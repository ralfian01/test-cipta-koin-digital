<?php

namespace App\Http\Controllers\REST\V1\Manage\Promos\Outlet;

use App\Http\Libraries\BaseDBRepo;
use App\Models\Outlet;
use App\Models\Promo;
use Exception;
use Illuminate\Support\Facades\DB;

class DBRepo extends BaseDBRepo
{
    /**
     * Mengambil daftar promo yang ter-assign ke outlet tertentu.
     */
    public function getPromosByOutlet()
    {
        try {
            $outlet = Outlet::findOrFail($this->payload['outlet_id']);
            $perPage = $this->payload['per_page'] ?? 15;
            $promos = $outlet->promos()->with('business')->paginate($perPage);
            return (object)['status' => true, 'data' => $promos->toArray()];
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Menyinkronkan daftar outlet untuk sebuah promo.
     */
    public function syncPromoOutlets()
    {
        try {
            return DB::transaction(function () {
                $promo = Promo::findOrFail($this->payload['promo_id']);
                $promo->outlets()->sync($this->payload['outlet_ids']);
                return (object)['status' => true];
            });
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Memvalidasi bahwa semua outlet memiliki business_id yang sama dengan promo.
     */
    public static function checkBusinessIdConsistency(int $promoId, array $outletIds): bool
    {
        if (empty($outletIds)) return true;
        $promo = Promo::find($promoId);
        if (!$promo) return false;
        $promoBusinessId = $promo->business_id;
        $mismatchedCount = Outlet::whereIn('id', $outletIds)->where('business_id', '!=', $promoBusinessId)->count();
        return $mismatchedCount === 0;
    }
}
