<?php

namespace App\Http\Controllers\REST\V1\Manage\Outlets;

use App\Http\Libraries\BaseDBRepo;
use App\Models\Outlet;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class DBRepo extends BaseDBRepo
{
    /**
     * Mengambil data outlet
     */
    public function getData()
    {
        try {
            $query = Outlet::query()->with(['business' => fn($q) => $q->select('id', 'name')]);

            if (isset($this->payload['id'])) {
                $data = $query->find($this->payload['id']);
                return (object) ['status' => !is_null($data), 'data' => $data ? $data->toArray() : null];
            }

            if (isset($this->payload['business_id'])) {
                $query->where('business_id', $this->payload['business_id']);
            }
            if (isset($this->payload['keyword'])) {
                $query->where('name', 'LIKE', "%{$this->payload['keyword']}%");
            }

            $perPage = $this->payload['per_page'] ?? 15;
            $data = $query->paginate($perPage);

            return (object) ['status' => true, 'data' => $data->toArray()];
        } catch (Exception $e) {
            return (object) ['status' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Menambah data outlet baru
     */
    public function insertData()
    {
        try {
            return DB::transaction(function () {
                $outlet = Outlet::create($this->payload);
                if (!$outlet) {
                    throw new Exception("Failed to create a new outlet.");
                }
                return (object) ['status' => true, 'data' => (object) ['id' => $outlet->id]];
            });
        } catch (Exception $e) {
            return (object) ['status' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Mengedit data outlet
     */
    public function updateData()
    {
        try {
            $outlet = Outlet::findOrFail($this->payload['id']);
            $dbPayload = Arr::except($this->payload, ['id']);

            return DB::transaction(function () use ($outlet, $dbPayload) {
                $outlet->update($dbPayload);
                return (object) ['status' => true];
            });
        } catch (Exception $e) {
            return (object) ['status' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Menghapus data outlet
     */
    public function deleteData()
    {
        try {
            $outlet = Outlet::findOrFail($this->payload['id']);

            // onDelete('cascade') pada relasi pivot akan menangani penghapusan
            // relasi ke produk dan karyawan secara otomatis.
            $outlet->delete();

            return (object) ['status' => true];
        } catch (Exception $e) {
            return (object) [
                'status' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
