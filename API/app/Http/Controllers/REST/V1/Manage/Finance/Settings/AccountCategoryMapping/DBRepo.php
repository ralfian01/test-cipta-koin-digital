<?php

namespace App\Http\Controllers\REST\V1\Manage\Finance\Settings\AccountCategoryMapping;

use App\Http\Libraries\BaseDBRepo;
use App\Models\AccountCategoryMapping;
use Exception;
use Illuminate\Support\Facades\DB;

class DBRepo extends BaseDBRepo
{
    public function getData()
    {
        try {
            $data = AccountCategoryMapping::query()
                ->where('business_id', $this->payload['business_id'])
                ->with(['sourceCategory:id,name', 'recommendedCategory:id,name']);

            if (isset($this->payload['source_category_id'])) {
                $data->where('source_category_id', $this->payload['source_category_id']);
            }

            $data = $data->get();

            return (object)['status' => true, 'data' => $data->toArray()];
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }

    public function insertData()
    {
        try {
            // Bungkus dalam transaksi untuk memastikan semua atau tidak sama sekali
            return DB::transaction(function () {
                $createdMappings = [];
                // Payload 'mappings' akan berisi array dari aturan
                foreach ($this->payload['mappings'] as $mappingRule) {
                    $createdMappings[] = AccountCategoryMapping::updateOrCreate(
                        // Kondisi untuk mencari (mencegah duplikasi)
                        [
                            'business_id' => $this->payload['business_id'],
                            'source_category_id' => $mappingRule['source_category_id'],
                            'recommended_category_id' => $mappingRule['recommended_category_id'],
                        ],
                        // Data (jika tidak ditemukan, kolom ini akan digunakan untuk create)
                        [
                            'business_id' => $this->payload['business_id'],
                            'source_category_id' => $mappingRule['source_category_id'],
                            'recommended_category_id' => $mappingRule['recommended_category_id'],
                        ]
                    );
                }
                return (object)['status' => true, 'data' => $createdMappings];
            });
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }

    public function deleteData()
    {
        try {
            $mapping = AccountCategoryMapping::findOrFail($this->payload['id']);
            // Tambahkan validasi otorisasi jika perlu (misal: cek business_id)
            $mapping->delete();
            return (object)['status' => true];
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
     * Memeriksa apakah satu atau lebih aturan mapping sudah ada di database.
     * Mengembalikan array yang berisi aturan yang sudah ada, atau array kosong jika semuanya unik.
     *
     * @param int $businessId
     * @param array $mappings
     * @return \Illuminate\Support\Collection
     */
    public static function findExistingMappings(int $businessId, array $mappings): \Illuminate\Support\Collection
    {
        if (empty($mappings)) {
            return collect();
        }

        // Buat query dasar
        $query = AccountCategoryMapping::query()
            ->where('business_id', $businessId);

        // Tambahkan klausa OR WHERE untuk setiap pasangan yang akan diperiksa
        $query->where(function ($q) use ($mappings) {
            foreach ($mappings as $rule) {
                $q->orWhere(function ($subQ) use ($rule) {
                    $subQ->where('source_category_id', $rule['source_category_id'])
                        ->where('recommended_category_id', $rule['recommended_category_id']);
                });
            }
        });

        // Eager load nama kategori untuk pesan error yang lebih informatif
        return $query->with(['sourceCategory:id,name', 'recommendedCategory:id,name'])->get();
    }
}
