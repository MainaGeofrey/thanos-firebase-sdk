<?php

namespace Firebase\Resources;

use Firebase\Config;
use Firebase\Constants\SdkConstants;
use Firebase\Exceptions\FirebaseException;
use Exception;

class CurlResource
{
    /**
     * @var resource|null
     */
    private $curl = null;

    public $header = ''; // Changed to public for PHP 5.6 compatibility

    public $code = 0; // Changed to public for PHP 5.6 compatibility

    private $config; // Changed to public for PHP 5.6 compatibility

    private $ignoreSSL = false;

    private $rfc = PHP_QUERY_RFC1738;

    private $getHandler = false;

    private $db = null;

    private $requestHeaders = [];

    private $defaultHeaders = [
        'thanos-firebase-sdk: ' . SdkConstants::VERSION,
    ];

    /**
     * @param Config $config
     * @param boolean $getHandler
     */
    public function __construct(Config $config, $getHandler = false, $db = null)
    {
        if (!extension_loaded('curl')) {
            throw new FirebaseException('cURL library is not loaded');
        }

        $this->getHandler = $getHandler;
        $this->config = $config;
        $this->db = $db;

        $this->requestHeaders = array_merge($this->defaultHeaders, $this->config->getHttpHeaders());
    }

    /**
     * @param string $methodName
     * @param array $args
     * @return mixed
     */
    public function __call($methodName, $args)
    {
        if (!method_exists($this, $methodName)) {
            return false;
        }

        $this->initCurl();

        $result = call_user_func_array([$this, $methodName], $args);

        return $result;
    }

    /**
     * @param string $url
     * @return mixed
     */
    private function request($url = '')
    {
        curl_setopt($this->curl, CURLOPT_URL, $url);
        curl_setopt($this->curl, CURLOPT_VERBOSE, true);

        if ($this->ignoreSSL) {
            curl_setopt($this->curl, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, 0);
            $this->ignoreSSL = false;
        }

        if ($this->getHandler) {
            return $this->curl;
        }

        $resp = curl_exec($this->curl);
        $info = curl_getinfo($this->curl);

        if ($info['http_code'] == 417) {
            curl_setopt($this->curl, CURLOPT_HTTPHEADER, ["Expect:  "]);
            $resp = curl_exec($this->curl);
            $info = curl_getinfo($this->curl);
        }

        $header = substr($resp ?: '', 0, $info['header_size']);
        $response = substr($resp ?: '', strlen($header));

        if (is_string($resp)) {
            $header = substr($resp, 0, $info['header_size']);
            $response = substr($resp, strlen($header));
        } else {
            $header = '';
            $response = '';
        }

        preg_match('#HTTP.* (?P<code>\d+)#', $header, $matches);
        $this->header = $header;
        $this->code = (int)(isset($matches['code']) ? $matches['code'] : null);

        $this->reset();

        return $response;
    }



    /**
     * Make HTTP request and handle response
     * 
     * @param string $url The request URL
     * @return string Response body
     * @throws Exception on curl errors
     */
    public function request2($url = '')
    {
        try {
            // Configure curl request
            curl_setopt($this->curl, CURLOPT_URL, $url);
            curl_setopt($this->curl, CURLOPT_VERBOSE, true);
            curl_setopt($this->curl, CURLOPT_HTTPHEADER, $this->requestHeaders);
            curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($this->curl, CURLOPT_HEADER, true);

            // Handle SSL verification settings
            if ($this->ignoreSSL) {
                curl_setopt($this->curl, CURLOPT_SSL_VERIFYHOST, 0);
                curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, 0);
                $this->ignoreSSL = false;
            }

            // Execute request
            $resp = curl_exec($this->curl);

            if ($resp === false) {
                throw new Exception('cURL Error: ' . curl_error($this->curl));
            }

            // Get response info and parse headers
            $info = curl_getinfo($this->curl);
            $headerSize = $info['header_size'];
            $header = substr($resp, 0, $headerSize);
            $body = substr($resp, $headerSize);

            // Parse response code
            preg_match('#HTTP.* (?P<code>\d+)#', $header, $matches);
            $this->header = $header;
            $this->code = (int)(isset($matches['code']) ? $matches['code'] : null);

            // Handle 417 Expectation Failed
            if ($info['http_code'] == 417) {
                // Retry without Expect header
                curl_setopt($this->curl, CURLOPT_HTTPHEADER, array_merge($this->requestHeaders, ["Expect:  "]));
                $resp = curl_exec($this->curl);
                $info = curl_getinfo($this->curl);

                // Re-parse response
                $headerSize = $info['header_size'];
                $header = substr($resp, 0, $headerSize);
                $body = substr($resp, $headerSize);

                preg_match('#HTTP.* (?P<code>\d+)#', $header, $matches);
                $this->header = $header;
                $this->code = (int)(isset($matches['code']) ? $matches['code'] : null);
            }

            // Clean up
            $this->reset();

            return $body;
        } catch (Exception $e) {
            throw $e;
        }
    }


    /**
     * @param array $options
     * @return CurlResource
     */
    public function setopt($options) // Removed array type hint
    {
        $this->initCurl();
        foreach ($options as $option => $value) {
            curl_setopt($this->curl, $option, $value);
        }

        return $this;
    }

    /**
     * @return CurlResource
     */
    public function ignoreSSL()
    {
        $this->ignoreSSL = true;

        return $this;
    }

    public function useRFC($rfc) // Removed int type hint
    {
        $this->rfc = $rfc;

        return $this;
    }

    /**
     * @param string $username
     * @param string $password
     * @return CurlResource
     */
    public function setBasicAuthentication($username, $password = '') // Removed string type hint
    {
        $this->initCurl();
        curl_setopt($this->curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($this->curl, CURLOPT_USERPWD, $username . ':' . $password);

        return $this;
    }

    /**
     * @param array $array
     * @return CurlResource
     */
    public function setHeaders($array = []) // Removed array type hint
    {
        $this->requestHeaders = array_merge($this->requestHeaders, $array);

        $this->initCurl();

        curl_setopt($this->curl, CURLOPT_HTTPHEADER, array_unique($this->requestHeaders));

        return $this;
    }

    /**
     * @param integer $timeout
     * @return CurlResource
     */
    public function setTimeout($timeout = 30) // Removed int type hint
    {
        $this->initCurl();
        curl_setopt($this->curl, CURLOPT_TIMEOUT, $timeout);

        return $this;
    }

    /**
     * @return boolean
     */
    private function initCurl()
    {
        if (is_resource($this->curl)) {
            return true;
        }

        $this->curl = curl_init();
        curl_setopt($this->curl, CURLINFO_HEADER_OUT, true);
        curl_setopt($this->curl, CURLOPT_HEADER, true);
        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->curl, CURLOPT_TIMEOUT, 60);

        return true;
    }

    /**
     * @return void
     */
    private function reset()
    {
        if (is_resource($this->curl)) {
            curl_close($this->curl);
            // reset headers
            $this->requestHeaders = array_merge($this->defaultHeaders, $this->config->getHttpHeaders());
        }
    }

    /**
     * @param string $url
     * @param array $params
     * @return mixed
     */
    private function get($url = '', $params = [])
    {
        if (is_array($params) && !empty($params)) {
            $url = rtrim($url, '?');
            $params = http_build_query($params, '', '&', $this->rfc);
            $url .= '?' . $params;
        }

        curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($this->curl, CURLOPT_HTTPGET, true);

        return $this->request2($url);
    }

    /**
     * @param string $url
     * @param mixed $params
     * @return mixed
     */
    private function post($url = '', $params = [])
    {
        if (is_array($params)) {
            $params = json_encode($params);
        }

        curl_setopt($this->curl, CURLOPT_POSTFIELDS, $params);
        curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($this->curl, CURLOPT_POST, true);

        return $this->request2($url);
    }

    /**
     * @param string $url
     * @param array $params
     * @return mixed
     */
    private function head($url = '', $params = [])
    {
        if (is_array($params)) {
            $params = http_build_query($params, '', '&', $this->rfc);
        }

        curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, 'HEAD');
        curl_setopt($this->curl, CURLOPT_POSTFIELDS, $params);

        return $this->request($url);
    }
}
