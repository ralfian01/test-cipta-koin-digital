<?php

namespace App\Http\Controllers\REST\V1\Manage\Finance\JournalEntries;

use App\Http\Libraries\BaseDBRepo;
use App\Models\JournalEntry;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class DBRepo extends BaseDBRepo
{
    public function getData()
    {
        try {
            $query = JournalEntry::query()
                ->with(['details.accountChart', 'createdBy']);
            if (isset($this->payload['id'])) {
                $data = $query->find($this->payload['id']);
                return (object)['status' => !is_null($data), 'data' => $data ? $data->toArray() : null];
            }

            if (isset($this->payload['start_date'], $this->payload['end_date'])) {
                $query->whereBetween('entry_date', [$this->payload['start_date'], $this->payload['end_date']]);
            }

            if (isset($this->payload['business_id'])) {
                $query->where('business_id', $this->payload['business_id']);
            }

            $perPage = $this->payload['per_page'] ?? 15;

            $data = $query->orderBy('entry_date', 'desc')->paginate($perPage);
            return (object)['status' => true, 'data' => $data->toArray()];
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }
    public function insertData()
    {
        try {
            return DB::transaction(function () {
                $entryPayload = Arr::except($this->payload, ['details']);
                $entryPayload['created_by_account_id'] = $this->auth['account_id'];

                $details = collect($this->payload['details'])->map(function ($detail) {
                    if (isset($detail['amount'])) {
                        $detail['amount'] = (string) $detail['amount'];
                    }
                    return $detail;
                })->toArray();

                $journalEntry = JournalEntry::create($entryPayload);
                $journalEntry->details()->createMany($details);
                return (object)['status' => true, 'data' => (object)['id' => $journalEntry->id]];
            });
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }
    public function updateData()
    {
        try {
            return DB::transaction(function () {
                $journalEntry = JournalEntry::findOrFail($this->payload['id']);
                $entryPayload = Arr::except($this->payload, ['id', 'details', 'business_id']);
                $journalEntry->update($entryPayload);
                if (isset($this->payload['details'])) {
                    $journalEntry->details()->delete();
                    $journalEntry->details()->createMany($this->payload['details']);
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
            $journalEntry = JournalEntry::findOrFail($this->payload['id']);
            $journalEntry->delete(); // onDelete('cascade') akan menghapus details
            return (object)['status' => true];
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }
    public static function validateBalance(array $details): bool
    {
        $debits = 0;
        $credits = 0;
        foreach ($details as $detail) {
            if ($detail['entry_type'] === 'DEBIT') $debits += $detail['amount'];
            if ($detail['entry_type'] === 'CREDIT') $credits += $detail['amount'];
        }
        return abs($debits - $credits) < 0.01; // Toleransi pembulatan kecil
    }
}
