<?php

namespace App\Http\Controllers\REST\V1\Manage\PosTransactions;

use App\Http\Libraries\BaseDBRepo;
use App\Models\PosTransaction;
use Exception;

class DBRepo extends BaseDBRepo
{
    /**
     * Mengambil data transaksi POS dengan filter dan relasi.
     * @return object
     */
    public function getData()
    {
        try {
            $query = PosTransaction::query()
                ->with([
                    // Muat semua relasi yang relevan untuk ditampilkan
                    'customer' => fn($q) => $q->select('id', 'name'),
                    'member' => fn($q) => $q->select('id', 'name'),
                    'paymentMethod' => fn($q) => $q->select('id', 'name'),
                    'outlet' => fn($q) => $q->select('id', 'name'),
                    'employee' => fn($q) => $q->select('id', 'name'),
                    'items', // Muat semua item dalam transaksi
                ]);

            // Kasus 1: Mengambil satu transaksi spesifik
            if (isset($this->payload['id'])) {
                // Pastikan transaksi yang diminta milik business_id yang benar
                $query->whereHas('outlet.business', function ($q) {
                    $q->where('id', $this->payload['business_id']);
                });
                $data = $query->find($this->payload['id']);
                return (object) ['status' => !is_null($data), 'data' => $data ? $data->toArray() : null];
            }

            // Kasus 2: Mengambil daftar transaksi
            $this->applyFilters($query);

            $perPage = $this->payload['per_page'] ?? 15;
            $data = $query->orderBy('transaction_date', 'desc')->paginate($perPage);

            return (object) ['status' => true, 'data' => $data->toArray()];
        } catch (Exception $e) {
            return (object) ['status' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Menerapkan filter pada query.
     * @param \Illuminate\Database\Eloquent\Builder $query
     */
    private function applyFilters(&$query)
    {
        // Filter WAJIB berdasarkan unit bisnis
        $query->whereHas('outlet.business', function ($q) {
            $q->where('id', $this->payload['business_id']);
        });

        // Filter opsional
        if (isset($this->payload['outlet_id'])) {
            $query->whereHas('outlet', function ($q) {
                $q->where('id', $this->payload['outlet_id']);
            });
        }
        if (isset($this->payload['employee_id'])) {
            $query->where('employee_id', $this->payload['employee_id']);
        }
        if (isset($this->payload['customer_id'])) {
            $query->where('customer_id', $this->payload['customer_id']);
        }
        if (isset($this->payload['start_date'])) {
            $query->whereDate('transaction_date', '>=', $this->payload['start_date']);
        }
        if (isset($this->payload['end_date'])) {
            $query->whereDate('transaction_date', '<=', $this->payload['end_date']);
        }
        if (isset($this->payload['keyword'])) {
            $keyword = $this->payload['keyword'];
            $query->where(function ($subQuery) use ($keyword) {
                // Cari berdasarkan ID transaksi atau nama customer
                $subQuery->where('id', 'LIKE', "%{$keyword}%")
                    ->orWhereHas('customer', function ($q) use ($keyword) {
                        $q->where('name', 'LIKE', "%{$keyword}%");
                    });
            });
        }
    }
}
