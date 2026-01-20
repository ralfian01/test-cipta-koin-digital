<?php

namespace App\Http\Controllers\REST\V1\Manage\EmployeePositions;

use App\Http\Libraries\BaseDBRepo;
use App\Models\Position;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class DBRepo extends BaseDBRepo
{
    public function getData()
    {
        try {
            $query = Position::query()->with([
                'business' => fn($q) => $q->select('id', 'name'),
                'role' => fn($q) => $q->select('id', 'name'),
                'parent' => fn($q) => $q->select('id', 'name')
            ]);
            if (isset($this->payload['id'])) {
                $data = $query->find($this->payload['id']);
                return (object)['status' => !is_null($data), 'data' => $data ? $data->toArray() : null];
            }
            if (isset($this->payload['business_id'])) {
                $query->where('business_id', $this->payload['business_id']);
            }
            $data = $query->get();
            return (object)['status' => true, 'data' => $data->toArray()];
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }

    public function insertData()
    {
        try {
            return DB::transaction(function () {
                $position = Position::create($this->payload);
                if (!$position) {
                    throw new Exception("Failed to create position.");
                }
                return (object)['status' => true, 'data' => (object)['id' => $position->id]];
            });
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }

    public function updateData()
    {
        try {
            return DB::transaction(function () {
                $position = Position::findOrFail($this->payload['id']);
                $dbPayload = Arr::except($this->payload, ['id']);
                $position->update($dbPayload);
                return (object)['status' => true];
            });
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }

    public function deleteData()
    {
        try {
            $position = Position::findOrFail($this->payload['id']);
            $position->delete();
            return (object)['status' => true];
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }

    public static function isNameUniqueInBusiness(string $name, int $businessId): bool
    {
        return !Position::where('name', $name)->where('business_id', $businessId)->exists();
    }
    public static function isNameUniqueOnUpdate(string $name, int $businessId, int $ignoreId): bool
    {
        return !Position::where('name', $name)->where('business_id', $businessId)->where('id', '!=', $ignoreId)->exists();
    }
    public static function findBusinessId(int $positionId): ?int
    {
        return Position::find($positionId)?->business_id;
    }
    /**
     * Memeriksa apakah sebuah Posisi sedang digunakan oleh setidaknya satu Karyawan.
     * @param int $positionId
     * @return bool
     */
    public static function isPositionInUse(int $positionId): bool
    {
        $position = Position::find($positionId);
        if (!$position) {
            return false; // Posisi tidak ditemukan, jadi tidak "in use"
        }

        // Gunakan relasi 'employees()' dan cek apakah ada record terkait di tabel pivot.
        // exists() sangat efisien karena hanya melakukan query 'select 1 ... limit 1'.
        return $position->employees()->exists();
    }
}
