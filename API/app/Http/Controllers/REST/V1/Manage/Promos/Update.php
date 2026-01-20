<?php

namespace App\Http\Controllers\REST\V1\Manage\Promos;

use App\Http\Controllers\REST\BaseREST;
use App\Http\Controllers\REST\Errors;
use App\Http\Controllers\REST\V1\Manage\Promos\DBRepo;

class Update extends BaseREST
{
    public function __construct(
        ?array $payload = [],
        ?array $file = [],
        ?array $auth = []
    ) {
        $this->payload = $payload;
        $this->file = $file;
        $this->auth = $auth;
        return $this;
    }

    protected $payloadRules = [
        'id' => 'required|integer|exists:promos,id',
        'business_id' => 'integer|exists:business,id',
        'outlet_ids' => 'nullable|array',
        'outlet_ids.*' => 'integer|exists:outlets,id',
        'name' => 'string|max:255',
        'description' => 'nullable|string',
        'start_date' => 'date_format:Y-m-d',
        'end_date' => 'date_format:Y-m-d|after_or_equal:start_date',
        'is_cumulative' => 'nullable|boolean',

        'rewards' => 'nullable|array',
        'rewards.*.reward_type' => 'required_with:rewards|string|in:DISCOUNT_PERCENTAGE,DISCOUNT_FIXED,FREE_VARIANT,FREE_RESOURCE',

        'conditions' => 'nullable|array',
        'schedules' => 'nullable|array',

        'is_active' => 'nullable|boolean',
    ];

    protected $privilegeRules = [
        "PROMO_MANAGE_VIEW",
        "PROMO_MANAGE_UPDATE",
    ];
    protected function mainActivity()
    {
        return $this->nextValidation();
    }

    private function nextValidation()
    {
        // Ambil business_id dari promo yang ada di database sebagai "jangkar"
        $promoBusinessId = DBRepo::findBusinessId($this->payload['id']);
        if (!$promoBusinessId) {
            return $this->error((new Errors)->setMessage(404, 'Promo not found.'));
        }

        // Validasi konsistensi outlet_ids dengan business_id promo
        if (array_key_exists('outlet_ids', $this->payload)) {
            if (!DBRepo::validateOutletConsistency($promoBusinessId, $this->payload['outlet_ids'])) {
                return $this->error(
                    (new Errors)
                        ->setMessage(409, 'One or more outlets do not belong to the business unit of this promo.')
                        ->setReportId('MPRU1') // Manage Promo Update 1
                );
            }
        }

        // Validasi lain jika ada (misal: conditions, rewards)
        if (array_key_exists('conditions', $this->payload)) {
            if (!DBRepo::validateConditions($this->payload['conditions'])) {
                return $this->error((new Errors)->setMessage(400, 'One or more target_id in conditions is invalid.'));
            }
        }
        if (array_key_exists('rewards', $this->payload)) {
            if (!DBRepo::validateRewards($this->payload['rewards'])) {
                return $this->error((new Errors)->setMessage(400, 'One or more target_id in rewards is invalid.'));
            }

            if (!DBRepo::validateDiscountRules($this->payload['rewards'])) {
                return $this->error(
                    (new Errors)
                        ->setMessage(400, 'A promo can only have one monetary discount (PERCENTAGE or FIXED), not multiple.')
                        ->setReportId('MPRU2') // Manage Promo Update 2
                );
            }
        }

        return $this->update();
    }


    public function update()
    {
        $dbRepo = new DBRepo($this->payload, $this->file, $this->auth);
        $update = $dbRepo->updateData();
        if ($update->status) {
            return $this->respond(200);
        }
        return $this->error(500, ['reason' => $update->message]);
    }
}
