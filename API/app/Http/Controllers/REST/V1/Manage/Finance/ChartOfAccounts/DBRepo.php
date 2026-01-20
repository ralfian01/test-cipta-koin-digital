<?php

namespace App\Http\Controllers\REST\V1\Manage\Finance\ChartOfAccounts;

use App\Http\Libraries\BaseDBRepo;
use App\Models\AccountCategoryMapping;
use App\Models\AccountChart;
use App\Models\AccountChartCategory;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class DBRepo extends BaseDBRepo
{
    public function getData()
    {
        try {
            $businessId = $this->payload['business_id'];

            if (isset($this->payload['pair_recommendation_for_account_id'])) {

                $sourceAccountId = $this->payload['pair_recommendation_for_account_id'];

                // 1. Dapatkan kategori dari akun sumber
                $sourceAccount = AccountChart::find($sourceAccountId);
                if (!$sourceAccount || !$sourceAccount->account_chart_category_id) {
                    // Jika akun sumber tidak ada atau tidak punya kategori, kembalikan array kosong
                    return (object)['status' => true, 'data' => []];
                }
                $sourceCategoryId = $sourceAccount->account_chart_category_id;

                // 2. Cari semua kategori yang direkomendasikan dari tabel mapping
                $recommendedCategoryIds = AccountCategoryMapping::where('business_id', $businessId)
                    ->where('source_category_id', $sourceCategoryId)
                    ->pluck('recommended_category_id');

                if ($recommendedCategoryIds->isEmpty()) {
                    return (object)['status' => true, 'data' => []];
                }

                // 3. Ambil semua akun posting yang termasuk dalam kategori yang direkomendasikan
                $recommendedAccounts = AccountChart::query()
                    ->where('business_id', $businessId)
                    ->whereIn('account_chart_category_id', $recommendedCategoryIds)
                    ->whereDoesntHave('children') // Hanya akun posting
                    ->orderBy('account_code', 'asc')
                    ->get();

                return (object)['status' => true, 'data' => $recommendedAccounts->toArray()];
            }

            $categoryIds = $this->payload['account_chart_category_ids'] ?? null;
            $includeNoCategory = filter_var($this->payload['include_no_category'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $from = $this->payload['from'] ?? null;
            $accountType = $this->payload['account_type'] ?? null;

            // 1. Inisialisasi query utama dengan filter wajib
            $query = AccountChart::query()
                ->where('business_id', $businessId)
                ->with('category'); // Selalu eager load kategori untuk info

            if (isset($categoryIds) || isset($accountType)) {
                // Terapkan filter utama secara kondisional
                $query->when($categoryIds, function ($q) use ($categoryIds, $includeNoCategory) {
                    $q->where(function ($subQ) use ($categoryIds, $includeNoCategory) {
                        $subQ->whereIn('account_chart_category_id', $categoryIds);
                        if ($includeNoCategory) {
                            $subQ->orWhereNull('account_chart_category_id');
                        }
                    });
                });

                $query->when($accountType, function ($q) use ($accountType) {
                    if ($accountType == 'HEAD') {
                        // HEAD: Akun yang memiliki parent_id NULL atau memiliki anak
                        $q->where(function ($subQ) {
                            $subQ->whereNull('parent_id')->orWhereHas('children');
                        });
                    } elseif ($accountType == 'POST') {
                        // POST: Akun yang tidak memiliki anak
                        $q->whereDoesntHave('children');
                    }
                });
            } else {

                // Tentukan bagaimana data akan diambil (Hierarki vs Datar)
                // Jika 'from' tidak ditentukan, kita asumsikan defaultnya adalah hierarki dari root
                if (is_null($from) || $from == 'ROOT') {
                    $query->whereNull('parent_id') // Mulai dari root
                        ->with('childrenRecursive'); // Ambil semua anak-anaknya
                } else if ($from == 'CHILD') {
                    $query->whereDoesntHave('children');
                }
            }

            // Urutkan hasil akhir
            $query->orderBy('account_code', 'asc');

            // 4. Eksekusi Query
            $data = $query->get();
            return (object)['status' => true, 'data' => $data->toArray()];
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }

    public function getCategory()
    {
        try {
            // Mengambil data secara hierarkis
            $query = AccountChartCategory::query();
            $data = $query->get();
            return (object)['status' => true, 'data' => $data->toArray()];
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }

    public function insertData()
    {
        try {
            $account = AccountChart::create($this->payload);
            return (object)['status' => true, 'data' => (object)['id' => $account->id]];
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }

    public function updateData()
    {
        try {
            $account = AccountChart::findOrFail($this->payload['id']);
            $dbPayload = Arr::except($this->payload, ['id']);
            $account->update($dbPayload);
            return (object)['status' => true];
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }

    public function deleteData()
    {
        try {
            $account = AccountChart::findOrFail($this->payload['id']);
            if ($account->journalEntryDetails()->exists()) {
                throw new Exception("Cannot delete account: It has been used in journal entries.");
            }
            $account->delete();
            return (object)['status' => true];
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }

    // Method ini akan dibutuhkan oleh Update.php
    public static function isCodeUniqueOnUpdate(string $accountCode, int $ignoreId): bool
    {
        $account = AccountChart::find($ignoreId);
        $businessId = $account?->business_id;

        return !AccountChart::where('account_code', $accountCode)
            ->where('business_id', $businessId)
            ->where('id', '!=', $ignoreId)
            ->exists();
    }

    public static function isCodeUniqueInBusiness(string $code, int $businessId): bool
    {
        return !AccountChart::where('account_code', $code)->where('business_id', $businessId)->exists();
    }

    public static function isChildInSameCategory(string $parentId, int $accountChartCategoryId): bool
    {
        return AccountChart::where('id', $parentId)
            ->where(function ($query) use ($accountChartCategoryId) {
                $query->where('account_chart_category_id', $accountChartCategoryId)
                    ->orWhereNull('account_chart_category_id');
            })
            ->exists();
    }
}
