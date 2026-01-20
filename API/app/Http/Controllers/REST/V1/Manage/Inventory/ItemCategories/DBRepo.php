<?php

namespace App\Http\Controllers\REST\V1\Manage\Inventory\ItemCategories;

use App\Http\Libraries\BaseDBRepo;
use App\Models\InventoryItemCategory;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class DBRepo extends BaseDBRepo
{
    public function getData()
    {
        try {
            $query = InventoryItemCategory::query()
                ->withCount('items')
                ->where('business_id', $this->payload['business_id']);

            if (isset($this->payload['id'])) {
                $data = $query->find($this->payload['id']);
                return (object)['status' => !is_null($data), 'data' => $data ? $data->toArray() : null];
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
                $category = InventoryItemCategory::create($this->payload);
                if (!$category) throw new Exception("Failed to create inventory item category.");
                return (object)['status' => true, 'data' => (object)['id' => $category->id]];
            });
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }

    public function updateData()
    {
        try {
            return DB::transaction(function () {
                $category = InventoryItemCategory::findOrFail($this->payload['id']);
                $dbPayload = Arr::except($this->payload, ['id']);
                $category->update($dbPayload);
                return (object)['status' => true];
            });
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }

    public function deleteData()
    {
        try {
            $category = InventoryItemCategory::findOrFail($this->payload['id']);
            // onDelete('set null') pada migrasi inventory_items akan menangani item terkait
            $category->delete();
            return (object)['status' => true];
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }

    // -- METHOD STATIC/TOOLS --
    public static function isNameUniqueInBusiness(string $name, int $businessId): bool
    {
        return !InventoryItemCategory::where('name', $name)->where('business_id', $businessId)->exists();
    }

    public static function isNameUniqueOnUpdate(string $name, int $businessId, int $ignoreId): bool
    {
        return !InventoryItemCategory::where('name', $name)
            ->where('business_id', $businessId)
            ->where('id', '!=', $ignoreId)
            ->exists();
    }

    public static function findBusinessId(int $categoryId): ?int
    {
        return InventoryItemCategory::find($categoryId)?->business_id;
    }
}
