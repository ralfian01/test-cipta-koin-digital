<?php

namespace App\Http\Controllers\REST\V1\Manage\Finance\CashReceipts;

use App\Http\Controllers\REST\V1\Manage\Finance\JournalEntries\DBRepo as JournalRepo; // Re-use validator
use App\Http\Libraries\BaseDBRepo;
use App\Models\BusinessFinanceSetting;
use App\Models\JournalEntry;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class DBRepo extends BaseDBRepo
{
    public function getData()
    {
        // Panggil helper dengan parameter 'DEBIT' untuk penerimaan kas
        return $this->getJournalData('DEBIT');
    }

    /**
     * Helper method yang bisa digunakan kembali untuk mengambil data jurnal kas.
     * @param string $cashMovementType 'DEBIT' untuk penerimaan, 'CREDIT' untuk pengeluaran.
     * @return object
     */
    private function getJournalData(string $cashMovementType)
    {
        try {
            // 1. Ambil Akun Kas Default
            $settings = BusinessFinanceSetting::where('business_id', $this->payload['business_id'])->first();
            if (!$settings || !$settings->default_cash_account_id) {
                // Jika tidak ada setting, kembalikan koleksi kosong
                return (object)['status' => true, 'data' => []];
            }
            $defaultCashAccountId = $settings->default_cash_account_id;

            // 2. Buat query utama
            $query = JournalEntry::query()
                ->with(['details.accountChart', 'business', 'createdBy.userProfile'])
                // KUNCI LOGIKA: Cari jurnal yang memiliki detail yang cocok
                ->whereHas('details', function ($q) use ($defaultCashAccountId, $cashMovementType) {
                    $q->where('account_chart_id', $defaultCashAccountId)
                        ->where('entry_type', $cashMovementType);
                })
                ->where('business_id', $this->payload['business_id']);

            // 3. Terapkan filter
            if (isset($this->payload['start_date']) && isset($this->payload['end_date'])) {
                $query->whereBetween('entry_date', [$this->payload['start_date'], $this->payload['end_date']]);
            }
            if (isset($this->payload['keyword'])) {
                $query->where('description', 'LIKE', "%{$this->payload['keyword']}%");
            }

            $perPage = $this->payload['per_page'] ?? 15;
            $data = $query->orderBy('entry_date', 'desc')->orderBy('id', 'desc')->paginate($perPage);

            return (object)['status' => true, 'data' => $data->toArray()];
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }


    public function insertData()
    {
        try {
            return DB::transaction(function () {
                // 1. Ambil Akun Kas Default
                $settings = BusinessFinanceSetting::where('business_id', $this->payload['business_id'])->first();
                if (!$settings || !$settings->default_cash_account_id) {
                    throw new Exception("Default cash account is not set for this business unit.");
                }
                $defaultCashAccountId = $settings->default_cash_account_id;

                // 2. Siapkan sisi Debit (Kas) secara otomatis
                $totalAmount = array_sum(array_column($this->payload['details'], 'amount'));
                $finalDetails = [
                    [
                        'account_chart_id' => $defaultCashAccountId,
                        'entry_type' => 'DEBIT',
                        'amount' => $totalAmount,
                    ]
                ];

                // 3. Siapkan sisi Kredit (sisi lawan) dari payload
                foreach ($this->payload['details'] as $detail) {
                    $finalDetails[] = [
                        'account_chart_id' => $detail['account_chart_id'],
                        'entry_type' => 'CREDIT',
                        'amount' => $detail['amount'],
                    ];
                }

                // 4. Validasi keseimbangan
                if (!JournalRepo::validateBalance($finalDetails)) {
                    throw new Exception("Journal could not be balanced.");
                }

                // 5. Buat Jurnal
                $entryPayload = Arr::only($this->payload, ['business_id', 'entry_date', 'description']);
                $entryPayload['created_by_account_id'] = $this->auth['account_id'];
                $journalEntry = JournalEntry::create($entryPayload);
                $journalEntry->details()->createMany($finalDetails);

                return (object)['status' => true, 'data' => (object)['journal_entry_id' => $journalEntry->id]];
            });
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }
}
