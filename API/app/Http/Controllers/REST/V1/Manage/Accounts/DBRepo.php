<?php

namespace App\Http\Controllers\REST\V1\Manage\Accounts;

use App\Http\Libraries\BaseDBRepo;
use App\Models\AccountModel;
use App\Models\Employee;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DBRepo extends BaseDBRepo
{
    public function getData()
    {
        try {
            $query = AccountModel::query()
                ->with([
                    'roles'
                ]);
            if (isset($this->payload['id'])) {
                $data = $query->find($this->payload['id']);
                return (object)['status' => !is_null($data), 'data' => $data ? $data->toArray() : null];
            }
            if (isset($this->payload['keyword'])) {
                $query->where('username', 'LIKE', "%{$this->payload['keyword']}%");
            }
            $perPage = $this->payload['per_page'] ?? 15;
            $data = $query->paginate($perPage);
            return (object)['status' => true, 'data' => $data->toArray()];
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }

    public function insertData()
    {
        try {
            return DB::transaction(function () {
                $dbPayload = Arr::except($this->payload, ['password_confirmation', 'role_ids']);
                $dbPayload['uuid'] = Str::uuid();

                $account = AccountModel::create($dbPayload);
                if (!$account) throw new Exception("Failed to create account.");

                $account->roles()->sync($this->payload['role_ids']);

                return (object)['status' => true, 'data' => (object)['id' => $account->id]];
            });
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }

    public function updateData()
    {
        try {
            return DB::transaction(function () {
                $account = AccountModel::findOrFail($this->payload['id']);
                $dbPayload = Arr::except($this->payload, ['id', 'password_confirmation', 'role_ids']);

                if (empty($dbPayload['password'])) unset($dbPayload['password']);

                if (!empty($dbPayload)) $account->update($dbPayload);

                if (array_key_exists('role_ids', $this->payload)) {
                    $account->roles()->sync($this->payload['role_ids']);
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
                $account = AccountModel::findOrFail($this->payload['id']);
                $account->delete(); // onDelete('cascade') akan menghapus relasi pivot
                return (object)['status' => true];
            });
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }

    public static function isUsernameUniqueOnUpdate(string $username, int $ignoreId): bool
    {
        return !AccountModel::where('username', $username)->where('id', '!=', $ignoreId)->exists();
    }

    public static function isAccountDeletable(int $accountId): bool
    {
        $account = AccountModel::find($accountId);
        return $account && $account->deletable;
    }
}
