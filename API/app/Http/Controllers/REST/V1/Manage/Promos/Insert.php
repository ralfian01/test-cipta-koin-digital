<?php

namespace App\Http\Controllers\REST\V1\Manage\Promos;

use App\Http\Controllers\REST\BaseREST;
use App\Http\Controllers\REST\Errors;
use App\Http\Controllers\REST\V1\Manage\Promos\DBRepo;

class Insert extends BaseREST
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
        'business_id' => 'nullable|integer|exists:business,id|required_without:outlet_ids',
        'outlet_ids' => 'nullable|array',
        'outlet_ids.*' => 'integer|exists:outlets,id',
        'name' => 'required|string|max:255',
        'description' => 'nullable|string',
        'start_date' => 'required|date_format:Y-m-d',
        'end_date' => 'required|date_format:Y-m-d|after_or_equal:start_date',
        'is_cumulative' => 'nullable|boolean',

        'rewards' => 'required|array|min:1',
        'rewards.*.reward_type' => 'required_with:rewards|string|in:DISCOUNT_PERCENTAGE,DISCOUNT_FIXED,FREE_VARIANT,FREE_RESOURCE',
        'rewards.*.value' => 'required_if:rewards.*.reward_type,DISCOUNT_PERCENTAGE,DISCOUNT_FIXED|nullable|numeric|min:0',
        'rewards.*.target_id' => 'required_if:rewards.*.reward_type,FREE_VARIANT,FREE_RESOURCE|nullable|integer',
        'rewards.*.quantity' => 'required_if:rewards.*.reward_type,FREE_VARIANT,FREE_RESOURCE|nullable|integer|min:1',
        'rewards.*.unit_id' => 'nullable|integer|exists:units,unit_id',

        'conditions' => 'required|array|min:1',
        'conditions.*.condition_type' => 'required_with:conditions|string|in:TOTAL_PURCHASE,PRODUCT_VARIANT,PRODUCT_CATEGORY,PACKAGE',
        'conditions.*.target_id' => 'nullable|integer',
        'conditions.*.min_value' => 'nullable|numeric',
        'conditions.*.min_quantity' => 'nullable|integer|min:1',

        'schedules' => 'required|array|min:1',
        'schedules.*.day_of_week' => 'required_with:schedules|integer|between:0,6',
        'schedules.*.start_time' => 'required_with:schedules|date_format:H:i',
        'schedules.*.end_time' => 'required_with:schedules|date_format:H:i|after:schedules.*.start_time',

        'is_active' => 'nullable|boolean',
    ];

    protected $privilegeRules = [
        "PROMO_MANAGE_VIEW",
        "PROMO_MANAGE_INSERT",
    ];
    protected function mainActivity()
    {
        return $this->nextValidation();
    }

    private function nextValidation()
    {
        // Panggil method validasi dari DBRepo
        $validationResult = DBRepo::validateAndDetermineBusinessId($this->payload);

        if (!$validationResult->status) {
            return $this->error(
                (new Errors)
                    ->setMessage(409, $validationResult->message)
                    ->setReportId('MPRI2') // Manage Promo Insert 2
            );
        }

        // Timpa atau isi $this->payload['business_id'] dengan hasil validasi
        $this->payload['business_id'] = $validationResult->business_id;

        // Validasi lanjutan untuk conditions dan rewards
        if (!DBRepo::validateConditions($this->payload['conditions'])) {
            return $this->error((new Errors)->setMessage(400, 'One or more target_id in conditions is invalid.'));
        }
        if (isset($this->payload['rewards'])) {
            if (!DBRepo::validateRewards($this->payload['rewards'])) {
                return $this->error((new Errors)->setMessage(400, 'One or more target_id in rewards is invalid.'));
            }
        }

        // Validasi 3: Aturan Diskon Moneter
        if (!DBRepo::validateDiscountRules($this->payload['rewards'])) {
            return $this->error(
                (new Errors)
                    ->setMessage(400, 'A promo can only have one monetary discount (PERCENTAGE or FIXED), not multiple.')
                    ->setReportId('MPRI3') // Manage Promo Insert 3
            );
        }

        return $this->insert();
    }


    public function insert()
    {
        $dbRepo = new DBRepo($this->payload, $this->file, $this->auth);
        $insert = $dbRepo->insertData();
        if ($insert->status) {
            return $this->respond(201, ['id' => $insert->data->id]);
        }
        return $this->error(500, ['reason' => $insert->message]);
    }
}
