<?php

namespace App\Http\Controllers\REST\V1\Manage\Finance\Settings\AccountMapping;

use App\Http\Libraries\BaseDBRepo;
use App\Models\AccountChart;
use App\Models\BusinessFinanceSetting;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class DBRepo extends BaseDBRepo
{
    /**
     * Mengambil pengaturan keuangan untuk satu unit bisnis.
     * @return object
     */
    public function getSettings()
    {
        try {
            $settings = BusinessFinanceSetting::where('business_id', $this->payload['business_id'])
                ->with('defaultCashAccount:id,account_code,account_name') // Eager load info akun kas
                ->with('defaultArAccount:id,account_code,account_name') // Eager load info akun kas
                ->with('defaultApAccount:id,account_code,account_name') // Eager load info akun kas
                ->first();

            return (object)['status' => true, 'data' => $settings ? $settings->toArray() : null];
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Memperbarui atau membuat pengaturan keuangan untuk satu unit bisnis.
     * @return object
     */
    public function updateSettings()
    {
        try {
            return DB::transaction(function () {
                $businessId = $this->payload['business_id'];
                $dbPayload = Arr::except($this->payload, ['business_id']);

                BusinessFinanceSetting::updateOrCreate(
                    ['business_id' => $businessId],
                    $dbPayload
                );

                return (object)['status' => true];
            });
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }

     /*
     * =================================================================================
     * METHOD STATIS UNTUK VALIDASI
     * =================================================================================
     */

    /**
     * Memeriksa apakah sebuah akun valid dan milik unit bisnis yang benar.
     * @param int $accountId
     * @param int $businessId
     * @return bool
     */
    public static function isAccountValidForBusiness(int $accountId, int $businessId): bool
    {
        $account = AccountChart::find($accountId);

        // Akun dianggap valid jika ada DAN business_id-nya cocok.
        return $account && $account->business_id == $businessId;
    }

    /**
     * Memeriksa apakah Akun Kas Default sudah diatur untuk sebuah unit bisnis.
     * @param int $businessId
     * @return bool
     */
    public static function isDefaultCashAccountSet(int $businessId): bool
    {
        $settings = BusinessFinanceSetting::where('business_id', $businessId)->first();

        // Pengaturan dianggap valid jika record-nya ada DAN
        // default_cash_account_id tidak null.
        return $settings && !is_null($settings->default_cash_account_id);
    }

    public static function isDefaultARAccountSet(int $businessId): bool
    {
        $settings = BusinessFinanceSetting::where('business_id', $businessId)->first();

        // Pengaturan dianggap valid jika record-nya ada DAN
        // default_ar_account_id tidak null.
        return $settings && !is_null($settings->default_ar_account_id);
    }

    public static function isDefaultAPAccountSet(int $businessId): bool
    {
        $settings = BusinessFinanceSetting::where('business_id', $businessId)->first();

        // Pengaturan dianggap valid jika record-nya ada DAN
        // default_ap_account_id tidak null.
        return $settings && !is_null($settings->default_ap_account_id);
    }

    /**
     * Memeriksa apakah sekumpulan ID akun valid dan semuanya milik unit bisnis yang benar.
     * @param array $accountIds Array berisi ID akun yang mungkin null.
     * @param int $businessId
     * @return bool
     */
    public static function areAccountsValidForBusiness(array $accountIds, int $businessId): bool
    {
        // 1. Filter semua nilai null atau kosong dari array
        $validAccountIds = array_filter($accountIds);

        // 2. Jika tidak ada ID yang perlu dicek, lewati (valid)
        if (empty($validAccountIds)) {
            return true;
        }

        // 3. Hitung berapa banyak akun yang cocok dengan business_id
        $count = AccountChart::whereIn('id', $validAccountIds)
            ->where('business_id', $businessId)
            ->count();

        // 4. Validasi berhasil jika jumlah akun yang cocok sama dengan
        //    jumlah ID unik yang kita periksa.
        return $count === count(array_unique($validAccountIds));
    }
}
