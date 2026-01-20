<?php

namespace App\Http\Controllers\REST;

use App\Http\Controllers\Controller;
use App\Http\Libraries\FormDataParser;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class BaseREST extends Controller
{
    /**
     * Instance of the main Request object
     * @var Request|null
     */
    protected $request;

    /**
     * Instance of the main response object
     * @var Response|null
     */
    protected $response;

    /**
     * An array of helpers to be loaded automatically upon class instantiation.
     * These helpers will be available to all other controllers that extend BaseController
     * @var array
     */
    protected $helpers = [];

    /**
     * Set valid origin when access API to show $report_id
     * 
     * How to use:
     * - ["."] => "https://yourdomain.com"
     * - ["sub"] => "https://sub.yourdomain.com"
     * - ["/segment"] => "https://yourdomain.com/segment"
     * - ["sub.domain.com"] => "https://sub.domain.com"
     * - ["https://specific.domain.com"] => "https://specific.domain.com"
     * 
     * @var array
     */
    protected $validOrigin = [];

    /**
     * Property that contains the authentication data
     * @var array
     */
    public $auth = [];

    /**
     * Property that contains the payload data in non file form
     * @var array
     */
    public $payload = [];

    /**
     * Property that contains the privilege data
     * @var array
     */
    protected $privilegeRules = [];

    /**
     * Property that contains the payload rules
     * (Please read the Payload class documentation for more information)
     * @var array|Payload
     */
    protected $payloadRules = [];

    /**
     * Property that contain payload data in file form
     * @var array|object
     */
    protected $file = [];

    /**
     * Property that contain database repository class
     * @var object|BaseDBRepo
     */
    protected $dbRepo;

    /**
     * Whether the class is called via the index method or not
     * @var bool
     */
    private $directCall = true;

    /**
     * Default function if client unauthorized
     * @return void|string
     */
    private function unauthorizedScheme()
    {
        return $this->error(401);
    }

    /**
     * The method that starts the main activity
     * @return null
     */
    protected function mainActivity()
    {
        return null;
    }

    /** 
     * The main method called by routes
     * @return object|string
     */
    public function index(Request $request, Response $response)
    {
        $this->request = $request;
        $this->response = $response;

        $this->directCall = false;

        // Collect payload in non file form and then combine it
        self::mergePayload($this->payload, $this->getPayload(), $this->payload);

        // Additional payload from route
        $params = $this->request->route()->parameters();
        self::mergePayload($this->payload, $params, $this->payload);

        // Collect payload in file form
        $this->file = $this->getFile();

        // Merge payload with file type payload
        self::mergePayload($this->payload, $this->file, $this->payload);

        // Check authentication status
        if ($request->attributes->has('auth_status')) {
            if (!$request->attributes->get('auth_status')) {
                return $this->unauthorizedScheme();
            }

            // Collect authentication data
            $this->auth = $request->attributes->get('auth_data');

            if (isset($this->auth['privileges'])) {

                // Check account privilege
                if (!$this->validatePrivilege($this->auth['privileges'])) {
                    return $this->unauthorizedScheme();
                }
            }
        }

        if (!method_exists(self::class, 'mainActivity')) {
            throw new Exception("Method mainActivity() does not exist");
        }

        return $this->validatePayload($params);
    }


    /** 
     * Function to check payload
     * @return void
     */
    private function validatePayload($params)
    {
        $validator = Validator::make($this->payload, $this->payloadRules);

        if ($validator->fails()) {
            return $this->error(400, $validator->errors()->toArray());
        }

        return $this->mainActivity($params);
    }

    /**
     * Function to check account authorization
     * @return boolean
     */
    private function validatePrivilege($authority)
    {
        $validCount = 0;
        foreach ($this->privilegeRules as $key => $value) {
            if (in_array($value, $authority)) $validCount++;
        }

        return $validCount >= count($this->privilegeRules);
    }

    /** 
     * Function to merge initial payload and addon payload
     * @return array
     */
    private static function mergePayload(mixed &$return, array $payload, array $addon)
    {
        foreach ($addon as $key => $val) {
            $payload[$key] = $val;
        }

        $return = $payload;
    }

    /** 
     * Function to return payload array based on request method and content type
     * This method can only return a payload array in non-file form
     * @return array
     */
    private function getPayload()
    {
        $contentType = $this->request->header('Content-Type');
        $requestMethod = strtoupper($this->request->method());

        switch ($requestMethod) {
            case 'GET':
            case 'POST':
            case 'DELETE':

                return $this->request->all();
                break;

            case 'PUT':
            case 'PATCH':

                if (strpos($contentType, 'form-data') >= 1) {
                    $formData = file_get_contents('php://input');

                    return array_merge(
                        FormDataParser::parseFormData($formData),
                        $this->request->all()
                    );
                }

                return $this->request->all();
                break;
        }

        return [];
    }

    /** 
     * Function to return an array of files sent in formdata
     * This method can only check files in formdata format 
     * and with http post, put, or patch methods  
     * @return array
     */
    private function getFile(string $fileName = null)
    {
        $contentType = $this->request->header('Content-Type');
        $requestMethod = strtoupper($this->request->method());

        if (strpos($contentType, 'form-data') >= 1) {

            switch ($requestMethod) {

                case 'POST':

                    if ($fileName != null) {
                        return $this->request->file($fileName);
                    }

                    return $this->request->files->all();
                    break;
                case 'PUT':
                case 'PATCH':

                    $formData = file_get_contents('php://input');
                    return FormDataParser::parseFormDataFile($formData);
                    break;
            }
        }

        return [];
    }


    /**
     * Function contains json template error response
     * @param Errors|int $errors Error code or instance of class App\Http\Controllers\REST\Errors
     * @return void
     */
    protected function error($errors_or_code = 400, ?array $detail = null)
    {
        if ($errors_or_code instanceof Errors) {
            return $errors_or_code
                ->setInternal($this->directCall)
                ->sendError(404);
        }

        return (new Errors)
            ->setInternal($this->directCall)
            ->sendError($errors_or_code, $detail);
    }

    /**
     * Function contains json template response
     * @param Errors|int $responds_or_code Respond code or instance of class App\Http\Controllers\REST\Responds
     * @return void
     */
    protected function respond($responds_or_code, array $data = null)
    {
        if ($responds_or_code instanceof Responds) {
            return $responds_or_code
                ->setInternal($this->directCall)
                ->sendRespond(200);
        }

        return (new Responds)
            ->setInternal($this->directCall)
            ->sendRespond($responds_or_code, $data);
    }

    // /**
    //  * @param callable  $scFunction     Callback function triggered when process success
    //  * @param callable  $errFunction    Callback function triggered when process error or failed
    //  * 
    //  * @todo callback function $errFunction must be defined if you want to do something to client when they are not authenticated
    //  */
    // public function authHandler(callable $scFunction, callable $errFunction, ...$options)
    // {

    //     /*
    //      * !!! Note:
    //      * callback function $errFunction must be defined
    //      * if you want to do something to client when they
    //      * are not authenticated
    //      * 
    //     */

    //     // Compile all arguments
    //     $param = func_get_args();

    //     // If client not authenticated
    //     if (isset($this->request->auth)) {

    //         $param['auth'] = $this->request->auth;
    //         $param['uri_segment'] = $_SERVER['REQUEST_URI'];

    //         if (!$this->request->auth->status) {

    //             if (is_callable($errFunction)) return $errFunction($param);
    //             return $this->error(500);
    //         }

    //         // If client authenticated
    //         if (is_callable($scFunction)) return $scFunction($param);
    //         return $this->error(500);
    //     } else {

    //         // If client not authenticated
    //         if (is_callable($scFunction)) return $scFunction($param);

    //         return $this->error(500);
    //     }
    // }

    // /**
    //  * Initialize Valid origins when access API to show $report_id
    //  * @return void
    //  */
    // private function initializeValidOrigin()
    // {
    //     $config = new App();
    //     $scheme = $config->secureRequest ? 'https' : 'http';

    //     foreach ($this->validOrigin as $key => $origin) {

    //         if ($origin == '.') {
    //             $url = Services::normalizeURI('/_');
    //             $this->validOrigin[$key] = "{$scheme}://" . rtrim($url, '/_') . '/';
    //             $this->validOrigin[] = "{$scheme}://" . rtrim($url, '/_');
    //             continue;
    //         }

    //         $url = Services::normalizeURI($origin);
    //         $this->validOrigin[$key] = "{$scheme}://" . rtrim($url, '/') . '/';
    //         $this->validOrigin[] = "{$scheme}://" . rtrim($url, '/');
    //     }
    // }
}
