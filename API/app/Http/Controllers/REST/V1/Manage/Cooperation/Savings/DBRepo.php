<?php

namespace App\Http\Controllers\REST\V1\Manage\Cooperation\Savings;

use App\Http\Libraries\BaseDBRepo;
use App\Models\CooperationSavingsTypeMapping;
use App\Models\CooperationMemberSavingsTransaction;
use App\Models\JournalEntry;
use Exception;
use Illuminate\Support\Facades\DB;

class DBRepo extends BaseDBRepo
{
    public function insertTransaction()
    {
        try {
            return DB::transaction(function () {
                $payload = $this->payload;
                $transactionType = $payload['transaction_type'];
                $groupedByMapping = [];

                // 1. Ambil aturan mapping dan kelompokkan item
                foreach ($payload['items'] as $item) {
                    $mapping = CooperationSavingsTypeMapping::where('cooperation_savings_type_id', $item['cooperation_savings_type_id'])->firstOrFail();
                    $key = $mapping->id;

                    if (!isset($groupedByMapping[$key])) {
                        $groupedByMapping[$key] = [
                            'mapping' => $mapping,
                            'items' => [],
                            'total_amount' => 0,
                        ];
                    }
                    $groupedByMapping[$key]['items'][] = $item;
                    $groupedByMapping[$key]['total_amount'] += $item['amount'];
                }

                // 2. Buat satu jurnal untuk setiap kelompok mapping
                foreach ($groupedByMapping as $group) {
                    $mapping = $group['mapping'];

                    // Tentukan akun Debit dan Kredit
                    if ($transactionType === 'DEPOSIT') {
                        $debitAccountId = $mapping->cash_account_id;
                        $description = $payload['description'] ?? "Setoran Simpanan Anggota";
                    } else { // WITHDRAWAL
                        $creditAccountId = $mapping->cash_account_id;
                        $description = $payload['description'] ?? "Penarikan Simpanan Anggota";
                    }

                    // --- PERBAIKAN DI SINI ---
                    // Ganti '$mapping->default_business_id' menjadi '$mapping->business_id'
                    $journalEntry = JournalEntry::create([
                        'business_id' => $mapping->business_id,
                        'entry_date' => $payload['transaction_date'],
                        'description' => $description,
                        'created_by_account_id' => $this->auth['account_id'],
                    ]);
                    // -------------------------

                    // Buat detail jurnal
                    if ($transactionType === 'DEPOSIT') {
                        // 1 Debit (Kas), N Kredit (Simpanan)
                        $journalEntry->details()->create(['account_chart_id' => $debitAccountId, 'entry_type' => 'DEBIT', 'amount' => $group['total_amount']]);
                        foreach ($group['items'] as $item) {
                            // Perbaikan: Pastikan kita menggunakan akun simpanan yang benar dari mapping
                            $journalEntry->details()->create(['account_chart_id' => $mapping->savings_account_id, 'entry_type' => 'CREDIT', 'amount' => $item['amount']]);
                        }
                    } else { // WITHDRAWAL
                        // N Debit (Simpanan), 1 Kredit (Kas)
                        foreach ($group['items'] as $item) {
                            // Perbaikan: Pastikan kita menggunakan akun simpanan yang benar dari mapping
                            $journalEntry->details()->create(['account_chart_id' => $mapping->savings_account_id, 'entry_type' => 'DEBIT', 'amount' => $item['amount']]);
                        }
                        $journalEntry->details()->create(['account_chart_id' => $creditAccountId, 'entry_type' => 'CREDIT', 'amount' => $group['total_amount']]);
                    }

                    // 3. Catat di histori transaksi untuk setiap item
                    foreach ($group['items'] as $item) {
                        CooperationMemberSavingsTransaction::create([
                            'member_id' => $payload['member_id'],
                            'cooperation_savings_type_id' => $item['cooperation_savings_type_id'],
                            'transaction_type' => $transactionType,
                            'amount' => $item['amount'],
                            'transaction_date' => $payload['transaction_date'],
                            'journal_entry_id' => $journalEntry->id,
                        ]);
                    }
                }
                return (object)[
                    'status' => true,
                    'data' => (object)['journals_created' => count($groupedByMapping)]
                ];
            });
        } catch (Exception $e) {
            return (object)[
                'status' => false,
                'message' => [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]
            ];
        }
    }

    public static function areSavingsTypesMapped(array $savingsTypeIds): bool
    {
        if (empty($savingsTypeIds)) return true;
        $count = CooperationSavingsTypeMapping::whereIn('cooperation_savings_type_id', array_unique($savingsTypeIds))->count();
        return $count === count(array_unique($savingsTypeIds));
    }

    /**
     * Mengambil data histori transaksi simpanan (daftar atau detail).
     * @return object
     */
    public function getData()
    {
        try {
            $query = CooperationMemberSavingsTransaction::query()
                ->with([
                    'member:id,name',
                    'savingsType:id,name,code',
                    // Ambil jurnal terkait, dan dari jurnal, ambil unit bisnisnya
                    'journalEntry.business:id,name'
                ]);

            // Kasus 1: Mengambil satu transaksi spesifik berdasarkan ID
            if (isset($this->payload['id'])) {
                $data = $query->find($this->payload['id']);
                return (object)['status' => !is_null($data), 'data' => $data ? $data->toArray() : null];
            }

            // Kasus 2: Mengambil daftar transaksi dengan filter
            $this->applyFilters($query);

            $perPage = $this->payload['per_page'] ?? 15;
            $data = $query->orderBy('transaction_date', 'desc')->orderBy('id', 'desc')->paginate($perPage);

            return (object)['status' => true, 'data' => $data->toArray()];
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Method pendukung untuk menerapkan filter pada query daftar transaksi.
     * @param \Illuminate\Database\Eloquent\Builder $query
     */
    private function applyFilters(&$query)
    {
        if (isset($this->payload['member_id'])) {
            $query->where('member_id', $this->payload['member_id']);
        }
        if (isset($this->payload['cooperation_savings_type_id'])) {
            $query->where('cooperation_savings_type_id', $this->payload['cooperation_savings_type_id']);
        }
        if (isset($this->payload['start_date']) && isset($this->payload['end_date'])) {
            $query->whereBetween('transaction_date', [$this->payload['start_date'], $this->payload['end_date']]);
        }
    }

    /**
     * Menghapus transaksi simpanan dan jurnal terkaitnya secara permanen.
     */
    public function deleteTransaction()
    {
        try {
            // Bungkus semua operasi dalam satu transaksi database
            return DB::transaction(function () {
                // 1. Temukan record transaksi simpanan yang akan dihapus
                $transactionToDelete = CooperationMemberSavingsTransaction::findOrFail($this->payload['id']);

                // 2. Simpan ID jurnalnya SEBELUM menghapus transaksi
                $journalEntryId = $transactionToDelete->journal_entry_id;

                // 3. Hapus record transaksi simpanan
                $transactionToDelete->delete();

                // 4. Temukan dan hapus record jurnal terkait secara manual
                $journalEntryToDelete = JournalEntry::find($journalEntryId);
                if ($journalEntryToDelete) {
                    // Menghapus JournalEntry akan secara otomatis meng-cascade
                    // penghapusan ke semua JournalEntryDetail terkait.
                    $journalEntryToDelete->delete();
                }

                return (object)['status' => true];
            });
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }
}
