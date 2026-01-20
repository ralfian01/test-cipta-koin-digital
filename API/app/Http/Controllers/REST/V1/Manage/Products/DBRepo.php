<?php

namespace App\Http\Controllers\REST\V1\Manage\Products;

use App\Http\Libraries\BaseDBRepo;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Unit;
use Exception;
use Illuminate\Support\Facades\DB;

// Semua model yang digunakan didefinisikan di sini untuk kejelasan
use App\Models\ProductVariant;
use App\Models\Resource;
use Arr;

class DBRepo extends BaseDBRepo
{
    /**
     * Fungsi utama untuk mengambil data produk beserta semua detail relasionalnya.
     * @return object
     */
    public function getData()
    {
        try {
            // -- KUNCI PERUBAHAN: Query Eager Loading yang sangat detail --
            $query = Product::query()
                ->with([
                    // Relasi dasar
                    'business' => fn($q) => $q->select('id', 'name'),
                    'category' => fn($q) => $q->select('id', 'name'),
                    'outlets' => fn($q) => $q->select('outlets.id', 'outlets.name'),

                    // Relasi untuk produk KONSUMSI
                    'variants.pricing.customerCategory' => fn($q) => $q->select('id', 'name'),

                    // Relasi untuk produk SEWA
                    'resources.availability',
                    'resources.pricing.customerCategory' => fn($q) => $q->select('id', 'name'),
                    'resources.pricing.unit' => fn($q) => $q->select('unit_id', 'name'),
                ]);

            // Kasus 1: Mengambil satu produk spesifik berdasarkan ID
            if (isset($this->payload['id'])) {
                $data = $query->find($this->payload['id']);
                return (object) ['status' => !is_null($data), 'data' => $data ? $data->toArray() : null];
            }

            // Kasus 2: Mengambil daftar produk dengan filter
            $this->applyFilters($query);

            $perPage = $this->payload['per_page'] ?? 15;
            $data = $query->orderBy('product_id', 'desc')->paginate($perPage);

            return (object) ['status' => true, 'data' => $data->toArray()];
        } catch (Exception $e) {
            return (object) ['status' => false, 'message' => $e->getMessage()];
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
        if (isset($this->payload['category_id'])) {
            $query->where('category_id', $this->payload['category_id']);
        }
        if (isset($this->payload['product_type'])) {
            $query->where('product_type', $this->payload['product_type']);
        }
        if (isset($this->payload['keyword'])) {
            $query->where('name', 'LIKE', "%{$this->payload['keyword']}%");
        }
        if (isset($this->payload['outlet_id'])) {
            $query->whereHas('outlets', function ($q) {
                $q->where('outlets.id', $this->payload['outlet_id']);
            });
        }
    }

    /**
     * Memvalidasi bahwa semua outlet yang diberikan memiliki business_id yang sama
     * dengan business_id dari produk yang sudah ada.
     * @param int $productId
     * @param array $outletIds
     * @return bool
     */
    public static function validateOutletConsistency(int $productId, array $outletIds): bool
    {
        // Jika array kosong, tidak ada yang perlu divalidasi, anggap valid.
        if (empty($outletIds)) {
            return true;
        }

        // Ambil business_id dari produk yang ada di database.
        $product = Product::find($productId);
        if (!$product) {
            return false; // Seharusnya tidak terjadi karena ada 'exists' rule
        }
        $productBusinessId = $product->business_id;

        // Hitung jumlah outlet dari payload yang TIDAK memiliki business_id yang sama.
        $mismatchedCount = Outlet::whereIn('id', $outletIds)
            ->where('business_id', '!=', $productBusinessId)
            ->count();

        // Valid jika tidak ada outlet yang tidak cocok (count === 0).
        return $mismatchedCount === 0;
    }

    /**
     * Memvalidasi konsistensi business_id antara payload dan outlet_ids.
     * @param array $payload
     * @return object {status: bool, message: string|null, business_id: int|null}
     */
    public static function validateAndDetermineBusinessId(array $payload): object
    {
        $businessId = $payload['business_id'] ?? null;
        $outletIds = $payload['outlet_ids'] ?? null;

        // Skenario 1: Hanya business_id yang diberikan (validasi sudah ditangani oleh payloadRules)
        if ($businessId && is_null($outletIds)) {
            return (object)['status' => true, 'message' => null, 'business_id' => (int)$businessId];
        }

        // Skenario 2: Hanya outlet_ids yang diberikan
        if (is_null($businessId) && !empty($outletIds)) {
            $outlets = Outlet::whereIn('id', $outletIds)->get(['business_id']);
            // Ambil semua business_id yang unik dari outlet yang ditemukan
            $uniqueBusinessIds = $outlets->pluck('business_id')->unique();

            if ($uniqueBusinessIds->count() > 1) {
                return (object)['status' => false, 'message' => 'outlet_ids belong to multiple business units.', 'business_id' => null];
            }
            if ($uniqueBusinessIds->isEmpty()) {
                return (object)['status' => false, 'message' => 'Invalid outlet_ids provided.', 'business_id' => null];
            }
            // Sukses: simpulkan business_id dari outlet
            return (object)['status' => true, 'message' => null, 'business_id' => $uniqueBusinessIds->first()];
        }

        // Skenario 3: Keduanya diberikan
        if ($businessId && !empty($outletIds)) {
            $mismatchedCount = Outlet::whereIn('id', $outletIds)
                ->where('business_id', '!=', $businessId)
                ->count();
            if ($mismatchedCount > 0) {
                return (object)['status' => false, 'message' => 'outlet_ids do not match the provided business_id.', 'business_id' => null];
            }
            // Sukses: keduanya konsisten
            return (object)['status' => true, 'message' => null, 'business_id' => (int)$businessId];
        }

        // Skenario 4: Keduanya kosong (akan gagal di payloadRules 'required_without')
        // Tapi kita tangani untuk keamanan
        return (object)['status' => false, 'message' => 'Either business_id or outlet_ids must be provided.', 'business_id' => null];
    }

    public function insertData()
    {
        try {
            return DB::transaction(function () {
                // 1. Buat Produk utama
                $productPayload = Arr::only($this->payload, ['business_id', 'category_id', 'name', 'description', 'product_type']);
                $product = Product::create($productPayload);

                // 2. Buat data relasional (variants atau resources)
                if ($this->payload['product_type'] === 'CONSUMPTION') {
                    $this->createProductVariants($product, $this->payload['variants']);
                }
                if ($this->payload['product_type'] === 'RENTAL') {
                    $this->createProductResources($product, $this->payload['resources']);
                }

                // 3. Assign produk ke outlet jika outlet_ids diberikan
                if (!empty($this->payload['outlet_ids'])) {
                    $product->outlets()->sync($this->payload['outlet_ids']);
                }

                return (object)['status' => true, 'data' => (object)['product_id' => $product->product_id]];
            });
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }


    private function createProductVariants(Product $product, array $variantsData): void
    {
        if (empty($variantsData)) throw new Exception("Product variants data is required for a CONSUMPTION product.");

        foreach ($variantsData as $variantData) {
            $variant = $product->variants()->create(
                Arr::only($variantData, ['name', 'sku', 'stock_quantity'])
            );

            // --- KUNCI PERUBAHAN ---
            // Setelah varian dibuat, buat aturan harganya.
            if (!empty($variantData['pricing'])) {
                $this->createVariantPricing($variant, $variantData['pricing']);
            }
            // ------------------------
        }
    }

    private function createProductResources(Product $product, array $resourcesData): void
    {
        if (empty($resourcesData)) throw new Exception("Product resources data is required for a RENTAL product.");

        foreach ($resourcesData as $resourceData) {
            $resource = $product->resources()->create(
                Arr::only($resourceData, ['name'])
            );

            if (!empty($resourceData['availability'])) {
                $resource->availability()->createMany($resourceData['availability']);
            }

            // --- KUNCI PERUBAHAN ---
            // Setelah resource dibuat, buat aturan harganya.
            if (!empty($resourceData['pricing'])) {
                $this->createResourcePricing($resource, $resourceData['pricing']);
            }
            // ------------------------
        }
    }

    /**
     * Method baru untuk membuat harga yang terikat pada Varian.
     * @param ProductVariant $variant
     * @param array $pricingData
     */
    private function createVariantPricing(ProductVariant $variant, array $pricingData): void
    {
        foreach ($pricingData as $priceRule) {
            $variant->pricing()->create([
                'customer_category_id' => $priceRule['customer_category_id'],
                'price' => $priceRule['price'],
                // resource_id dan unit_id akan otomatis NULL
            ]);
        }
    }

    /**
     * Method baru untuk membuat harga yang terikat pada Resource.
     * @param Resource $resource
     * @param array $pricingData
     */
    private function createResourcePricing(Resource $resource, array $pricingData): void
    {
        foreach ($pricingData as $priceRule) {
            $resource->pricing()->create([
                'customer_category_id' => $priceRule['customer_category_id'],
                'unit_id' => $priceRule['unit_id'],
                'price' => $priceRule['price'],
                // variant_id akan otomatis NULL
            ]);
        }
    }

    /*
     * =================================================================================
     * METHOD STATIC/TOOLS (Digunakan oleh Controller lain)
     * =================================================================================
     */

    /**
     * Method pendukung (static) untuk memeriksa keberadaan unit ID.
     * Digunakan oleh `Insert.php` di `nextValidation()`.
     */
    public static function checkUnitsExist(array $unitIds): bool
    {
        if (empty($unitIds)) return false;
        // Gunakan array_unique untuk efisiensi jika ada ID duplikat di payload
        $uniqueUnitIds = array_unique($unitIds);
        $count = Unit::whereIn('unit_id', $uniqueUnitIds)->count();
        return $count === count($uniqueUnitIds);
    }


    /**
     * Fungsi utama untuk memperbarui data produk beserta relasinya.
     * @return object
     */
    public function updateData()
    {
        try {
            return DB::transaction(function () {
                $product = Product::findOrFail($this->payload['product_id']);

                // 1. Update data dasar produk
                $productPayload = Arr::only($this->payload, ['business_id', 'category_id', 'name', 'description']);
                if (!empty($productPayload)) {
                    $product->update($productPayload);
                }

                // 2. Sinkronkan data relasional (variants, resources)
                if ($product->product_type === 'CONSUMPTION' && isset($this->payload['variants'])) {
                    $this->syncProductVariants($product, $this->payload['variants']);
                }
                if ($product->product_type === 'RENTAL' && isset($this->payload['resources'])) {
                    $this->syncProductResources($product, $this->payload['resources']);
                }

                // --- PERUBAHAN KRUSIAL DI SINI ---
                // 3. Sinkronkan outlet jika outlet_ids ada di payload
                // Menggunakan array_key_exists agar array kosong [] juga diproses (untuk menghapus semua assignment)
                if (array_key_exists('outlet_ids', $this->payload)) {
                    $product->outlets()->sync($this->payload['outlet_ids']);
                }
                // ------------------------------------

                return (object)['status' => true];
            });
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Menyinkronkan varian: update yang ada, buat yang baru, hapus yang lama.
     */
    private function syncProductVariants(Product $product, array $variantsData): void
    {
        $incomingVariantIds = Arr::pluck(Arr::where($variantsData, fn($v) => isset($v['variant_id'])), 'variant_id');

        // Hapus varian yang tidak ada di payload
        $product->variants()->whereNotIn('variant_id', $incomingVariantIds)->delete();

        foreach ($variantsData as $variantData) {
            // Update atau Buat Varian
            $variant = $product->variants()->updateOrCreate(
                ['variant_id' => $variantData['variant_id'] ?? null],
                Arr::only($variantData, ['name', 'sku', 'stock_quantity'])
            );

            // Sinkronkan harga untuk varian ini
            if (isset($variantData['pricing'])) {
                $this->syncPricing($variant, $variantData['pricing']);
            }
        }
    }

    /**
     * Menyinkronkan resource: update yang ada, buat yang baru, hapus yang lama.
     */
    private function syncProductResources(Product $product, array $resourcesData): void
    {
        $incomingResourceIds = Arr::pluck(Arr::where($resourcesData, fn($r) => isset($r['resource_id'])), 'resource_id');

        // Hapus resource yang tidak ada di payload
        $product->resources()->whereNotIn('resource_id', $incomingResourceIds)->delete();

        foreach ($resourcesData as $resourceData) {
            // Update atau Buat Resource
            $resource = $product->resources()->updateOrCreate(
                ['resource_id' => $resourceData['resource_id'] ?? null],
                Arr::only($resourceData, ['name'])
            );

            // Sinkronkan availability
            if (isset($resourceData['availability'])) {
                $resource->availability()->delete(); // Hapus semua jadwal lama
                $resource->availability()->createMany($resourceData['availability']); // Buat yang baru
            }

            // Sinkronkan harga
            if (isset($resourceData['pricing'])) {
                $this->syncPricing(null, $resourceData['pricing'], $resource);
            }
        }
    }

    /**
     * Method generik untuk menyinkronkan harga.
     */
    private function syncPricing($variant = null, array $pricingData, $resource = null): void
    {
        $parent = $variant ?? $resource;
        $incomingPriceIds = Arr::pluck(Arr::where($pricingData, fn($p) => isset($p['price_id'])), 'price_id');

        // Hapus harga yang tidak ada di payload
        $parent->pricing()->whereNotIn('price_id', $incomingPriceIds)->delete();

        foreach ($pricingData as $priceRule) {
            // Tentukan konteks harga
            $context = [
                'variant_id' => $variant ? $parent->variant_id : null,
                'resource_id' => $resource ? $parent->resource_id : null,
            ];

            // Update atau Buat Harga
            $parent->pricing()->updateOrCreate(
                ['price_id' => $priceRule['price_id'] ?? null],
                array_merge(Arr::only($priceRule, ['customer_category_id', 'unit_id', 'price']), $context)
            );
        }
    }

    /**
     * Fungsi utama untuk menghapus data produk.
     * @return object
     */
    public function deleteData()
    {
        try {
            // 1. Ambil ID dari payload dan cari data produk
            $productId = $this->payload['product_id'];
            $product = Product::findOrFail($productId);

            // 2. Hapus record dari database
            // Berkat `onDelete('cascade')` di migrasi, semua data terkait seperti:
            // - product_variants
            // - resources (dan availability-nya)
            // - pricing
            // - relasi di outlet_product
            // akan dihapus secara otomatis oleh database.
            $product->delete();

            return (object) ['status' => true];
        } catch (Exception $e) {
            return (object) [
                'status' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
