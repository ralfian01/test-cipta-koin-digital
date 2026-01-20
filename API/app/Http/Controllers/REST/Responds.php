<?php

namespace App\Http\Controllers\REST;

class Responds
{
    /**
     * @var string|int $code
     */
    protected $code;

    /**
     * Function called internally
     * @var bool
     */
    protected $internal = true;

    /**
     * Error statuses
     */
    private $statuses = [
        200 => [
            'status' => 'SUCCESS',
            'message' => 'Success'
        ],
        201 => [
            'status' => 'CREATED',
            'message' => 'Created'
        ],
        202 => [
            'status' => 'ACCEPTED',
            'message' => 'Accepted',
        ],
        204 => [
            'status' => 'NO_CONTENT',
            'message' => 'No content',
        ],
    ];


    /**
     * Set internal property
     * @return App\Http\Controllers\REST\Responds
     */
    public function setInternal(?bool $internal = true)
    {
        $this->internal = $internal;
        return $this;
    }

    /**
     * Set respond status
     * @param $code HTTP Code or respond status
     * @return App\Http\Controllers\REST\Responds
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
     * Set respond message
     * @param $code HTTP Code or respond message
     * @return App\Http\Controllers\REST\Responds
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
     * Set respond data
     * @param $code HTTP Code or respond detail
     * @return App\Http\Controllers\REST\Responds
     */
    public function setData($code, $data = null)
    {
        if (is_null($this->code)) {
            $this->code = $code;
        }

        $this->statuses[$this->code]['data'] = $data ?? $code;
        return $this;
    }

    /**
     * Function contains json template to provide response
     * @param array|null $data Show response data
     * @return void
     */
    public function sendRespond($code = 200, ?array $data = null)
    {
        if (is_null($this->code)) {
            $this->code = $code;
        }

        $json = [
            'code' => $this->code,
            'status' => $this->statuses[$this->code]['status'],
            'message' => $this->statuses[$this->code]['message'],
            'data' => $data ?? $this->statuses['data'] ?? null,
        ];

        // Apply CORS
        // Services::CORS(new REST)->applyCors();

        if (!$this->internal) {
            return response(
                json_encode($json, JSON_PRETTY_PRINT),
                $this->code,
                ['Content-Type' => 'application/json']
            );
        }

        return $data ?? $this->statuses['data'] ?? null;
    }
}
