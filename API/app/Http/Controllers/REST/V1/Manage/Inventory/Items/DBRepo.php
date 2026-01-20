<?php

namespace App\Http\Controllers\REST\V1\Manage\Inventory\Items;

use App\Http\Libraries\BaseDBRepo;
use App\Models\InventoryItem;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class DBRepo extends BaseDBRepo
{
    public function getData()
    {
        try {
            $query = InventoryItem::query()
                ->with('category', 'unit');

            if (isset($this->payload['id'])) {
                $data = $query->find($this->payload['id']);
                return (object)['status' => !is_null($data), 'data' => $data ? $data->toArray() : null];
            } else {
                $query->where('business_id', $this->payload['business_id']);
            }

            if (isset($this->payload['category_id']))
                $query->where('category_id', $this->payload['category_id']);

            if (isset($this->payload['keyword']))
                $query->where(fn($q) => $q->where('name', 'LIKE', "%{$this->payload['keyword']}%")->orWhere('sku', 'LIKE', "%{$this->payload['keyword']}%"));

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
                $item = InventoryItem::create($this->payload);
                if (!$item) throw new Exception("Failed to create inventory item.");
                return (object)['status' => true, 'data' => (object)['id' => $item->id]];
            });
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }

    public function updateData()
    {
        try {
            return DB::transaction(function () {
                $item = InventoryItem::findOrFail($this->payload['id']);
                $dbPayload = Arr::except($this->payload, ['id']);
                $item->update($dbPayload);
                return (object)['status' => true];
            });
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }

    public function deleteData()
    {
        try {
            $item = InventoryItem::findOrFail($this->payload['id']);
            $item->delete();
            return (object)['status' => true];
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }

    // -- METHOD STATIC/TOOLS YANG DIPERBARUI --
    public static function isSkuUniqueInBusiness(string $sku, int $businessId): bool
    {
        return !InventoryItem::where('sku', $sku)->where('business_id', $businessId)->exists();
    }

    public static function isSkuUniqueOnUpdate(?string $sku, int $businessId, int $ignoreId): bool
    {
        if (is_null($sku)) return true;
        return !InventoryItem::where('sku', $sku)
            ->where('business_id', $businessId)
            ->where('id', '!=', $ignoreId)
            ->exists();
    }

    public static function findBusinessId(int $itemId): ?int
    {
        return InventoryItem::find($itemId)?->business_id;
    }
}
