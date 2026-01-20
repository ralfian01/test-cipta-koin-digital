<?php

namespace App\Http\Controllers\REST\V1\Manage\Finance\FixedAssets;

use App\Http\Libraries\BaseDBRepo;
use App\Models\FixedAsset;
use App\Models\DepreciationSetting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class DBRepo extends BaseDBRepo
{
    /*
     * =================================================================================
     * METHOD UTAMA
     * =================================================================================
     */

    public function getData()
    {
        try {
            // -- Query Eager Loading yang sama seperti sebelumnya --
            $query = FixedAsset::query()
                ->with([
                    'business:id,name',
                    'assetAccount:id,account_code,account_name',
                    'bill:id,bill_number',
                    'depreciationSetting',
                ]);

            // --- KUNCI PERBAIKAN: Menambahkan Agregasi ---
            // Kita akan menghitung SUM dari jadwal yang sudah di-posting
            $query->withSum([
                'depreciationSchedules as posted_depreciation_sum' => function ($q) {
                    $q->where('status', 'POSTED');
                }
            ], 'depreciation_amount');
            // ---------------------------------------------

            // Kasus 1: Mengambil satu aset spesifik
            if (isset($this->payload['id'])) {
                // Eager load semua relasi yang dibutuhkan
                $asset = FixedAsset::with([
                    'business:id,name',
                    'assetAccount:id,account_code,account_name',
                    'bill', // Ambil semua data bill
                    'depreciationSetting.expenseAccount:id,account_code,account_name',
                    'depreciationSetting.accumulatedDepreciationAccount:id,account_code,account_name',
                    // Ambil jadwal yang sudah di-posting beserta jurnalnya
                    'depreciationSchedules' => function ($query) {
                        $query->where('status', 'POSTED')->with('journalEntry:id,description')->orderBy('depreciation_date', 'asc');
                    }
                ])->findOrFail($this->payload['id']);

                // Panggil method helper untuk menambahkan data hasil perhitungan
                $calculatedData = $this->getCalculatedAssetValues($asset);

                // Panggil method helper untuk membangun histori transaksi
                $transactionHistory = $this->buildTransactionHistory($asset, $calculatedData);

                // Gabungkan semua data menjadi satu respons yang kaya
                $detailedAsset = array_merge(
                    $asset->toArray(),
                    $calculatedData,
                    ['transaction_history' => $transactionHistory]
                );

                return (object)['status' => true, 'data' => $detailedAsset];
            }


            // Kasus 2: Mengambil daftar aset
            $this->applyFilters($query);

            $perPage = $this->payload['per_page'] ?? 15;
            $assets = $query->orderBy('acquisition_date', 'desc')->paginate($perPage);

            // Tambahkan data hasil perhitungan ke setiap item dalam koleksi
            $assets->each(function ($asset) {
                $this->appendCalculatedValues($asset);
            });

            return (object)['status' => true, 'data' => $assets->toArray()];
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Method helper untuk menghitung nilai-nilai turunan (Nilai Buku, dll.).
     */
    private function getCalculatedAssetValues(FixedAsset $asset): array
    {
        $openingBalance = 0;
        if ($asset->depreciationSetting) {
            $openingBalance = (float) $asset->depreciationSetting->opening_balance_accumulated_depreciation;
        }

        // 'depreciation_schedules_sum_depreciation_amount' adalah hasil dari withSum yang akan kita tambahkan
        $postedDepreciation = (float) $asset->depreciationSchedules->sum('depreciation_amount');

        $totalAccumulatedDepreciation = $openingBalance + $postedDepreciation;
        $bookValue = (float) $asset->acquisition_cost - $totalAccumulatedDepreciation;

        return [
            'total_accumulated_depreciation' => $totalAccumulatedDepreciation,
            'book_value' => $bookValue,
        ];
    }

    /**
     * Method helper untuk membangun tabel histori transaksi.
     */
    private function buildTransactionHistory(FixedAsset $asset, array $calculatedData): array
    {
        $history = [];

        // 1. Tambahkan Transaksi Pembelian Awal
        $history[] = [
            'date' => $asset->acquisition_date,
            'reference' => 'Pendaftaran Aset Tetap',
            'journal_link' => null, // Anda bisa menambahkan link ke jurnal pembelian jika ada
            'debit' => (float) $asset->acquisition_cost,
            'credit' => 0,
            'balance' => (float) $asset->acquisition_cost, // Nilai buku awal adalah harga beli
        ];

        // 2. Tambahkan semua transaksi penyusutan yang sudah di-posting
        $runningBookValue = (float) $asset->acquisition_cost;
        foreach ($asset->depreciationSchedules as $schedule) {
            $runningBookValue -= (float) $schedule->depreciation_amount;
            $history[] = [
                'date' => $schedule->depreciation_date,
                'reference' => $schedule->journalEntry->description ?? "Depreciation on {$schedule->depreciation_date}",
                'journal_link' => $schedule->posted_journal_entry_id,
                'debit' => 0,
                'credit' => (float) $schedule->depreciation_amount,
                'balance' => $runningBookValue,
            ];
        }

        return $history;
    }

    /**
     * Method helper untuk menambahkan nilai-nilai yang dihitung (Nilai Buku, dll.).
     * @param FixedAsset $asset
     */
    private function appendCalculatedValues(FixedAsset $asset): void
    {
        $openingBalance = 0;
        $postedDepreciation = 0;

        if ($asset->depreciationSetting) {
            $openingBalance = (float) $asset->depreciationSetting->opening_balance_accumulated_depreciation;
        }

        // 'posted_depreciation_sum' adalah nama alias dari withSum
        $postedDepreciation = (float) $asset->posted_depreciation_sum ?? 0;

        $totalAccumulatedDepreciation = $openingBalance + $postedDepreciation;
        $bookValue = (float) $asset->acquisition_cost - $totalAccumulatedDepreciation;

        // Tambahkan sebagai atribut baru ke objek model
        $asset->total_accumulated_depreciation = $totalAccumulatedDepreciation;
        $asset->book_value = $bookValue;
    }

    /**
     * Method pendukung untuk menerapkan filter.
     */
    private function applyFilters(&$query)
    {
        if (isset($this->payload['business_id'])) {
            $query->where('business_id', $this->payload['business_id']);
        }
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
    }

    public function insertData()
    {
        try {
            return DB::transaction(function () {
                // 1. Buat Aset Utama
                $assetPayload = Arr::except($this->payload, ['depreciation_setting']);
                $asset = FixedAsset::create($assetPayload);

                // 2. Cek apakah ada data penyusutan, jika ya, proses
                if (!empty($this->payload['depreciation_setting'])) {
                    $this->saveSettingAndGenerateSchedules(
                        $asset,
                        $this->payload['depreciation_setting']
                    );
                }

                return (object)['status' => true, 'data' => (object)['id' => $asset->id]];
            });
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }



    public function updateData()
    {
        try {
            return DB::transaction(function () {
                $asset = FixedAsset::findOrFail($this->payload['id']);

                // 1. Update Aset Utama
                $assetPayload = Arr::except($this->payload, ['id', 'depreciation_setting']);
                $asset->update($assetPayload);

                // 2. Cek data penyusutan
                if (isset($this->payload['depreciation_setting'])) {
                    if (empty($this->payload['depreciation_setting'])) {
                        // Jika objek kosong dikirim, hapus pengaturan & jadwal
                        $asset->depreciationSetting()->delete(); // Cascade akan menghapus jadwal
                    } else {
                        // Jika ada data, simpan dan generate ulang jadwal
                        $this->saveSettingAndGenerateSchedules(
                            $asset,
                            $this->payload['depreciation_setting']
                        );
                    }
                }

                return (object)['status' => true];
            });
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Method helper terpusat untuk menyimpan pengaturan dan membuat jadwal.
     * @param FixedAsset $asset
     * @param array $settingData
     */
    private function saveSettingAndGenerateSchedules(FixedAsset $asset, array $settingData): void
    {
        // 1. Simpan Pengaturan (Update atau Create)
        $setting = $asset->depreciationSetting()->updateOrCreate(
            ['fixed_asset_id' => $asset->id],
            $settingData
        );

        // 2. Hapus Jadwal Lama (jika ada) dan Buat yang Baru
        $asset->depreciationSchedules()->delete();

        $depreciableCost = $asset->acquisition_cost - $setting->salvage_value;
        if ($depreciableCost > 0 && $setting->useful_life_in_months > 0) {
            $monthlyDepreciation = round($depreciableCost / $setting->useful_life_in_months, 2);

            $schedules = [];
            for ($i = 0; $i < $setting->useful_life_in_months; $i++) {
                $schedules[] = [
                    'depreciation_date' => Carbon::parse($setting->depreciation_start_date)->addMonths($i)->endOfMonth()->toDateString(),
                    'depreciation_amount' => $monthlyDepreciation,
                ];
            }
            if (!empty($schedules)) {
                $asset->depreciationSchedules()->createMany($schedules);
            }
        }
    }


    /**
     * Menghapus data aset tetap.
     * @return object
     */
    public function deleteData()
    {
        try {
            return DB::transaction(function () {
                $asset = FixedAsset::findOrFail($this->payload['id']);

                // Hapus record dari database.
                // Berkat onDelete('cascade'), ini akan secara otomatis menghapus:
                // - Record di 'depreciation_settings'
                // - SEMUA record di 'depreciation_schedules'
                $asset->delete();

                return (object) ['status' => true];
            });
        } catch (Exception $e) {
            return (object) [
                'status' => false,
                'message' => $e->getMessage(),
            ];
        }
    }


    /*
     * =================================================================================
     * METHOD STATIS UNTUK VALIDASI
     * =================================================================================
     */

    /**
     * Memeriksa apakah kode aset unik saat update.
     */
    public static function isCodeUniqueOnUpdate(string $assetCode, int $ignoreId): bool
    {
        // Ambil business_id dari aset yang sedang diedit
        $asset = FixedAsset::find($ignoreId);
        if (!$asset) return false; // Seharusnya tidak terjadi karena validasi 'exists'

        return !FixedAsset::where('business_id', $asset->business_id)
            ->where('asset_code', $assetCode)
            ->where('id', '!=', $ignoreId)
            ->exists();
    }

    /**
     * Memeriksa apakah sebuah aset bisa dihapus (belum memiliki jadwal penyusutan yang ter-posting).
     */
    public static function canAssetBeDeleted(int $assetId): bool
    {
        // Cari aset yang memiliki jadwal penyusutan dengan status 'POSTED'.
        $hasPostedSchedules = FixedAsset::where('id', $assetId)
            ->whereHas('depreciationSchedules', function ($query) {
                $query->where('status', 'POSTED');
            })
            ->exists();

        // Aset bisa dihapus jika TIDAK ditemukan jadwal yang sudah ter-posting.
        return !$hasPostedSchedules;
    }
}
