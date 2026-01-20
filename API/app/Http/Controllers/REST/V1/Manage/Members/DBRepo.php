<?php

namespace App\Http\Controllers\REST\V1\Manage\Members;

use App\Http\Libraries\BaseDBRepo;
use App\Models\Member;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class DBRepo extends BaseDBRepo
{
    public function getData()
    {
        try {
            $query = Member::query();

            if (isset($this->payload['id'])) {
                $data = $query->find($this->payload['id']);
                return (object)['status' => !is_null($data), 'data' => $data ? $data->toArray() : null];
            }

            if (isset($this->payload['keyword'])) {
                $keyword = $this->payload['keyword'];
                $query->where(function ($subQuery) use ($keyword) {
                    $subQuery->where('name', 'LIKE', "%{$keyword}%")
                        ->orWhere('member_code', 'LIKE', "%{$keyword}%"); // -- PERUBAHAN DI SINI --
                });
            }

            $perPage = $this->payload['per_page'] ?? 15;
            $data = $query->paginate($perPage);
            return (object)['status' => true, 'data' => $data->toArray()];
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }

    public function insertData()
    {
        try {
            return DB::transaction(function () {
                $member = Member::create($this->payload);
                if (!$member) {
                    throw new Exception("Failed to create a new member.");
                }
                return (object)['status' => true, 'data' => (object)['id' => $member->id]];
            });
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }

    public function updateData()
    {
        try {
            $member = Member::findOrFail($this->payload['id']);
            $dbPayload = Arr::except($this->payload, ['id']);

            return DB::transaction(function () use ($member, $dbPayload) {
                $member->update($dbPayload);
                return (object)['status' => true];
            });
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }

    public function deleteData()
    {
        try {
            $member = Member::findOrFail($this->payload['id']);
            $member->delete();
            return (object)['status' => true];
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * -- PERUBAHAN NAMA METHOD DAN LOGIKA --
     */
    public static function isMemberCodeUniqueOnUpdate(string $memberCode, int $ignoreId): bool
    {
        return !Member::where('member_code', $memberCode)
            ->where('id', '!=', $ignoreId)
            ->exists();
    }
}
