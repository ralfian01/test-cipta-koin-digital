<?php

namespace App\Http\Controllers\REST\V1\Manage\Taxes;

use App\Http\Libraries\BaseDBRepo;
use App\Models\Tax;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class DBRepo extends BaseDBRepo
{
    public function getData()
    {
        try {
            $query = Tax::query()->with(['business' => fn($q) => $q->select('id', 'name')]);
            if (isset($this->payload['id'])) {
                $data = $query->find($this->payload['id']);
                return (object)['status' => !is_null($data), 'data' => $data ? $data->toArray() : null];
            }
            if (isset($this->payload['business_id'])) {
                $query->where('business_id', $this->payload['business_id']);
            }
            if (isset($this->payload['keyword'])) {
                $query->where('name', 'LIKE', "%{$this->payload['keyword']}%");
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
                $tax = Tax::create($this->payload);
                if (!$tax) {
                    throw new Exception("Failed to create tax.");
                }
                return (object)['status' => true, 'data' => (object)['id' => $tax->id]];
            });
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }
    public function updateData()
    {
        try {
            $tax = Tax::findOrFail($this->payload['id']);
            $dbPayload = Arr::except($this->payload, ['id']);
            return DB::transaction(function () use ($tax, $dbPayload) {
                $tax->update($dbPayload);
                return (object)['status' => true];
            });
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }
    public function deleteData()
    {
        try {
            $tax = Tax::findOrFail($this->payload['id']);
            $tax->delete();
            return (object)['status' => true];
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }

    public static function isNameUniqueInBusiness(string $name, int $businessId): bool
    {
        return !Tax::where('name', $name)->where('business_id', $businessId)->exists();
    }
    public static function isNameUniqueOnUpdate(string $name, int $businessId, int $ignoreId): bool
    {
        return !Tax::where('name', $name)->where('business_id', $businessId)->where('id', '!=', $ignoreId)->exists();
    }
    public static function findBusinessIid(int $taxId): ?int
    {
        return Tax::find($taxId)?->business_id;
    }
}
