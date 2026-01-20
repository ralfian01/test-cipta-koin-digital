<?php

namespace App\Http\Controllers\REST\V1\Manage\Cooperation\Settings\SavingsMapping;

use App\Http\Libraries\BaseDBRepo;
use App\Models\CooperationSavingsTypeMapping;
use App\Models\AccountChart;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class DBRepo extends BaseDBRepo
{
    public function getMappings()
    {
        try {
            $data = CooperationSavingsTypeMapping::query()
                ->with([
                    'savingsType:id,name,code',
                    'savingsAccount:id,account_code,account_name',
                    'cashAccount:id,account_code,account_name'
                ]);

            if (isset($this->payload['business_id'])) {
                $data->where('business_id', $this->payload['business_id']);
            }

            if (isset($this->payload['code'])) {
                $data->whereHas('savingsType', function ($query) {
                    return $query->where('code', $this->payload['code']);
                });
            }

            $data = $data->get();

            return (object)['status' => true, 'data' => $data->toArray()];
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }

    public function updateMappings()
    {
        try {
            return DB::transaction(function () {
                $businessId = $this->payload['business_id'];
                $mappings = $this->payload['mappings'];

                // Hapus semua mapping lama untuk unit bisnis ini
                CooperationSavingsTypeMapping::where('business_id', $businessId)->delete();

                // Buat kembali semua mapping dari payload
                $dataToInsert = [];
                foreach ($mappings as $rule) {
                    $dataToInsert[] = array_merge($rule, ['business_id' => $businessId]);
                }

                if (!empty($dataToInsert)) {
                    CooperationSavingsTypeMapping::insert($dataToInsert);
                }

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
    public static function validateMappingPayload(array $mappings, int $businessId): array
    {
        foreach ($mappings as $index => $rule) {
            $savingsAccount = AccountChart::with('category')->find($rule['savings_account_id']);
            $cashAccount = AccountChart::with('category')->find($rule['cash_account_id']);

            // Cek 1: Semua akun harus milik business_id yang sama
            if ($savingsAccount->business_id != $businessId || $cashAccount->business_id != $businessId) {
                return [false, "Validation failed at rule #" . ($index + 1) . ": All accounts must belong to the specified business unit."];
            }
            // // Cek 2: Akun simpanan harus bertipe Ekuitas
            // if ($savingsAccount->category->account_type !== 'EQUITY') {
            //     return [false, "Validation failed at rule #" . ($index + 1) . ": Savings Account '{$savingsAccount->account_name}' must be an EQUITY type account."];
            // }
            // Cek 3: Akun kas/bank harus merupakan akun kas ekuivalen
            if (!$cashAccount->category->is_cash_equivalent) {
                return [false, "Validation failed at rule #" . ($index + 1) . ": Cash Account '{$cashAccount->account_name}' must be a Cash or Bank type account."];
            }
        }
        return [true, 'Validation successful.'];
    }
}
