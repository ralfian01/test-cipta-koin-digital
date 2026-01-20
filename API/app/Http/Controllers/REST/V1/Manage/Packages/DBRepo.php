<?php

namespace App\Http\Controllers\REST\V1\Manage\Packages;

use App\Http\Libraries\BaseDBRepo;
use App\Models\Outlet;
use App\Models\Package;
use App\Models\ProductVariant;
use App\Models\Resource;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class DBRepo extends BaseDBRepo
{
    public function getData()
    {
        try {
            $query = Package::query()
                ->with([
                    'business' => fn($q) => $q->select('id', 'name'),
                    'pricing.customerCategory',
                    'outlets',
                    'items.variant.product',
                    'items.resource.product',
                    'items.unit'
                ]);

            if (isset($this->payload['id'])) {
                $data = $query->find($this->payload['id']);
                return (object)['status' => !is_null($data), 'data' => $data ? $data->toArray() : null];
            }
            if (isset($this->payload['business_id'])) {
                $query->where('business_id', $this->payload['business_id']);
            }
            if (isset($this->payload['keyword'])) {
                $query->where(fn($q) => $q->where('name', 'LIKE', "%{$this->payload['keyword']}%")->orWhere('sku', 'LIKE', "%{$this->payload['keyword']}%"));
            }
            $perPage = $this->payload['per_page'] ?? 15;
            $data = $query->paginate($perPage);
            return (object)['status' => true, 'data' => $data->toArray()];
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }

    public static function isSkuUniqueInBusiness(?string $sku, int $businessId): bool
    {
        if (is_null($sku)) return true;
        return !Package::where('sku', $sku)
            ->where('business_id', $businessId)
            ->exists();
    }


    public function insertData()
    {
        try {
            return DB::transaction(function () {
                // 1. Buat Paket utama
                $packagePayload = Arr::only($this->payload, ['business_id', 'name', 'sku', 'is_active']);
                $package = Package::create($packagePayload);
                if (!$package) {
                    throw new Exception("Failed to create package.");
                }

                // 2. Buat data relasional
                $package->items()->createMany($this->payload['items']);
                $package->pricing()->createMany($this->payload['pricing']);

                // 3. Assign paket ke outlet jika outlet_ids diberikan
                if (!empty($this->payload['outlet_ids'])) {
                    $package->outlets()->sync($this->payload['outlet_ids']);
                }

                return (object)['status' => true, 'data' => (object)['id' => $package->id]];
            });
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }


    public function updateData()
    {
        try {
            return DB::transaction(function () {
                $package = Package::findOrFail($this->payload['id']);

                // Aturan #1: Pastikan business_id tidak di-update
                $packagePayload = Arr::except($this->payload, ['id', 'business_id', 'items', 'pricing', 'outlet_ids']);

                if (!empty($packagePayload)) {
                    $package->update($packagePayload);
                }

                // Sinkronisasi data relasional (jika ada di payload)
                if (array_key_exists('items', $this->payload)) {
                    $package->items()->delete();
                    $package->items()->createMany($this->payload['items']);
                }
                if (array_key_exists('pricing', $this->payload)) {
                    $package->pricing()->delete();
                    $package->pricing()->createMany($this->payload['pricing']);
                }
                if (array_key_exists('outlet_ids', $this->payload)) {
                    $package->outlets()->sync($this->payload['outlet_ids']);
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
            $package = Package::findOrFail($this->payload['id']);
            $package->delete();
            return (object)['status' => true];
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Memvalidasi konsistensi business_id antara payload dan outlet_ids untuk paket.
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


    public static function findBusinessId(int $packageId): ?int
    {
        return Package::find($packageId)?->business_id;
    }

    public static function isSkuUniqueOnUpdate(?string $sku, int $businessId, int $ignoreId): bool
    {
        if (is_null($sku)) return true;
        return !Package::where('sku', $sku)
            ->where('business_id', $businessId)
            ->where('id', '!=', $ignoreId)
            ->exists();
    }

    public static function validateOutletConsistency(int $packageBusinessId, array $outletIds): bool
    {
        if (empty($outletIds)) return true;
        $mismatchedCount = Outlet::whereIn('id', $outletIds)
            ->where('business_id', '!=', $packageBusinessId)
            ->count();
        return $mismatchedCount === 0;
    }

    public static function validatePackageItems(array $items): bool
    {
        foreach ($items as $item) {
            if (!isset($item['item_type']) || !isset($item['item_id'])) continue;
            $exists = false;
            if ($item['item_type'] === 'VARIANT') {
                $exists = ProductVariant::where('variant_id', $item['item_id'])->exists();
            } elseif ($item['item_type'] === 'RESOURCE') {
                $exists = Resource::where('resource_id', $item['item_id'])->exists();
            }
            if (!$exists) return false;
        }
        return true;
    }
}
