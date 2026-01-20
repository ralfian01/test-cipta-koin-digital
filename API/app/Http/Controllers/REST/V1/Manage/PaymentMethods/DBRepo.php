<?php

namespace App\Http\Controllers\REST\V1\Manage\PaymentMethods;

use App\Http\Libraries\BaseDBRepo;
use App\Models\PaymentMethod;
use Exception;
use Illuminate\Support\Facades\DB;

class DBRepo extends BaseDBRepo
{
    /*
     * =================================================================================
     * METHOD UNTUK MENGAMBIL DATA (GET)
     * =================================================================================
     */

    /**
     * Fungsi utama untuk mengambil data metode pembayaran berdasarkan filter.
     * @return object
     */
    public function getData()
    {
        try {
            $query = PaymentMethod::query();

            // Kasus 1: Mengambil satu data spesifik berdasarkan ID
            if (isset($this->payload['id'])) {
                $data = $query->find($this->payload['id']);
                return (object) [
                    'status' => !is_null($data),
                    'data' => $data ? $data->toArray() : null,
                ];
            }

            // Kasus 2: Mengambil daftar data dengan filter dan paginasi
            $this->applyFilters($query);

            $perPage = $this->payload['per_page'] ?? 15;
            $data = $query->paginate($perPage);

            return (object) [
                'status' => true,
                'data' => $data->toArray(),
            ];
        } catch (Exception $e) {
            return (object) [
                'status' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Method pendukung untuk menerapkan filter pada query GET.
     * @param \Illuminate\Database\Eloquent\Builder $query
     */
    private function applyFilters(&$query)
    {
        if (isset($this->payload['type'])) {
            $query->where('type', $this->payload['type']);
        }

        if (isset($this->payload['is_active'])) {
            $query->where('is_active', $this->payload['is_active']);
        }

        if (isset($this->payload['keyword'])) {
            $query->where('name', 'LIKE', "%{$this->payload['keyword']}%");
        }
    }


    /*
     * =================================================================================
     * METHOD UNTUK MENAMBAH DATA (INSERT)
     * =================================================================================
     */

    /**
     * Fungsi utama untuk memasukkan data metode pembayaran baru.
     * @return object
     */
    public function insertData()
    {
        try {
            return DB::transaction(function () {
                $paymentMethod = PaymentMethod::create($this->payload);

                if (!$paymentMethod) {
                    throw new Exception("Failed to create a new payment method.");
                }

                return (object) [
                    'status' => true,
                    'data' => (object) ['id' => $paymentMethod->id]
                ];
            });
        } catch (Exception $e) {
            return (object) [
                'status' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Function to insert a new unit into the database.
     * @return object
     */
    public function deleteData()
    {
        try {
            $paymentMethodId = $this->payload['id'];
            $unit = PaymentMethod::findOrFail($paymentMethodId);

            $unit->delete();

            return (object) ['status' => true];
        } catch (Exception $e) {
            return (object) [
                'status' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
