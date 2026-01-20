<?php

namespace App\Http\Controllers\REST\V1\Manage\Packages\Outlet;

use App\Http\Libraries\BaseDBRepo;
use App\Models\Outlet;
use App\Models\Package;
use Exception;
use Illuminate\Support\Facades\DB;

class DBRepo extends BaseDBRepo
{

    /**
     * Memvalidasi bahwa semua outlet yang diberikan memiliki business_id yang sama dengan paket.
     * @param int $packageId
     * @param array $outletIds
     * @return bool
     */
    public static function checkBusinessIdConsistency(int $packageId, array $outletIds): bool
    {
        if (empty($outletIds)) {
            return true;
        }

        $package = Package::find($packageId);
        if (!$package) {
            return false;
        }
        $packageBusinessId = $package->business_id;

        $mismatchedOutletsCount = Outlet::whereIn('id', $outletIds)
            ->where('business_id', '!=', $packageBusinessId)
            ->count();

        return $mismatchedOutletsCount === 0;
    }

    /**
     * Mengambil daftar paket yang ter-assign ke outlet tertentu.
     * @return object
     */
    public function getPackagesByOutlet()
    {
        try {
            $outlet = Outlet::findOrFail($this->payload['outlet_id']);
            $perPage = $this->payload['per_page'] ?? 15;

            // Ambil paket yang terhubung dengan outlet ini dengan paginasi
            // Eager load relasi penting untuk ditampilkan di daftar
            $packages = $outlet->packages()
                // ->with(['business' => fn($q) => $q->select('id', 'name')])
                ->paginate($perPage);

            return (object)['status' => true, 'data' => $packages->toArray()];
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Menyinkronkan daftar outlet untuk sebuah paket.
     * @return object
     */
    public function syncPackageOutlets()
    {
        try {
            return DB::transaction(function () {
                $package = Package::findOrFail($this->payload['package_id']);

                // Method sync() akan menangani penambahan/penghapusan relasi di tabel pivot
                $package->outlets()->sync($this->payload['outlet_ids']);

                return (object)['status' => true];
            });
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }
}
