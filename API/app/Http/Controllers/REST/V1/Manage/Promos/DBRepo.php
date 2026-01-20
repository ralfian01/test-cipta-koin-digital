<?php

namespace App\Http\Controllers\REST\V1\Manage\Promos;

use App\Http\Libraries\BaseDBRepo;
use App\Models\Outlet;
use App\Models\Promo;
use App\Models\ProductVariant;
use App\Models\Resource;
use App\Models\ProductCategory;
use App\Models\Package;
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

    /**
     * Fungsi utama untuk mengambil data promo berdasarkan filter.
     * @return object
     */
    public function getData()
    {
        try {
            $query = Promo::query()
                // Eager loading adalah KUNCI untuk mendapatkan semua data terkait promo
                ->with([
                    'business',
                    'outlets',      // Outlet tempat promo ini berlaku
                    'schedules',    // Jadwal harian promo
                    'conditions',   // Syarat-syarat pemicu promo
                    'rewards',
                    // 'rewards.product' // Item gratis beserta detail produknya
                ]);

            // Kasus 1: Mengambil satu promo spesifik berdasarkan ID
            if (isset($this->payload['id'])) {
                $data = $query->find($this->payload['id']);
                return (object) [
                    'status' => !is_null($data),
                    'data' => $data ? $data->toArray() : null
                ];
            }

            // Kasus 2: Mengambil daftar promo dengan filter
            $this->applyFilters($query);

            $perPage = $this->payload['per_page'] ?? 15;
            $data = $query->orderBy('id', 'desc')->paginate($perPage);

            return (object) [
                'status' => true,
                'data' => $data->toArray()
            ];
        } catch (Exception $e) {
            return (object) [
                'status' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Method pendukung untuk menerapkan filter pada query GET.
     * @param \Illuminate\Database\Eloquent\Builder $query
     */
    private function applyFilters(&$query)
    {
        if (isset($this->payload['business_id'])) {
            $query->where('business_id', $this->payload['business_id']);
        }
        if (isset($this->payload['outlet_id'])) {
            // Filter promo yang ter-assign ke outlet spesifik
            $query->whereHas('outlets', function ($q) {
                $q->where('outlets.id', $this->payload['outlet_id']);
            });
        }
        if (isset($this->payload['promo_type'])) {
            $query->where('promo_type', $this->payload['promo_type']);
        }
        if (isset($this->payload['is_active'])) {
            $query->where('is_active', $this->payload['is_active']);
        }
        if (isset($this->payload['keyword'])) {
            $query->where('name', 'LIKE', "%{$this->payload['keyword']}%");
        }
    }

    /**
     * Memvalidasi konsistensi business_id antara payload dan outlet_ids untuk promo.
     * @param array $payload
     * @return object {status: bool, message: string|null, business_id: int|null}
     */
    public static function validateAndDetermineBusinessId(array $payload): object
    {
        $businessId = $payload['business_id'] ?? null;
        $outletIds = $payload['outlet_ids'] ?? null;

        // Skenario 1: Hanya business_id
        if ($businessId && is_null($outletIds)) {
            return (object)['status' => true, 'message' => null, 'business_id' => (int)$businessId];
        }

        // Skenario 2: Hanya outlet_ids
        if (is_null($businessId) && !empty($outletIds)) {
            $uniqueBusinessIds = Outlet::whereIn('id', $outletIds)->pluck('business_id')->unique();
            if ($uniqueBusinessIds->count() > 1) {
                return (object)['status' => false, 'message' => 'outlet_ids belong to multiple business units.', 'business_id' => null];
            }
            if ($uniqueBusinessIds->isEmpty()) {
                return (object)['status' => false, 'message' => 'Invalid outlet_ids provided.', 'business_id' => null];
            }
            return (object)['status' => true, 'message' => null, 'business_id' => $uniqueBusinessIds->first()];
        }

        // Skenario 3: Keduanya diberikan
        if ($businessId && !empty($outletIds)) {
            $mismatchedCount = Outlet::whereIn('id', $outletIds)->where('business_id', '!=', $businessId)->count();
            if ($mismatchedCount > 0) {
                return (object)['status' => false, 'message' => 'outlet_ids do not match the provided business_id.', 'business_id' => null];
            }
            return (object)['status' => true, 'message' => null, 'business_id' => (int)$businessId];
        }

        // Skenario 4: Keduanya kosong
        return (object)['status' => false, 'message' => 'Either business_id or outlet_ids must be provided.', 'business_id' => null];
    }

    public function insertData()
    {
        try {
            return DB::transaction(function () {
                // 1. Buat Promo utama
                $promoPayload = Arr::only($this->payload, [
                    'business_id',
                    'name',
                    'description',
                    'condition_logic',
                    'start_date',
                    'end_date',
                    'is_active',
                    'is_cumulative'
                ]);
                $promo = Promo::create($promoPayload);

                // 2. Buat data relasional
                if (isset($this->payload['conditions'])) $promo->conditions()->createMany($this->payload['conditions']);
                if (isset($this->payload['rewards'])) $promo->rewards()->createMany($this->payload['rewards']);
                if (isset($this->payload['schedules'])) $promo->schedules()->createMany($this->payload['schedules']);

                // 3. Assign promo ke outlet jika outlet_ids diberikan
                if (!empty($this->payload['outlet_ids'])) {
                    $promo->outlets()->sync($this->payload['outlet_ids']);
                }

                return (object)['status' => true, 'data' => (object)['id' => $promo->id]];
            });
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }

    public function updateData()
    {
        try {
            return DB::transaction(function () {
                $promo = Promo::findOrFail($this->payload['id']);

                // Pastikan business_id tidak di-update
                $promoPayload = Arr::except($this->payload, [
                    'id',
                    'business_id',
                    'conditions',
                    'rewards',
                    'schedules',
                    'outlet_ids'
                ]);

                if (!empty($promoPayload)) {
                    $promo->update($promoPayload);
                }

                // Sinkronisasi data relasional (jika ada di payload)
                if (array_key_exists('conditions', $this->payload)) {
                    $promo->conditions()->delete();
                    $promo->conditions()->createMany($this->payload['conditions']);
                }
                if (array_key_exists('rewards', $this->payload)) {
                    $promo->rewards()->delete();
                    $promo->rewards()->createMany($this->payload['rewards']);
                }
                if (array_key_exists('schedules', $this->payload)) {
                    $promo->schedules()->delete();
                    $promo->schedules()->createMany($this->payload['schedules']);
                }
                if (array_key_exists('outlet_ids', $this->payload)) {
                    $promo->outlets()->sync($this->payload['outlet_ids']);
                }

                return (object)['status' => true];
            });
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }


    // D. METHOD UNTUK DELETE
    public function deleteData()
    {
        try {
            $promo = Promo::findOrFail($this->payload['id']);
            $promo->delete();
            return (object)['status' => true];
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }

    /*
     * =================================================================================
     * METHOD STATIC/TOOLS
     * =================================================================================
     */

    /**
     * Memvalidasi aturan bisnis untuk diskon moneter di dalam array rewards.
     * Aturan: Hanya boleh ada maksimal satu reward bertipe DISCOUNT_*.
     * @param array $rewards
     * @return bool
     */
    public static function validateDiscountRules(array $rewards): bool
    {
        // Gunakan Collection untuk mempermudah
        $rewardsCollection = collect($rewards);

        // Hitung berapa banyak item di array rewards yang tipenya adalah diskon
        $discountCount = $rewardsCollection->whereIn('reward_type', [
            'DISCOUNT_PERCENTAGE',
            'DISCOUNT_FIXED'
        ])->count();

        // Validasi berhasil jika jumlah diskon adalah 0 atau 1.
        // Gagal jika jumlahnya 2 atau lebih.
        return $discountCount <= 1;
    }

    public static function findBusinessId(int $promoId): ?int
    {
        return Promo::find($promoId)?->business_id;
    }

    public static function validateOutletConsistency(int $promoBusinessId, array $outletIds): bool
    {
        if (empty($outletIds)) return true;
        $mismatchedCount = Outlet::whereIn('id', $outletIds)
            ->where('business_id', '!=', $promoBusinessId)
            ->count();
        return $mismatchedCount === 0;
    }


    /**
     * Memvalidasi bahwa 'target_id' di dalam setiap kondisi adalah valid dan ada.
     * @param array $conditions
     * @return bool
     */
    public static function validateConditions(array $conditions): bool
    {
        foreach ($conditions as $condition) {
            // Jika tipe tidak ada, lewati (akan gagal di payloadRules)
            if (!isset($condition['condition_type'])) continue;

            $exists = false;
            $targetId = $condition['target_id'] ?? null;

            switch ($condition['condition_type']) {
                case 'TOTAL_PURCHASE':
                    // Tipe ini tidak memiliki target_id, jadi selalu dianggap valid di sini.
                    // Validasi 'min_value' sudah ditangani oleh payloadRules.
                    $exists = true;
                    break;

                case 'PRODUCT_VARIANT':
                    // Cek apakah variant_id yang diberikan ada di tabel product_variants.
                    if ($targetId) {
                        $exists = ProductVariant::where('variant_id', $targetId)->exists();
                    }
                    break;

                case 'PRODUCT_CATEGORY':
                    // Cek apakah category_id yang diberikan ada di tabel product_categories.
                    if ($targetId) {
                        $exists = ProductCategory::where('id', $targetId)->exists();
                    }
                    break;

                case 'PACKAGE':
                    // Cek apakah package_id yang diberikan ada di tabel packages.
                    if ($targetId) {
                        $exists = Package::where('id', $targetId)->exists();
                    }
                    break;
            }

            // Jika setelah pengecekan hasilnya false, berarti ada target_id yang tidak valid.
            if (!$exists) {
                return false; // Gagal cepat
            }
        }

        return true; // Semua kondisi valid
    }

    /**
     * Method baru untuk memvalidasi 'target_id' di dalam rewards.
     */
    public static function validateRewards(array $rewards): bool
    {
        foreach ($rewards as $reward) {
            if (!in_array($reward['reward_type'], ['FREE_VARIANT', 'FREE_RESOURCE'])) continue;

            $exists = false;
            $targetId = $reward['target_id'] ?? null;
            if (!$targetId) return false;

            if ($reward['reward_type'] === 'FREE_VARIANT') {
                $exists = ProductVariant::where('variant_id', $targetId)->exists();
            } elseif ($reward['reward_type'] === 'FREE_RESOURCE') {
                $exists = Resource::where('resource_id', $targetId)->exists();
            }

            if (!$exists) return false;
        }
        return true;
    }
}
