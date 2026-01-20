<?php

namespace App\Http\Controllers\REST\V1\Blueprint\Cooperation\Settings\SavingsTypes;

use App\Http\Libraries\BaseDBRepo;
use App\Models\CooperationSavingsType;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class DBRepo extends BaseDBRepo
{
    public function getData()
    {
        try {
            $query = CooperationSavingsType::query();

            if (isset($this->payload['id'])) {
                $data = $query->find($this->payload['id']);
                return (object)['status' => !is_null($data), 'data' => $data ? $data->toArray() : null];
            }

            if (isset($this->payload['code'])) {
                $query->where('code', $this->payload['code']);
            }

            $data = $query->orderBy('name', 'asc')->get();
            return (object)['status' => true, 'data' => $data->toArray()];
        } catch (Exception $e) {
            return (object) [
                'status' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function insertData()
    {
        try {
            $type = CooperationSavingsType::create($this->payload);
            return (object)['status' => true, 'data' => (object)['id' => $type->id]];
        } catch (Exception $e) {
            return (object) [
                'status' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function updateData()
    {
        try {
            $type = CooperationSavingsType::findOrFail($this->payload['id']);
            $dbPayload = Arr::except($this->payload, 'id');
            $type->update($dbPayload);
            return (object)['status' => true];
        } catch (Exception $e) {
            return (object) [
                'status' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function deleteData()
    {
        try {
            $type = CooperationSavingsType::findOrFail($this->payload['id']);
            // onDelete('restrict') di tabel mapping dan transaksi akan mencegah penghapusan
            $type->delete();
            return (object)['status' => true];
        } catch (Exception $e) {
            if (str_contains($e->getMessage(), 'violates foreign key constraint')) {
                return (object)['status' => false, 'message' => 'Cannot delete type: It is being used in transaction mappings or histories.'];
            }
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }

    /*
     * =================================================================================
     * METHOD STATIS UNTUK VALIDASI
     * =================================================================================
     */

    /**
     * Memeriksa apakah 'name' atau 'code' unik saat proses update.
     * @param string $field 'name' atau 'code'
     * @param string $value Nilai yang akan diperiksa
     * @param int $ignoreId ID dari record yang sedang di-update
     * @return bool
     */
    public static function isFieldUniqueOnUpdate(string $field, string $value, int $ignoreId): bool
    {
        return !CooperationSavingsType::where($field, $value)
            ->where('id', '!=', $ignoreId)
            ->exists();
    }
}
