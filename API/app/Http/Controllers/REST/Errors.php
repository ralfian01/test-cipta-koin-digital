<?php

namespace App\Http\Controllers\REST;

class Errors
{
    /**
     * @var string|int $code
     */
    protected $code;

    /**
     * @var string $report_id
     */
    protected $report_id;

    /**
     * Function called internally
     * @var bool
     */
    protected $internal = true;

    /**
     * Error statuses
     */
    private $statuses = [
        400 => [
            'status' => 'BAD_REQUEST',
            'message' => 'Bad Request'
        ],
        401 => [
            'status' => 'UNAUTHORIZED',
            'message' => 'You do not have permission to access this resource'
        ],
        403 => [
            'status' => 'FORBIDDEN',
            'message' => 'You are prohibited from accessing these resources'
        ],
        404 => [
            'status' => 'NOT_FOUND',
            'message' => 'URL not available'
        ],
        409 => [
            'status' => 'CONFLICT',
            'message' => 'Data already exists in the system'
        ],
        500 => [
            'status' => 'INTERNAL_ERROR',
            'message' => 'There is an error in internal server'
        ]
    ];


    /**
     * Set internal property
     * @return App\Http\Controllers\REST\Errors
     */
    public function setInternal(?bool $internal = true)
    {
        $this->internal = $internal;
        return $this;
    }

    /**
     * Set error status
     * @param $code HTTP Code or error status
     * @return App\Http\Controllers\REST\Errors
     */
    public function setStatus($code, $status = null)
    {
        if (is_null($this->code)) {
            $this->code = $code;
        }

        $this->statuses[$this->code]['status'] = $status ?? $code;
        return $this;
    }

    /**
     * Set error message
     * @param $code HTTP Code or error message
     * @return App\Http\Controllers\REST\Errors
     */
    public function setMessage($code, $message = null)
    {
        if (is_null($this->code)) {
            $this->code = $code;
        }

        $this->statuses[$this->code]['message'] = $message ?? $code;
        return $this;
    }

    /**
     * Set error detail
     * @param $code HTTP Code or error detail
     * @return App\Http\Controllers\REST\Errors
     */
    public function setDetail($code, $detail = null)
    {
        if (is_null($this->code)) {
            $this->code = $code;
        }

        $this->statuses[$this->code]['error_detail'] = $detail ?? $code;
        return $this;
    }

    /**
     * Set error detail
     * @param $report_id Show report id when client request has valid origin 
     * @return App\Http\Controllers\REST\Errors
     */
    public function setReportId(string $report_id = null)
    {
        $this->report_id = $report_id;
        return $this;
    }

    /**
     * Function contains json template to provide error response
     * @param array|null $detail Show error detail
     * @return void
     */
    public function sendError($code = 400, ?array $detail = null)
    {
        if (is_null($this->code)) {
            $this->code = $code;
        }

        $json = [
            'code' => $this->code,
            'status' => $this->statuses[$this->code]['status'],
            'message' => $this->statuses[$this->code]['message'],
            'error_detail' => $detail ?? $this->statuses[$this->code]['error_detail'] ?? [],
        ];

        if (isset($this->report_id)) {
            $json['report_id'] = $this->report_id;
        }

        // if (isset($report_id)) {
        //     if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $this->validOrigin))
        //         $json['report_id'] = $report_id;
        // }

        if (!$this->internal) {
            return response(
                json_encode($json, JSON_PRETTY_PRINT),
                $this->code,
                ['Content-Type' => 'application/json']
            );
        }

        return $json['code'];
    }
}
