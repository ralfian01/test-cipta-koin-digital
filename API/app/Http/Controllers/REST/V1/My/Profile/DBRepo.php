<?php

namespace App\Http\Controllers\REST\V1\My\Profile;

use App\Http\Libraries\BaseDBRepo;
use App\Models\Account;
use App\Models\AccountModel;
use App\Models\UserProfile;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class DBRepo extends BaseDBRepo
{
    /**
     * Mengambil data profil pengguna yang sedang login.
     * @return object
     */
    public function getProfile()
    {
        try {
            $accountId = $this->auth['account_id'];

            // Ambil akun beserta relasi ke profil dan peran (role)
            $account = AccountModel::with(['userProfile', 'accountRole'])->find($accountId);

            if (!$account || !$account->userProfile) {
                return (object)['status' => false];
            }

            // Kita bisa format ulang responsnya agar lebih rapi
            $profileData = [
                'account_id' => $account->id,
                'username' => $account->username,
                'role' => $account->accountRole->name ?? null,
                'name' => $account->userProfile->name,
                'phone_number' => $account->userProfile->phone_number,
                'address' => $account->userProfile->address,
            ];

            return (object)['status' => true, 'data' => $profileData];
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Memperbarui data profil pengguna yang sedang login.
     * @return object
     */
    public function updateProfile()
    {
        try {
            return DB::transaction(function () {
                $accountId = $this->auth['account_id'];
                $profile = UserProfile::where('account_id', $accountId)->firstOrFail();

                // Ambil hanya field yang diizinkan dari payload
                $updatePayload = Arr::only($this->payload, ['name', 'phone_number', 'address']);

                if (empty($updatePayload)) {
                    // Jika tidak ada data yang dikirim, kembalikan data profil saat ini
                    return $this->getProfile();
                }

                $profile->update($updatePayload);

                // Panggil kembali getProfile untuk mengembalikan data yang sudah diperbarui
                return $this->getProfile();
            });
        } catch (Exception $e) {
            return (object)['status' => false, 'message' => $e->getMessage()];
        }
    }
}
