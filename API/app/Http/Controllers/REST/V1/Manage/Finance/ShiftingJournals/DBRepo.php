<?php

namespace App\Http\Controllers\REST\V1\Manage\Finance\ShiftingJournals;

use App\Http\Libraries\BaseDBRepo;
use App\Models\JournalEntry;
use App\Models\ShiftingJournalEntry;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class DBRepo extends BaseDBRepo
{
    public function getData()
    {
        try {
            $query = ShiftingJournalEntry::query()
                ->with(['details.accountChart', 'business:id,name']);

            if (isset($this->payload['id'])) {
                $data = $query->find($this->payload['id']);
                return (object)['status' => !is_null($data), 'data' => $data ? $data->toArray() : null];
            }

            $query->where('business_id', $this->payload['business_id']);
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
                // 1. Siapkan data untuk header jurnal
                $entryPayload = Arr::only($this->payload, [
                    'business_id',
                    'entry_date',
                    'description',
                    'reference_number'
                ]);
                $entryPayload['created_by_account_id'] = $this->auth['account_id'];

                // 2. Buat Header Jurnal Pergeseran
                $shiftingJournal = ShiftingJournalEntry::create($entryPayload);

                // 3. Buat Detail Jurnal Pergeseran dari payload
                $shiftingJournal->details()->createMany($this->payload['details']);

                return (object)['status' => true, 'data' => (object)['id' => $shiftingJournal->id]];
            });
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }

    public function updateData()
    {
        try {
            return DB::transaction(function () {
                $journal = ShiftingJournalEntry::findOrFail($this->payload['id']);

                // Update header
                $headerPayload = Arr::only($this->payload, ['entry_date', 'description', 'reference_number']);
                if (!empty($headerPayload)) {
                    $journal->update($headerPayload);
                }

                // Jika ada detail baru, ganti yang lama
                if (isset($this->payload['details'])) {
                    $journal->details()->delete();
                    $journal->details()->createMany($this->payload['details']);
                }

                return (object)['status' => true];
            });
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }

    public function deleteData()
    {
        try {
            $journal = ShiftingJournalEntry::findOrFail($this->payload['id']);
            // onDelete('cascade') akan menghapus semua detail terkait secara otomatis
            $journal->delete();
            return (object)['status' => true];
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }
}
