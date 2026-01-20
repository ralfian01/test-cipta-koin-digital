<?php

namespace App\Http\Controllers\REST\V1\Manage\Finance\Bills;

use App\Http\Libraries\BaseDBRepo;
use App\Models\BusinessFinanceSetting;
use App\Models\FinanceContact;
use App\Models\Bill;
use App\Models\JournalEntry;
use App\Models\AccountChart;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class DBRepo extends BaseDBRepo
{
    /*
     * =================================================================================
     * METHOD UTAMA
     * =================================================================================
     */

    /**
     * Mengambil data bill (daftar atau detail).
     * @return object
     */
    public function getData()
    {
        try {
            // -- Query Eager Loading yang Detail --
            $query = Bill::query()
                ->with([
                    'business:id,name',
                    'contact:id,name',
                    'items.accountChart.category', // Ambil item, akun, dan kategori akunnya
                    'payments.paymentMethodAccount:id,account_name', // Ambil histori pembayaran & nama akun kas/bank
                ]);

            // Kasus 1: Mengambil satu bill spesifik berdasarkan ID
            if (isset($this->payload['id'])) {
                $data = $query->find($this->payload['id']);
                return (object)['status' => !is_null($data), 'data' => $data ? $data->toArray() : null];
            }

            // Kasus 2: Mengambil daftar bill dengan filter
            $this->applyFilters($query);

            $perPage = $this->payload['per_page'] ?? 15;
            $data = $query->orderBy('bill_date', 'desc')->orderBy('id', 'desc')->paginate($perPage);

            return (object)['status' => true, 'data' => $data->toArray()];
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Method pendukung untuk menerapkan filter pada query daftar bill.
     * @param \Illuminate\Database\Eloquent\Builder $query
     */
    private function applyFilters(&$query)
    {
        if (isset($this->payload['business_id'])) {
            $query->where('business_id', $this->payload['business_id']);
        }
        if (isset($this->payload['contact_id'])) {
            $query->where('contact_id', $this->payload['contact_id']);
        }
        if (isset($this->payload['status'])) {
            $query->where('status', $this->payload['status']);
        }
        if (isset($this->payload['start_date']) && isset($this->payload['end_date'])) {
            $query->whereBetween('bill_date', [$this->payload['start_date'], $this->payload['end_date']]);
        }
        if (isset($this->payload['keyword'])) {
            $keyword = $this->payload['keyword'];
            $query->where(function ($q) use ($keyword) {
                // Cari berdasarkan nomor bill atau nama kontak
                $q->where('bill_number', 'LIKE', "%{$keyword}%")
                    ->orWhereHas('contact', function ($contactQuery) use ($keyword) {
                        $contactQuery->where('name', 'LIKE', "%{$keyword}%");
                    });
            });
        }
    }


    public function insertData()
    {
        try {
            return DB::transaction(function () {
                // 1. Ambil Pengaturan Akun Default (AR dan Kas)
                $settings = BusinessFinanceSetting::where('business_id', $this->payload['business_id'])->first();
                if (!$settings || !$settings->default_ap_account_id) {
                    throw new Exception('Default Accounts Receivable (Piutang Usaha) is not set.');
                }
                $arAccountId = $settings->default_ap_account_id;

                $totalAmount = collect($this->payload['items'])->sum('amount');
                $initialPayment = (float)($this->payload['initial_payment_amount'] ?? 0);

                // 2. Buat entitas bill
                $bill = Bill::create([
                    'business_id' => $this->payload['business_id'],
                    'contact_id' => $this->payload['contact_id'],
                    'bill_date' => $this->payload['bill_date'],
                    'due_date' => $this->payload['due_date'],
                    'bill_number' => self::generateBillNumber(),
                    'total_amount' => $totalAmount,
                    // Status akan di-update nanti
                ]);
                $bill->items()->createMany($this->payload['items']);

                // 3. SELALU BUAT JURNAL AKRUAL (jika ada nilai tagihan)
                // Ini adalah inti dari adopsi mekanisme akrual.
                if ($totalAmount > 0) {
                    $journalEntryAccrual = JournalEntry::create([
                        'business_id' => $bill->business_id,
                        'entry_date' => $bill->bill_date,
                        'description' => "Tagihan #{$bill->bill_number} untuk {$bill->contact->name}",
                        'created_by_account_id' => $this->auth['account_id'],
                    ]);

                    $details = [['account_chart_id' => $arAccountId, 'entry_type' => 'DEBIT', 'amount' => $totalAmount]];
                    foreach ($this->payload['items'] as $item) {
                        $details[] = ['account_chart_id' => $item['account_chart_id'], 'entry_type' => 'CREDIT', 'amount' => $item['amount']];
                    }
                    $journalEntryAccrual->details()->createMany($details);
                }

                // 4. BUAT JURNAL KAS (jika ada pembayaran awal)
                if ($initialPayment > 0) {
                    $paymentAccountId = $this->payload['payment_method_account_id'];

                    $journalEntryCash = JournalEntry::create([
                        'business_id' => $bill->business_id,
                        'entry_date' => $this->payload['bill_date'],
                        'description' => "Penerimaan pembayaran awal untuk Tagihan #{$bill->bill_number}",
                        'created_by_account_id' => $this->auth['account_id'],
                    ]);

                    $journalEntryCash->details()->createMany([
                        ['account_chart_id' => $paymentAccountId, 'entry_type' => 'DEBIT', 'amount' => $initialPayment],
                        ['account_chart_id' => $arAccountId, 'entry_type' => 'CREDIT', 'amount' => $initialPayment],
                    ]);

                    // Catat juga di histori pembayaran
                    $bill->payments()->create([
                        'payment_date' => $this->payload['bill_date'],
                        'amount' => $initialPayment,
                        'payment_method_account_id' => $paymentAccountId,
                    ]);
                }

                // 5. Tentukan Status Final bill
                $finalPaidAmount = $initialPayment;
                $finalStatus = 'DRAFT';
                if ($finalPaidAmount >= $totalAmount) {
                    $finalStatus = 'PAID';
                } elseif ($finalPaidAmount > 0) {
                    $finalStatus = 'PARTIALLY_PAID';
                } else {
                    $finalStatus = ($totalAmount > 0) ? 'SUBMITTED' : 'PAID';
                }
                $bill->update(['paid_amount' => $finalPaidAmount, 'status' => $finalStatus]);

                return (object)['status' => true, 'data' => (object)['bill_id' => $bill->id]];
            });
        } catch (Exception $e) {
            return (object)[
                'status' => false,
                'message' => $e->getMessage()
            ];
        }
    }


    /**
     * Mencatat pembayaran untuk sebuah bill, membuat jurnal kas, dan mengupdate status.
     * Method ini bisa dipanggil baik saat membuat bill (pembayaran awal)
     * maupun dari endpoint pembayaran terpisah.
     *
     * @param bill $bill Objek bill yang akan dibayar
     * @param array $paymentData Data pembayaran dari payload
     * @param BusinessFinanceSetting|null $settings Pengaturan keuangan (opsional, akan diambil jika null)
     * @return object
     */
    public function insertPayment(bill $bill, array $paymentData, $settings = null)
    {
        try {
            return DB::transaction(function () use ($bill, $paymentData, $settings) {
                // 1. Ambil pengaturan jika belum disediakan
                if (!$settings) {
                    $settings = BusinessFinanceSetting::where('business_id', $bill->business_id)->first();
                    if (!$settings || !$settings->default_ap_account_id) {
                        throw new Exception('Default Accounts Receivable is not set.');
                    }
                }

                // 2. Buat record di histori pembayaran
                $bill->payments()->create($paymentData);

                // 3. Buat Jurnal Penerimaan Kas Otomatis
                $journalEntry = JournalEntry::create([
                    'business_id' => $bill->business_id,
                    'entry_date' => $paymentData['payment_date'],
                    'description' => "Penerimaan pembayaran untuk Tagihan #{$bill->bill_number}",
                    'created_by_account_id' => $this->auth['account_id'],
                ]);

                // Jurnalnya adalah: Debit Kas/Bank, Kredit Piutang Usaha
                $journalEntry->details()->createMany([
                    ['account_chart_id' => $paymentData['payment_method_account_id'], 'entry_type' => 'DEBIT', 'amount' => $paymentData['amount']],
                    ['account_chart_id' => $settings->default_ap_account_id, 'entry_type' => 'CREDIT', 'amount' => $paymentData['amount']],
                ]);

                // 4. Update status dan paid_amount di bill
                $newPaidAmount = (float)$bill->paid_amount + (float)$paymentData['amount'];

                // Gunakan toleransi kecil untuk perbandingan float
                $isFullyPaid = (abs($newPaidAmount - $bill->total_amount) < 0.01);

                $newStatus = $isFullyPaid ? 'PAID' : 'PARTIALLY_PAID';

                $bill->update([
                    'paid_amount' => $newPaidAmount,
                    'status' => $newStatus
                ]);

                return (object)['status' => true];
            });
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }


    /*
     * =================================================================================
     * METHOD STATIS UNTUK VALIDASI
     * =================================================================================
     */

    public static function isContactAVendor(int $contactId): bool
    {
        $contact = FinanceContact::find($contactId);
        return $contact && $contact->contact_type === 'VENDOR';
    }

    public static function areAccountsOfType(array $accountIds, string $accountType): bool
    {
        if (empty($accountIds)) return true;
        $count = AccountChart::whereIn('id', $accountIds)
            ->whereHas('category', fn($q) => $q->where('account_type', $accountType))
            ->count();
        return $count === count(array_unique($accountIds));
    }

    public static function isAccountCashEquivalent(int $accountId): bool
    {
        $account = AccountChart::with('category')->find($accountId);
        return $account && $account->category->is_cash_equivalent;
    }

    public static function generateBillNumber(): string
    {
        // Implementasi sederhana, bisa Anda buat lebih kompleks
        return 'INV-' . date('Ymd') . '-' . str_pad(Bill::count() + 1, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Memvalidasi apakah sebuah bill siap untuk menerima pembayaran.
     * Mengembalikan array [bool status, string message].
     *
     * @param int $billId
     * @param float $paymentAmount
     * @return array
     */
    public static function validatePayment(int $billId, float $paymentAmount): array
    {
        $bill = Bill::find($billId);

        // Validasi 1: Pastikan bill ada
        if (!$bill) {
            return [false, 'bill not found.'];
        }

        // Validasi 2: Pastikan bill belum lunas
        if ($bill->status === 'PAID') {
            return [false, 'This bill has already been fully paid.'];
        }

        // Validasi 3: Jumlah pembayaran tidak boleh melebihi sisa tagihan
        $remainingAmount = (float)$bill->total_amount - (float)$bill->paid_amount;
        if ($paymentAmount > round($remainingAmount, 2) + 0.001) { // Toleransi pembulatan
            return [false, "Payment amount exceeds the remaining balance of {$remainingAmount}."];
        }

        return [true, 'Validation successful.'];
    }
}
