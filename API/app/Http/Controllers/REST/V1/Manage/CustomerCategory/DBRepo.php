<?php

namespace App\Http\Controllers\REST\V1\Manage\CustomerCategory;

use App\Http\Libraries\BaseDBRepo;
use App\Models\CustomerCategory;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;


class DBRepo extends BaseDBRepo
{
    /*
     * =================================================================================
     * METHOD UNTUK MENGAMBIL DATA (GET)
     * =================================================================================
     */
    public function getData()
    {
        try {
            // --- PERUBAHAN DI SINI ---
            $query = CustomerCategory::query()
                // Tetap hitung jumlah customer, ini efisien
                ->withCount('customers')
                // Muat relasi business, tapi hanya pilih kolom yang perlu
                ->with(['business' => function ($query) {
                    $query->select('id', 'name');
                }]);
            // ------------------------

            if (isset($this->payload['id'])) {
                $data = $query->find($this->payload['id']);
                return (object) ['status' => !is_null($data), 'data' => $data ? $data->toArray() : null];
            }

            if (isset($this->payload['business_id'])) {
                $query->where('business_id', $this->payload['business_id']);
            }
            if (isset($this->payload['keyword'])) {
                $query->where('name', 'LIKE', "%{$this->payload['keyword']}%");
            }

            $perPage = $this->payload['per_page'] ?? 15;
            $data = $query->paginate($perPage);

            return (object) ['status' => true, 'data' => $data->toArray()];
        } catch (Exception $e) {
            return (object) ['status' => false, 'message' => $e->getMessage()];
        }
    }


    /*
     * =================================================================================
     * METHOD UNTUK MENAMBAH DATA (INSERT)
     * =================================================================================
     */
    public function insertData()
    {
        try {
            return DB::transaction(function () {
                $customerCategory = CustomerCategory::create($this->payload);

                if (!$customerCategory) {
                    throw new Exception("Failed to create a new customer category.");
                }

                return (object) ['status' => true, 'data' => (object) ['id' => $customerCategory->id]];
            });
        } catch (Exception $e) {
            return (object) ['status' => false, 'message' => $e->getMessage()];
        }
    }

    public function updateData()
    {
        try {
            $categoryId = $this->payload['id'];
            $category = CustomerCategory::findOrFail($categoryId);
            $dbPayload = Arr::except($this->payload, ['id']);

            return DB::transaction(function () use ($category, $dbPayload) {
                $category->update($dbPayload);
                return (object) ['status' => true];
            });
        } catch (Exception $e) {
            return (object) ['status' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Fungsi utama untuk menghapus data kategori customer.
     * @return object
     */
    public function deleteData()
    {
        try {
            $categoryId = $this->payload['id'];
            $category = CustomerCategory::findOrFail($categoryId);

            // onDelete('set null') pada migrasi customers akan menangani customer terkait.
            // Saat kategori dihapus, customer yang ada di dalamnya akan memiliki
            // customer_category_id menjadi NULL.
            $category->delete();

            return (object) ['status' => true];
        } catch (Exception $e) {
            return (object) [
                'status' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /*
     * =================================================================================
     * METHOD STATIC/TOOLS
     * =================================================================================
     */
    public static function isNameUniqueInBusiness(string $name, int $businessId): bool
    {
        return !CustomerCategory::where('name', $name)
            ->where('business_id', $businessId)
            ->exists();
    }

    public static function isNameUniqueOnUpdate(string $name, int $businessId, int $ignoreId): bool
    {
        return !CustomerCategory::where('name', $name)
            ->where('business_id', $businessId)
            ->where('id', '!=', $ignoreId)
            ->exists();
    }

    public static function findBusinessId(int $categoryId): ?int
    {
        return CustomerCategory::find($categoryId)?->business_id;
    }
}
