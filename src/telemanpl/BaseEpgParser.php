<?php

namespace telemanpl;


class BaseEpgParser
{
    protected $config = array(
        'baseUrl' => 'http://www.teleman.pl/program-tv/stacje/',
        'curlProxy' => false,//false or ip
        'curlTor' => false,
        'channelsUrl' => 'http://www.teleman.pl/moje-stacje',
        'curlTorPort' => null //set if curlTor is true(default 9050)
    );

    protected $channelParser;

    protected $curlOptions = array();
    protected $userCurlOptions = array();
    protected $curlError = null;
    protected $curlResult = null;
    protected $curlInfo = array();
    protected $errors = array();
    protected $curlObject = null;

    /**
     * @param array $config
     */
    public function __construct($config = array()) {
        if ($config) {
            foreach ($config as $key => $value) {
                $this->config[$key] = $value;
            }
        }
    }

    /**
     * @param mixed $key
     * @param mixed $val
     * @return $this
     */
    public function setCurlOption($key, $val) {
        $this->userCurlOptions[$key] = $val;
        return $this;
    }

    /**
     * @param string $url
     * @return $this
     */
    protected function initCurl($url) {
        $this->resetCurl();
        $this->curlOptions = $this->userCurlOptions;
        if (!$this->curlOptions || $this->curlOptions == $this->userCurlOptions) {
            $this->curlOptions[CURLOPT_USERAGENT] = "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/47.0.2526.106 Safari/537.36";
            $this->curlOptions[CURLOPT_TIMEOUT] = 60;
            $cookie = "epg_cookie.txt";
            $this->curlOptions[CURLOPT_COOKIEJAR] = $cookie;
            $this->curlOptions[CURLOPT_COOKIE] = $cookie;
            $this->curlOptions[CURLOPT_FOLLOWLOCATION] = 1;
            $this->curlOptions[CURLOPT_RETURNTRANSFER] = 1;
        }
        $urlParts = parse_url($url);
        if ($urlParts['scheme'] == 'https') {
            $this->curlOptions[CURLOPT_SSL_VERIFYHOST] = 0;
            $this->curlOptions[CURLOPT_SSL_VERIFYPEER] = 0;
        }
        $this->curlOptions[CURLOPT_URL] = $url;
        if ($this->config['curlProxy']) {
            $this->curlOptions[CURLOPT_PROXY] = $this->config['curlProxy'];
        } elseif ($this->config['curlTor']) {
            $this->setCurlTor();
        }
        if (!$this->curlObject) {
            $this->curlObject = curl_init($url);
        }
        return $this;
    }

    /**
     * @return $this
     */
    protected function resetCurl() {
        $this->curlObject = null;
        $this->curlOptions = array();
        $this->curlInfo = array();
        $this->curlResult = null;
        return $this;
    }

    /**
     * @return $this
     */
    protected function runCurl() {
        try {
            curl_setopt_array($this->curlObject, $this->curlOptions);
            $this->curlResult = curl_exec($this->curlObject);
            $this->curlError = curl_error($this->curlObject);
            $this->curlInfo = curl_getinfo($this->curlObject);
            curl_close($this->curlObject);
        } catch (\Exception $e) {
            $this->setError($e->getMessage());
            $this->curlError = $e->getMessage();
        }
        return $this;
    }

    /**
     * @return $this
     */
    protected function setCurlTor() {
        $this->curlOptions[CURLOPT_AUTOREFERER] = 1;
        $this->curlOptions[CURLOPT_RETURNTRANSFER] = 1;
        $this->curlOptions[CURLOPT_PROXY] = '127.0.0.1:' . ($this->config['curlTorPort'] ? (int)$this->config['curlTorPort'] : 9050);
        $this->curlOptions[CURLOPT_PROXYTYPE] = 7;
        $this->curlOptions[CURLOPT_TIMEOUT] = 120;
        $this->curlOptions[CURLOPT_VERBOSE] = 0;
        $this->curlOptions[CURLOPT_HEADER] = 0;
        return $this;
    }

    /**
     * @param $error
     * @return $this
     */
    public function setError($error) {
        $this->errors[] = $error;
        return $this;
    }

    /**
     * @return array
     */
    public function getCurlInfo() {
        return $this->curlInfo;
    }

    /**
     * @return array
     */
    public function getErrors() {
        return $this->errors;
    }

    /**
     * @return mixed|null
     */
    public function getLastError() {
        if ($this->errors) {
            return end($this->errors);
        }
        return null;
    }
}