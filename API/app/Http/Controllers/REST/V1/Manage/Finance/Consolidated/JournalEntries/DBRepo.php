<?php

namespace App\Http\Controllers\REST\V1\Manage\Finance\Consolidated\JournalEntries;

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
            $query = JournalEntry::query()->with(['details.accountChart', 'createdBy.userProfile']);
            if (isset($this->payload['id'])) {
                $data = $query->find($this->payload['id']);
                return (object)['status' => !is_null($data), 'data' => $data ? $data->toArray() : null];
            }

            if (isset($this->payload['business_id'])) {
                $query->where('business_id', $this->payload['business_id']);
            }

            $data = $query->orderBy('entry_date', 'desc')->paginate(15);
            return (object)['status' => true, 'data' => $data->toArray()];
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }
}
