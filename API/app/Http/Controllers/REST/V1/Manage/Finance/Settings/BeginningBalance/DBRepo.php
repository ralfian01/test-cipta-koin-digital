<?php

namespace App\Http\Controllers\REST\V1\Manage\Finance\Settings\BeginningBalance;

use App\Http\Libraries\BaseDBRepo;
use App\Models\JournalEntry;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class DBRepo extends BaseDBRepo
{
    /**
     * Mengambil data jurnal Saldo Awal untuk satu unit bisnis.
     */
    public function getData()
    {
        try {
            // Cari satu-satunya jurnal Saldo Awal untuk bisnis ini
            $openingBalanceJournal = JournalEntry::query()
                ->where('business_id', $this->payload['business_id'])
                ->where('type', 'OPENING_BALANCE')
                ->with(['details.accountChart:id,account_code,account_name']) // Eager load
                ->first();

            // Jika jurnal tidak ditemukan, kembalikan status sukses dengan data null
            if (!$openingBalanceJournal) {
                return (object)['status' => true, 'data' => null];
            }

            return (object)['status' => true, 'data' => $openingBalanceJournal->toArray()];
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }

    public function insertData()
    {
        try {
            return DB::transaction(function () {
                $entryPayload = Arr::only($this->payload, ['business_id', 'entry_date']);
                $entryPayload['created_by_account_id'] = $this->auth['account_id'];

                // --- KUNCI LOGIKA DI SINI ---
                $entryPayload['description'] = 'Saldo Awal Pembukuan';
                $entryPayload['type'] = 'OPENING_BALANCE';
                // -----------------------------

                $journalEntry = JournalEntry::create($entryPayload);
                $journalEntry->details()->createMany($this->payload['details']);
                return (object)['status' => true, 'data' => (object)['id' => $journalEntry->id]];
            });
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Memeriksa apakah jurnal saldo awal sudah ada untuk sebuah unit bisnis.
     */
    public static function doesOpeningBalanceExist(int $businessId): bool
    {
        return JournalEntry::where('business_id', $businessId)
            ->where('type', 'OPENING_BALANCE')
            ->exists();
    }

    /**
     * Membuat, Memperbarui, atau Menghapus jurnal Saldo Awal.
     */
    public function updateData()
    {
        try {
            return DB::transaction(function () {
                $businessId = $this->payload['business_id'];
                $details = $this->payload['details'];

                // Cari jurnal saldo awal yang sudah ada untuk bisnis ini
                $existingJournal = JournalEntry::where('business_id', $businessId)
                    ->where('type', 'OPENING_BALANCE')
                    ->first();

                // --- LOGIKA UTAMA DI SINI ---

                // Kasus 1: Hapus Jurnal
                // Jika payload 'details' kosong DAN jurnal sudah ada, hapus.
                if (empty($details) && $existingJournal) {
                    $existingJournal->delete(); // Cascade akan menghapus detailnya
                }
                // Kasus 2: Buat atau Update Jurnal
                // Jika payload 'details' TIDAK kosong.
                elseif (!empty($details)) {
                    $entryPayload = Arr::only($this->payload, ['business_id', 'entry_date']);
                    $entryPayload['created_by_account_id'] = $this->auth['account_id'];
                    $entryPayload['description'] = 'Saldo Awal Pembukuan';
                    $entryPayload['type'] = 'OPENING_BALANCE';

                    // Gunakan updateOrCreate untuk efisiensi
                    $journalEntry = JournalEntry::updateOrCreate(
                        // Kondisi pencarian
                        [
                            'business_id' => $businessId,
                            'type' => 'OPENING_BALANCE',
                        ],
                        // Data untuk di-update atau di-create
                        $entryPayload
                    );

                    // Ganti detail yang lama dengan yang baru
                    $journalEntry->details()->delete();
                    $journalEntry->details()->createMany($details);
                }

                return (object)['status' => true];
            });
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }
}
