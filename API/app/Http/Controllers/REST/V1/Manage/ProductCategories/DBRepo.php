<?php

namespace App\Http\Controllers\REST\V1\Manage\ProductCategories;

use App\Http\Libraries\BaseDBRepo;
use App\Models\ProductCategory;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class DBRepo extends BaseDBRepo
{
    public function getData()
    {
        try {
            $query = ProductCategory::query()
                ->withCount('products')
                ->with([
                    'business' => fn($q) => $q->select('id', 'name'),
                ]);

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
                $category = ProductCategory::create($this->payload);
                if (!$category) {
                    throw new Exception("Failed to create product category.");
                }
                return (object)['status' => true, 'data' => (object)['id' => $category->id]];
            });
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }

    public function updateData()
    {
        try {
            $category = ProductCategory::findOrFail($this->payload['id']);
            $dbPayload = Arr::except($this->payload, ['id']);
            return DB::transaction(function () use ($category, $dbPayload) {
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
            $category = ProductCategory::findOrFail($this->payload['id']);
            $category->delete();
            return (object)['status' => true];
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }

    public static function isNameUniqueInBusiness(string $name, int $businessId): bool
    {
        return !ProductCategory::where('name', $name)->where('business_id', $businessId)->exists();
    }

    public static function isNameUniqueOnUpdate(string $name, int $businessId, int $ignoreId): bool
    {
        return !ProductCategory::where('name', $name)->where('business_id', $businessId)->where('id', '!=', $ignoreId)->exists();
    }

    public static function findBusinessId(int $categoryId): ?int
    {
        return ProductCategory::find($categoryId)?->business_id;
    }
}
