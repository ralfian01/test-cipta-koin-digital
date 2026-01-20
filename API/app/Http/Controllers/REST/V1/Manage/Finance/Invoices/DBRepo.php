<?php

namespace App\Http\Controllers\REST\V1\Manage\Finance\Invoices;

use App\Http\Libraries\BaseDBRepo;
use App\Models\BusinessFinanceSetting;
use App\Models\FinanceContact;
use App\Models\Invoice;
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
     * Mengambil data invoice (daftar atau detail).
     * @return object
     */
    public function getData()
    {
        try {
            // -- Query Eager Loading yang Detail --
            $query = Invoice::query()
                ->with([
                    'business:id,name',
                    'contact:id,name',
                    'items.accountChart.category', // Ambil item, akun, dan kategori akunnya
                    'payments.paymentMethodAccount:id,account_name', // Ambil histori pembayaran & nama akun kas/bank
                ]);

            // Kasus 1: Mengambil satu invoice spesifik berdasarkan ID
            if (isset($this->payload['id'])) {
                $data = $query->find($this->payload['id']);
                return (object)['status' => !is_null($data), 'data' => $data ? $data->toArray() : null];
            }

            // Kasus 2: Mengambil daftar invoice dengan filter
            $this->applyFilters($query);

            $perPage = $this->payload['per_page'] ?? 15;
            $data = $query->orderBy('invoice_date', 'desc')->orderBy('id', 'desc')->paginate($perPage);

            return (object)['status' => true, 'data' => $data->toArray()];
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Method pendukung untuk menerapkan filter pada query daftar invoice.
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
            $query->whereBetween('invoice_date', [$this->payload['start_date'], $this->payload['end_date']]);
        }
        if (isset($this->payload['keyword'])) {
            $keyword = $this->payload['keyword'];
            $query->where(function ($q) use ($keyword) {
                // Cari berdasarkan nomor invoice atau nama kontak
                $q->where('invoice_number', 'LIKE', "%{$keyword}%")
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
                if (!$settings || !$settings->default_ar_account_id) {
                    throw new Exception('Default Accounts Receivable (Piutang Usaha) is not set.');
                }
                $arAccountId = $settings->default_ar_account_id;

                $totalAmount = collect($this->payload['items'])->sum('amount');
                $initialPayment = (float)($this->payload['initial_payment_amount'] ?? 0);

                // 2. Buat entitas Invoice
                $invoice = Invoice::create([
                    'business_id' => $this->payload['business_id'],
                    'contact_id' => $this->payload['contact_id'],
                    'invoice_date' => $this->payload['invoice_date'],
                    'due_date' => $this->payload['due_date'],
                    'invoice_number' => self::generateInvoiceNumber(),
                    'total_amount' => $totalAmount,
                    // Status akan di-update nanti
                ]);
                $invoice->items()->createMany($this->payload['items']);

                // 3. SELALU BUAT JURNAL AKRUAL (jika ada nilai tagihan)
                // Ini adalah inti dari adopsi mekanisme akrual.
                if ($totalAmount > 0) {
                    $journalEntryAccrual = JournalEntry::create([
                        'business_id' => $invoice->business_id,
                        'entry_date' => $invoice->invoice_date,
                        'description' => "Tagihan #{$invoice->invoice_number} untuk {$invoice->contact->name}",
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
                        'business_id' => $invoice->business_id,
                        'entry_date' => $this->payload['invoice_date'],
                        'description' => "Penerimaan pembayaran awal untuk Tagihan #{$invoice->invoice_number}",
                        'created_by_account_id' => $this->auth['account_id'],
                    ]);

                    $journalEntryCash->details()->createMany([
                        ['account_chart_id' => $paymentAccountId, 'entry_type' => 'DEBIT', 'amount' => $initialPayment],
                        ['account_chart_id' => $arAccountId, 'entry_type' => 'CREDIT', 'amount' => $initialPayment],
                    ]);

                    // Catat juga di histori pembayaran
                    $invoice->payments()->create([
                        'payment_date' => $this->payload['invoice_date'],
                        'amount' => $initialPayment,
                        'payment_method_account_id' => $paymentAccountId,
                    ]);
                }

                // 5. Tentukan Status Final Invoice
                $finalPaidAmount = $initialPayment;
                $finalStatus = 'DRAFT';
                if ($finalPaidAmount >= $totalAmount) {
                    $finalStatus = 'PAID';
                } elseif ($finalPaidAmount > 0) {
                    $finalStatus = 'PARTIALLY_PAID';
                } else {
                    $finalStatus = ($totalAmount > 0) ? 'SENT' : 'PAID';
                }
                $invoice->update(['paid_amount' => $finalPaidAmount, 'status' => $finalStatus]);

                return (object)['status' => true, 'data' => (object)['invoice_id' => $invoice->id]];
            });
        } catch (Exception $e) {
            return (object)[
                'status' => false,
                'message' => $e->getMessage()
            ];
        }
    }


    /**
     * Mencatat pembayaran untuk sebuah invoice, membuat jurnal kas, dan mengupdate status.
     * Method ini bisa dipanggil baik saat membuat invoice (pembayaran awal)
     * maupun dari endpoint pembayaran terpisah.
     *
     * @param Invoice $invoice Objek invoice yang akan dibayar
     * @param array $paymentData Data pembayaran dari payload
     * @param BusinessFinanceSetting|null $settings Pengaturan keuangan (opsional, akan diambil jika null)
     * @return object
     */
    public function insertPayment(Invoice $invoice, array $paymentData, $settings = null)
    {
        try {
            return DB::transaction(function () use ($invoice, $paymentData, $settings) {
                // 1. Ambil pengaturan jika belum disediakan
                if (!$settings) {
                    $settings = BusinessFinanceSetting::where('business_id', $invoice->business_id)->first();
                    if (!$settings || !$settings->default_ar_account_id) {
                        throw new Exception('Default Accounts Receivable is not set.');
                    }
                }

                // 2. Buat record di histori pembayaran
                $invoice->payments()->create($paymentData);

                // 3. Buat Jurnal Penerimaan Kas Otomatis
                $journalEntry = JournalEntry::create([
                    'business_id' => $invoice->business_id,
                    'entry_date' => $paymentData['payment_date'],
                    'description' => "Penerimaan pembayaran untuk Tagihan #{$invoice->invoice_number}",
                    'created_by_account_id' => $this->auth['account_id'],
                ]);

                // Jurnalnya adalah: Debit Kas/Bank, Kredit Piutang Usaha
                $journalEntry->details()->createMany([
                    ['account_chart_id' => $paymentData['payment_method_account_id'], 'entry_type' => 'DEBIT', 'amount' => $paymentData['amount']],
                    ['account_chart_id' => $settings->default_ar_account_id, 'entry_type' => 'CREDIT', 'amount' => $paymentData['amount']],
                ]);

                // 4. Update status dan paid_amount di invoice
                $newPaidAmount = (float)$invoice->paid_amount + (float)$paymentData['amount'];

                // Gunakan toleransi kecil untuk perbandingan float
                $isFullyPaid = (abs($newPaidAmount - $invoice->total_amount) < 0.01);

                $newStatus = $isFullyPaid ? 'PAID' : 'PARTIALLY_PAID';

                $invoice->update([
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

    public static function isContactACustomer(int $contactId): bool
    {
        $contact = FinanceContact::find($contactId);
        return $contact && $contact->contact_type === 'CUSTOMER';
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

    public static function generateInvoiceNumber(): string
    {
        // Implementasi sederhana, bisa Anda buat lebih kompleks
        return 'INV-' . date('Ymd') . '-' . str_pad(Invoice::count() + 1, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Memvalidasi apakah sebuah invoice siap untuk menerima pembayaran.
     * Mengembalikan array [bool status, string message].
     *
     * @param int $invoiceId
     * @param float $paymentAmount
     * @return array
     */
    public static function validatePayment(int $invoiceId, float $paymentAmount): array
    {
        $invoice = Invoice::find($invoiceId);

        // Validasi 1: Pastikan invoice ada
        if (!$invoice) {
            return [false, 'Invoice not found.'];
        }

        // Validasi 2: Pastikan invoice belum lunas
        if ($invoice->status === 'PAID') {
            return [false, 'This invoice has already been fully paid.'];
        }

        // Validasi 3: Jumlah pembayaran tidak boleh melebihi sisa tagihan
        $remainingAmount = (float)$invoice->total_amount - (float)$invoice->paid_amount;
        if ($paymentAmount > round($remainingAmount, 2) + 0.001) { // Toleransi pembulatan
            return [false, "Payment amount exceeds the remaining balance of {$remainingAmount}."];
        }

        return [true, 'Validation successful.'];
    }
}
