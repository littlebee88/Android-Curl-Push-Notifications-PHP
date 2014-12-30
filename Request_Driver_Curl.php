<?php
/**
 * Request_Driver_Curl Class
 *
 * @category  Request Driver
 * @package   Android_Push_Notification
 * @author    Stephanie Schmidt <littlebeehigbee@gmail.com>
 * @copyright Copyright (c) 2014
 * @license   http://opensource.org/licenses/gpl-3.0.html GNU Public License
 * @version   1.0
 **/

/**
 * Class Request_Driver_Curl
 */
class Request_Driver_Curl
{
    /**
     * @var
     */
    public $resource;

    /**
     * @var
     */
    public $responseHeaders;

    /**
     * @var
     */
    public $headers;

    /**
     * @var
     */
    private $preserve_resource;

    /**
     * CURL object
     * @var $curl
     */
    private $curl;

    /**
     * @var string
     */
    private $auth = 'any';

    /**
     * @var string
     */
    private $user = 'YourUsername';

    /**
     * @var string
     */
    private $pass = 'YourPassword';

    /**
     * @var bool
     */
    private $method = false;

    /**
     * @var int
     */
    private $timeout = 30;

    /**
     * @var bool
     */
    private $return_transfer = true;

    /**
     * @var bool
     */
    private $fail_on_error = false;

    /**
     * @var bool
     */
    private $follow_loc = true;

    /**
    * @var
    */
    private $payload;
	/**
     * @var
     */
    private $response;
	/**
     * @var
     */
    private $response_info;


    /**
     * @param $payload
     * @param bool $testing
     * @throws Exception
     */
    public function __construct($payload, $testing=true)
    {
        // check if we have libcurl available
        if (!function_exists('curl_init')) {
            throw new Exception('Your PHP installation doesn\'t have cURL enabled. Rebuild PHP with --with-curl');
        }

        $this->payload = $payload;
        $this->resource = 'https://android.googleapis.com/gcm/send';

        // If authentication is enabled use it
        if (!empty($this->auth) and !empty($this->user) and !empty($this->pass)) {
            $this->httpLogin($this->user, $this->pass, $this->auth);
        }
    }

    /**
     * Fetch the connection, create if necessary
     *
     * @return  resource
     */
    protected function connection()
    {
        // If no a protocol in URL, assume its a local link
        !preg_match('!^\w+://! i', $this->resource) and $this->resource;
        return curl_init($this->resource);
    }

    /**
     * @param string $username
     * @param string $password
     * @param string $type
     * @return $this
     */
    public function httpLogin($username = '', $password = '', $type = 'any')
    {
        curl_setopt($this->curl, CURLOPT_HTTPAUTH, constant('CURLAUTH_' . strtoupper($type)));
        curl_setopt($this->curl, CURLOPT_USERPWD, $username . ':' . $password);
        return $this;
    }


	/**
     * @return bool
     * @throws Exception
     */
    public function execute()
    {
        // Reset response
        $this->response = null;
        $this->response_info = array();
        $this->preserve_resource = $this->resource;
        $this->curl = curl_init();
        $headers = $this->getHeaders();

        //add method specific curl settings
        if ($this->method) {
            $method = 'method' . ucfirst($this->method);
            $this->$method();
        } else {
            $this->methodPost();
        }

        curl_setopt($this->curl, CURLOPT_URL, $this->resource);
        if (!empty($headers)) {
            curl_setopt($this->curl, CURLOPT_HTTPHEADER, $headers);
        }
        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
        //curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, false);
        //curl_setopt($this->curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($this->curl, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, $this->return_transfer);
        curl_setopt($this->curl, CURLOPT_FAILONERROR, $this->fail_on_error);

        // Only set follow location if not running securely
        if (!ini_get('safe_mode') && !ini_get('open_basedir')) {
            curl_setopt($this->curl, CURLOPT_FOLLOWLOCATION, $this->follow_loc);
        }

        // Execute the request & and capture all output
        $body = curl_exec($this->curl);
        $this->setResponseInfo(curl_getinfo($this->curl));
        $status = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);

        // Request failed
        if ($body === false) {
            throw new Exception(curl_error($this->curl) . ' error: ' . curl_errno($this->curl));
        } elseif ($status >= 400) {
            //split the headers from the body
            $body = $this->splitBodyFromHeaders($body);
            throw new Exception($body . $this->getStatusCodeMessage($this->response->status));
        }

        // Request successful
        curl_close($this->curl);
        return true;
    }

    /**
     *
     */
    protected function methodGet()
    {
        if(false === strpos($this->resource, "?")){
            $this->resource = $this->resource.'&'.http_build_query($this->params);
        } else {
            $this->resource = $this->resource.'?'.http_build_query($this->params);
        }
    }

    /**
     *
     */
    protected function methodHead()
    {
        $this->methodGet();
        curl_setopt($this->curl, CURLOPT_NOBODY, true);
        curl_setopt($this->curl, CURLOPT_HEADER, true);
    }

    /**
     *
     */
    protected function methodPost()
    {
        curl_setopt($this->curl, CURLOPT_POST, true);
        curl_setopt($this->curl, CURLOPT_POSTFIELDS, $this->payload);
    }

    /**
     *
     */
    protected function methodPut()
    {
        $fields = (is_array($this->payload)) ? http_build_query($this->payload) : $this->payload;
        curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, 'PUT');
        //curl_setopt($this->curl, CURLOPT_PUT, true);
        curl_setopt($this->curl, CURLOPT_HTTPHEADER, array('Content-Length: ' . strlen($fields)));
        curl_setopt($this->curl, CURLOPT_POSTFIELDS, $this->payload);
    }

    /**
     *
     */
    protected function methodDelete()
    {
        $fields = (is_array($this->payload)) ? http_build_query($this->payload) : $this->payload;
        curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($this->curl, CURLOPT_HTTPHEADER, array('Content-Length: ' . strlen($fields)));
        curl_setopt($this->curl, CURLOPT_POSTFIELDS, $this->payload);
        // Override method
        $this->setHeader('X-HTTP-Method-Override', 'DELETE');
    }

    /**
     * @param $result
     * @return string
     */
    protected function splitBodyFromHeaders($result)
    {
        $dom = new DOMDocument;
        $dom->loadHTML($result);

        $body = '';
        $bodies = $dom->getElementsByTagName('body');
        assert($bodies->length === 1);
        foreach ($bodies as $b) {
            $body .= $b->nodeValue;
        }

        $raw_headers = $dom->getElementsByTagName('head');
        assert($raw_headers->length === 1);
        // Convert the header data
        foreach ($raw_headers as $h) {
            $header = explode(':', $h->nodeValue, 2);
            if (isset($header[1])) {
                $this->setResponseHeader(trim($header[0]), trim($header[1]));
            }
            else{
                $this->setResponseHeader(trim($header[0]));
            }
        }
        return html_entity_decode($body);
    }

    /**
     * @param boolean $method
     */
    public function setMethod($method) {
        $this->method = $method;
    }

    /**
     * @param $header
     * @param null $content
     * @return $this
     */
    public function setHeader($header, $content = null)
    {
        if (is_null($content))
        {
            $this->headers[] = $header;
        }
        else
        {
            $this->headers[$header] = $content;
        }
        return $this;
    }

    /**
     * @param $header
     * @param null $default
     * @return null
     */
    public function getHeader($header, $default=null)
    {
        if(isset($this->headers[$header])){
            return $this->headers[$header];
        }
        return $default;
    }


    /**
     * @return array
     */
    public function getHeaders()
    {
        $headers = array();
        foreach ($this->headers as $key => $value)
        {
            $headers[] = is_int($key) ? $value : $key.': '.$value;
        }
        return $headers;
    }

    /**
     * @param $header
     * @param null $content
     * @return $this
     */
    public function setResponseHeader($header, $content = null)
    {
        if (is_null($content))
        {
            $this->responseHeaders[] = $header;
        }
        else
        {
            $this->responseHeaders[$header] = $content;
        }
        return $this;
    }

    /**
     * @param $header
     * @param null $default
     * @return null
     */
    public function getResponseHeader($header, $default=null)
    {
        if(isset($this->responseHeaders[$header])){
            return $this->responseHeaders[$header];
        }
        return $default;
    }


    /**
     * @return array
     */
    public function getResponseHeaders()
    {
        $headers = array();
        foreach ($this->responseHeaders as $key => $value)
        {
            $headers[] = is_int($key) ? $value : $key.': '.$value;
        }
        return $headers;
    }

        // Helper method to get a string description for an HTTP status code
    // From http://www.gen-x-design.com/archives/create-a-rest-api-with-php/
    /**
     * @param $status
     * @return string
     */
    function getStatusCodeMessage($status)
    {
        // these could be stored in a .ini file and loaded
        // via parse_ini_file()... however, this will suffice
        // for an example
        $codes = Array(
            100 => 'Continue',
            101 => 'Switching Protocols',
            200 => 'OK',
            201 => 'Created',
            202 => 'Accepted',
            203 => 'Non-Authoritative Information',
            204 => 'No Content',
            205 => 'Reset Content',
            206 => 'Partial Content',
            300 => 'Multiple Choices',
            301 => 'Moved Permanently',
            302 => 'Found',
            303 => 'See Other',
            304 => 'Not Modified',
            305 => 'Use Proxy',
            306 => '(Unused)',
            307 => 'Temporary Redirect',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            402 => 'Payment Required',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            406 => 'Not Acceptable',
            407 => 'Proxy Authentication Required',
            408 => 'Request Timeout',
            409 => 'Conflict',
            410 => 'Gone',
            411 => 'Length Required',
            412 => 'Precondition Failed',
            413 => 'Request Entity Too Large',
            414 => 'Request-URI Too Long',
            415 => 'Unsupported Media Type',
            416 => 'Requested Range Not Satisfiable',
            417 => 'Expectation Failed',
            500 => 'Internal Server Error',
            501 => 'Not Implemented',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
            505 => 'HTTP Version Not Supported'
        );

        return (isset($codes[$status])) ? $codes[$status] : '';
    }

    /**
     * @param array $response_info
     */
    public function setResponseInfo($response_info)
    {
        $this->response_info = $response_info;
    }

    /**
     * @return array
     */
    public function getResponseInfo()
    {
        return $this->response_info;
    }
}