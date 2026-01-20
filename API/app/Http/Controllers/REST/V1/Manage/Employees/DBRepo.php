<?php

namespace App\Http\Controllers\REST\V1\Manage\Employees;

use App\Http\Libraries\BaseDBRepo;
use App\Models\AccountModel;
use App\Models\Employee;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class DBRepo extends BaseDBRepo
{
    public function getData()
    {
        try {
            $query = Employee::query()
                ->with(['outlets', 'account.roles']);

            if (isset($this->payload['id'])) {
                $data = $query->find($this->payload['id']);
                return (object)['status' => !is_null($data), 'data' => $data ? $data->toArray() : null];
            }

            if (isset($this->payload['outlet_id'])) {
                $query->whereHas('outlets', fn($q) => $q->where('outlets.id', $this->payload['outlet_id']));
            }

            if (isset($this->payload['business_id'])) {
                $query->whereHas('business', fn($q) => $q->where('business.id', $this->payload['business_id']));
            }

            if (isset($this->payload['role_id'])) {
                $query->whereHas('roles', fn($q) => $q->where('roles.id', $this->payload['role_id']));
            }

            if (isset($this->payload['keyword'])) {
                $query->where(fn($q) => $q->where('name', 'LIKE', "%{$this->payload['keyword']}%")->orWhere('phone_number', 'LIKE', "%{$this->payload['keyword']}%"));
            }
            $perPage = $this->payload['per_page'] ?? 15;
            $data = $query->paginate($perPage);
            return (object)['status' => true, 'data' => $data->toArray()];
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Menambah data Akun dan Karyawan baru dalam satu transaksi.
     */
    public function insertData()
    {
        try {
            return DB::transaction(function () {
                $employeePayload = Arr::except($this->payload, ['pin_confirmation', 'outlet_ids']);
                $employee = Employee::create($employeePayload);
                if (!$employee) {
                    throw new Exception("Failed to create employee.");
                }
                if (!empty($this->payload['outlet_ids'])) {
                    $employee->outlets()->sync($this->payload['outlet_ids']);
                }
                return (object)['status' => true, 'data' => (object)['id' => $employee->id]];
            });
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Mengedit data Karyawan dan Akun terkait.
     */
    public function updateData()
    {
        try {
            return DB::transaction(function () {
                $employee = Employee::findOrFail($this->payload['id']);

                // Pisahkan payload untuk employee dan outlet
                $employeePayload = Arr::except($this->payload, ['id', 'pin_confirmation', 'outlet_ids']);

                // --- KUNCI PERBAIKAN DI SINI ---
                // Logika ini secara eksplisit menangani assignment 'account_id'.
                // `array_key_exists` digunakan untuk mendeteksi jika klien mengirim `account_id: null`
                // yang berarti "lepaskan akun dari karyawan ini".
                if (array_key_exists('account_id', $employeePayload)) {
                    $employee->account_id = $employeePayload['account_id'];
                    $employee->save(); // Simpan perubahan account_id secara terpisah
                }
                // ---------------------------------

                // Update sisa data employee (nama, pin, dll.)
                $otherEmployeeData = Arr::except($employeePayload, ['account_id']);
                if (!empty($otherEmployeeData)) {
                    $employee->update($otherEmployeeData);
                }

                // Update Outlet (jika ada di payload)
                if (array_key_exists('outlet_ids', $this->payload)) {
                    $employee->outlets()->sync($this->payload['outlet_ids']);
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
            $employee = Employee::findOrFail($this->payload['id']);
            $account = AccountModel::findOrFail($employee['account_id']);

            $employee->delete();
            $account->delete();
            return (object)['status' => true];
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }

    public static function isAccountIdAvailable(int $accountId, int $ignoreEmployeeId): bool
    {
        // Cek apakah akun ini sudah diambil oleh karyawan LAIN.
        return !Employee::where('account_id', $accountId)
            ->where('id', '!=', $ignoreEmployeeId)
            ->exists();
    }
}
