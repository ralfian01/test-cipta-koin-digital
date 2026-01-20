<?php

namespace App\Http\Controllers\REST\V1\Manage\Products\Outlet;

use App\Http\Libraries\BaseDBRepo;
use App\Models\Outlet;
use App\Models\Product;
use Exception;
use Illuminate\Support\Facades\DB;

class DBRepo extends BaseDBRepo
{

    /**
     * Memvalidasi bahwa semua outlet yang diberikan memiliki business_id yang sama dengan produk.
     * @param int $productId
     * @param array $outletIds
     * @return bool
     */
    public static function checkBusinessIdConsistency(int $productId, array $outletIds): bool
    {
        // Jika array kosong, tidak ada yang perlu divalidasi
        if (empty($outletIds)) {
            return true;
        }

        $product = Product::find($productId);
        // Jika produk tidak ditemukan (meskipun seharusnya tidak terjadi karena payloadRules), anggap tidak valid
        if (!$product) {
            return false;
        }
        $productBusinessId = $product->business_id;

        // Hitung outlet yang tidak cocok
        $mismatchedOutletsCount = Outlet::whereIn('id', $outletIds)
            ->where('business_id', '!=', $productBusinessId)
            ->count();

        // Valid jika tidak ada outlet yang tidak cocok (count === 0)
        return $mismatchedOutletsCount === 0;
    }

    /*
     * =================================================================================
     * METHOD UNTUK /products/outlet
     * =================================================================================
     */

    /**
     * Mengambil daftar produk yang ter-assign ke outlet tertentu.
     * @return object
     */
    public function getProductsByOutlet()
    {
        try {
            // 1. Cari outlet yang diminta berdasarkan ID dari payload.
            $outlet = Outlet::findOrFail($this->payload['outlet_id']);

            $perPage = $this->payload['per_page'] ?? 15;

            // 2. Gunakan relasi 'products()' yang sudah kita definisikan di model Outlet
            //    untuk mengambil semua produk yang terhubung ke outlet ini.
            $products = $outlet->products()
                // ->with('category') // Eager load kategori untuk info tambahan
                ->paginate($perPage);

            return (object)['status' => true, 'data' => $products->toArray()];
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }


    /**
     * Menyinkronkan daftar outlet untuk sebuah produk.
     * @return object
     */
    public function syncProductOutlets()
    {
        try {
            return DB::transaction(function () {
                $product = Product::findOrFail($this->payload['product_id']);

                // KUNCI LOGIKA:
                // sync() akan secara otomatis menambah, menghapus, atau membiarkan
                // relasi di tabel pivot 'outlet_product' agar cocok dengan array yang diberikan.
                // Jika array `outlet_ids` kosong, semua relasi akan dihapus, sesuai permintaan.
                $product->outlets()->sync($this->payload['outlet_ids']);

                return (object)['status' => true];
            });
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }
}
