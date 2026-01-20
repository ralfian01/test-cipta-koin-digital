<?php

namespace App\Http\Controllers\REST\V1\Manage\Finance\Consolidated\ChartOfAccounts;

use App\Http\Libraries\BaseDBRepo;
use App\Models\AccountChart;
use Exception;
use Illuminate\Support\Facades\DB;

class DBRepo extends BaseDBRepo
{
    public function getConsolidatedData()
    {
        try {
            $allAccounts = AccountChart::with('business:id,name')
                ->orderBy('account_code', 'asc')
                ->get();

            $groupedByCode = $allAccounts->groupBy('account_code');

            $consolidatedList = [];

            foreach ($groupedByCode as $code => $accountsInGroup) {
                $uniqueAccountsByName = $accountsInGroup->unique('account_name');

                // Cek apakah ada konflik nama untuk kode ini
                $hasConflict = $uniqueAccountsByName->count() > 1;

                // --- LOGIKA BARU DI SINI ---
                // Iterasi setiap nama unik yang ditemukan untuk kode ini
                foreach ($uniqueAccountsByName as $uniqueAccount) {

                    // Temukan semua bisnis yang menggunakan kombinasi kode & nama ini
                    $businessesUsingThis = $accountsInGroup
                        ->where('account_name', $uniqueAccount->account_name)
                        ->map(function ($account) {
                            return [
                                'business_id' => $account->business_id,
                                'business_name' => $account->business->name,
                            ];
                        });

                    $consolidatedList[] = [
                        'account_code' => $code,
                        'account_name' => $uniqueAccount->account_name,
                        'has_conflict' => $hasConflict,
                        'account_chart_category_id' => $uniqueAccount->account_chart_category_id,
                        'businesses' => $businessesUsingThis->values()->toArray(),
                    ];
                }
                // ---------------------------
            }

            return (object)['status' => true, 'data' => $consolidatedList];
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }
}
