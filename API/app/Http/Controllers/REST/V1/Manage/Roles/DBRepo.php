<?php

namespace App\Http\Controllers\REST\V1\Manage\Roles;

use App\Http\Libraries\BaseDBRepo;
use App\Models\AccountModel;
use App\Models\RoleModel;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str; // Import Str facade

class DBRepo extends BaseDBRepo
{
    public function getData()
    {
        try {
            $query = RoleModel::query()
                ->whereDoesntHave('rolePrivilege', function ($q) {
                    $q->where('code', 'GLOBAL_ACCESS');
                })
                ->with([
                    'rolePrivilege' => fn($q) => $q->select('code')
                ])
                ->orderBy('id', 'asc');

            if (isset($this->payload['id'])) {
                $data = $query->find($this->payload['id']);
                return (object)['status' => !is_null($data), 'data' => $data ? $data->toArray() : null];
            }

            $data = $query
                ->get()
                ->map(function ($item) {
                    $item->privileges = $item->rolePrivilege->map(function ($priv) {
                        return $priv->code;
                    });

                    $item->makeHidden(['rolePrivilege']);

                    return $item;
                });

            return (object)['status' => true, 'data' => $data->toArray()];
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }

    public function insertData()
    {
        try {
            return DB::transaction(function () {
                // Aturan #3: Generate 'code' dari 'name'
                $code = Str::upper(Str::replace([' ', '-'], '_', $this->payload['name']));

                $role = RoleModel::create([
                    'name' => $this->payload['name'],
                    'code' => $code,
                ]);
                if (!$role) throw new Exception("Failed to create role.");

                // Aturan #2: Assign privilege
                $role->rolePrivilege()->sync($this->payload['privilege_ids']);

                return (object)['status' => true, 'data' => (object)['id' => $role->id]];
            });
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }

    public function updateData()
    {
        try {
            return DB::transaction(function () {
                $role = RoleModel::findOrFail($this->payload['id']);
                $dbPayload = Arr::except($this->payload, ['id', 'privilege_ids']);

                // Jika nama diubah, generate ulang 'code'
                if (isset($dbPayload['name'])) {
                    $dbPayload['code'] = Str::upper(Str::replace([' ', '-'], '_', $dbPayload['name']));
                }

                if (!empty($dbPayload)) {
                    $role->update($dbPayload);
                }

                // Sinkronkan privilege jika ada di payload
                if (array_key_exists('privilege_ids', $this->payload)) {
                    $role->privileges()->sync($this->payload['privilege_ids']);
                }
                return (object)['status' => true];
            });
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }

    public function deleteData()
    {
        try {
            return DB::transaction(function () {
                $role = RoleModel::findOrFail($this->payload['id']);
                // Hapus relasi di pivot dulu, lalu hapus role
                $role->privileges()->detach();
                $role->delete();
                return (object)['status' => true];
            });
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }

    public static function isNameUniqueOnUpdate(string $name, int $ignoreId): bool
    {
        return !RoleModel::where('name', $name)->where('id', '!=', $ignoreId)->exists();
    }

    /**
     * Memeriksa apakah sebuah Role sedang digunakan oleh setidaknya satu Akun.
     * @param int $roleId
     * @return bool
     */
    public static function isRoleInUse(int $roleId): bool
    {
        // Cukup periksa keberadaannya, ini sangat efisien.
        return AccountModel::where('role_id', $roleId)->exists();
    }
}
