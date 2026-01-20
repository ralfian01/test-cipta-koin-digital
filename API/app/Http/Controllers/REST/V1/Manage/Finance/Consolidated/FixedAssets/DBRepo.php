<?php

namespace App\Http\Controllers\REST\V1\Manage\Finance\Consolidated\FixedAssets;

use App\Http\Libraries\BaseDBRepo;
use App\Models\FinanceContact;
use App\Models\FixedAsset;
use Exception;
use Illuminate\Support\Facades\DB;

class DBRepo extends BaseDBRepo
{
    public function getConsolidatedData()
    {
        try {
            // 1. Inisialisasi Query (TANPA filter business_id)
            $query = FixedAsset::query()
                ->with([
                    'business:id,name',
                    'assetAccount:id,account_code,account_name',
                    'depreciationSetting',
                ]);

            // 2. Tambahkan agregasi withSum untuk penyusutan yang sudah di-posting
            $query->withSum([
                'depreciationSchedules as posted_depreciation_sum' => function ($q) {
                    $q->where('status', 'POSTED');
                }
            ], 'depreciation_amount');

            // 3. Terapkan filter opsional
            if (isset($this->payload['status'])) {
                $query->where('status', $this->payload['status']);
            }
            if (isset($this->payload['keyword'])) {
                $keyword = $this->payload['keyword'];
                $query->where(function ($q) use ($keyword) {
                    $q->where('asset_name', 'LIKE', "%{$keyword}%")
                        ->orWhere('asset_code', 'LIKE', "%{$keyword}%");
                });
            }

            // 4. Paginasi dan eksekusi query
            $perPage = $this->payload['per_page'] ?? 15;
            $assets = $query->orderBy('business_id', 'asc')->orderBy('acquisition_date', 'desc')->paginate($perPage);

            // 5. Lakukan perhitungan nilai buku untuk setiap aset
            $assets->each(function ($asset) {
                $this->appendCalculatedValues($asset);
            });

            return (object)['status' => true, 'data' => $assets->toArray()];
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Method helper untuk menambahkan nilai-nilai yang dihitung (Nilai Buku, dll.).
     * (Sama persis seperti di DBRepo per-bisnis)
     */
    private function appendCalculatedValues(FixedAsset $asset): void
    {
        $openingBalance = 0;
        if ($asset->depreciationSetting) {
            $openingBalance = (float) $asset->depreciationSetting->opening_balance_accumulated_depreciation;
        }
        $postedDepreciation = (float) $asset->posted_depreciation_sum ?? 0;
        $totalAccumulatedDepreciation = $openingBalance + $postedDepreciation;
        $bookValue = (float) $asset->acquisition_cost - $totalAccumulatedDepreciation;

        $asset->total_accumulated_depreciation = $totalAccumulatedDepreciation;
        $asset->book_value = $bookValue;
    }
}
