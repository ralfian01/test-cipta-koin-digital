<?php

namespace App\Http\Controllers\REST\V1\My\Todo;

use App\Http\Libraries\BaseDBRepo;
use App\Models\Todo;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;


class DBRepo extends BaseDBRepo
{
    /*
     * =================================================================================
     * METHOD UNTUK MENGAMBIL DATA (GET)
     * =================================================================================
     */
    public function getData()
    {
        try {
            $query = Todo::query()
                ->where('user_id', $this->auth['account_id'])
                ->with('user');

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


    /*
     * =================================================================================
     * METHOD UNTUK MENAMBAH DATA (INSERT)
     * =================================================================================
     */
    public function insertData()
    {
        try {
            return DB::transaction(function () {
                $this->payload['user_id'] = $this->auth['account_id'];

                $todoList = Todo::create($this->payload);

                if (!$todoList) {
                    throw new Exception("Failed to create a new customer category.");
                }

                return (object) ['status' => true];
            });
        } catch (Exception $e) {
            return (object) ['status' => false, 'message' => $e->getMessage()];
        }
    }

    public function updateData()
    {
        try {
            $todoList = Todo::findOrFail($this->payload['id']);
            $dbPayload = Arr::except($this->payload, ['id']);

            return DB::transaction(function () use ($todoList, $dbPayload) {
                $todoList->update($dbPayload);
                return (object) ['status' => true];
            });
        } catch (Exception $e) {
            return (object) ['status' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Fungsi utama untuk menghapus data kategori customer.
     * @return object
     */
    public function deleteData()
    {
        try {
            $todoList = Todo::findOrFail($this->payload['id']);

            $todoList->delete();

            return (object) ['status' => true];
        } catch (Exception $e) {
            return (object) [
                'status' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
