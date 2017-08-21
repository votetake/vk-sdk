<?php


namespace VKSDK;


class VK
{
    /**
     * VK application id.
     * @var string
     */
    private $appId;
    /**
     * VK application secret key.
     * @var string
     */
    private $apiSecret;
    /**
     * API version. If null uses latest version.
     * @var int
     */
    private $apiVersion;
    /**
     * VK access token.
     * @var string
     */
    private $accessToken;
    /**
     * Authorization status.
     * @var bool
     */
    private $auth = false;
    /**
     * Instance curl.
     * @var resource
     */
    private $ch;

    const AUTHORIZE_URL    = 'https://oauth.vk.com/authorize';
    const ACCESS_TOKEN_URL = 'https://oauth.vk.com/access_token';

    /**
     * Constructor.
     * @param   string $appId
     * @param   string $apiSecret
     * @param   string $accessToken
     * @throws  \Exception
     */
    public function __construct($appId, $apiSecret, $accessToken = null)
    {
        $this->appId = $appId;
        $this->apiSecret = $apiSecret;
        if (!is_null($accessToken)) {
            $this->accessToken = $accessToken;
        }

        $this->ch = curl_init();
    }

    /**
     * Destructor.
     * @return  void
     */
    public function __destruct()
    {
        curl_close($this->ch);
    }

    /**
     * Set special API version.
     * @param   int $version
     * @return  void
     */
    public function setApiVersion($version)
    {
        $this->apiVersion = $version;
    }

    /**
     * Set Access Token.
     * @param   string $accessToken
     * @throws  \Exception
     * @return  void
     */
    public function setAccessToken($accessToken)
    {
        $this->auth = $this->checkAccessToken();
        if (!$this->auth) {
            throw new \Exception('Invalid access token.');
        } else {
            $this->accessToken = $accessToken;
        }
    }

    /**
     * Returns base API url.
     * @param   string $method
     * @param   string $responseFormat
     * @return  string
     */
    public function getApiUrl($method, $responseFormat = 'json')
    {
        return 'https://api.vk.com/method/' . $method . '.' . $responseFormat;
    }

    /**
     * Returns authorization link with passed parameters.
     * @param   string $apiSettings
     * @param   string $callbackUrl
     * @param   bool $testMode
     * @return  string
     */
    public function getAuthorizeUrl(
        $apiSettings = '',
        $callbackUrl = 'https://api.vk.com/blank.html',
        $testMode = false
    )
    {
        $parameters = array(
            'client_id'     => $this->appId,
            'scope'         => $apiSettings,
            'redirect_uri'  => $callbackUrl,
            'response_type' => 'code'
        );
        if ($testMode) {
            $parameters['test_mode'] = 1;
        }
        return $this->createUrl(self::AUTHORIZE_URL, $parameters);
    }

    /**
     * Returns access token by code received on authorization link.
     * @param   string $code
     * @param   string $callbackUrl
     * @throws  \Exception
     * @return  array
     */
    public function getAccessToken($code, $callbackUrl = 'https://api.vk.com/blank.html')
    {
        if (!is_null($this->accessToken) && $this->auth) {
            throw new \Exception('Already authorized.');
        }
        $parameters = array(
            'client_id'     => $this->appId,
            'client_secret' => $this->apiSecret,
            'code'          => $code,
            'redirect_uri'  => $callbackUrl
        );
        $rs = json_decode($this->request(
            $this->createUrl(self::ACCESS_TOKEN_URL, $parameters)), true);
        if (isset($rs['error'])) {
            throw new \Exception($rs['error'] .
            	(!isset($rs['error_description']) ?: ': ' . $rs['error_description']));
        } else {
            $this->auth = true;
            $this->accessToken = $rs['access_token'];
            return $rs;
        }
    }

    /**
     * Return user authorization status.
     * @return  bool
     */
    public function isAuth()
    {
        return $this->auth;
    }

    /**
     * Check for validity access token.
     * @return  bool
     */
    private function checkAccessToken()
    {
        if (is_null($this->accessToken)) {
            return false;
        }
        $rs = $this->api('getUserSettings');
        return isset($rs['response']);
    }

    /**
     * Execute API method with parameters and return result.
     * @param   string $method
     * @param   array $parameters
     * @param   string $format
     * @param   string $requestMethod
     * @return  mixed
     */
    public function api($method, $parameters = array(), $format = 'array', $requestMethod = 'get')
    {
        $parameters['timestamp'] = time();
        $parameters['api_id'] = $this->appId;
        $parameters['random'] = rand(0, 10000);
        $parameters['client_secret'] = $this->apiSecret;
        if (!is_null($this->accessToken)) {
            $parameters['access_token'] = $this->accessToken;
        }
        if (!is_null($this->apiVersion)) {
            $parameters['v'] = $this->apiVersion;
        }
        ksort($parameters);
        $sig = '';
        foreach ($parameters as $key => $value) {
            $sig .= $key . '=' . $value;
        }
        $sig .= $this->apiSecret;
        $parameters['sig'] = md5($sig);
        if ($method == 'execute' || $requestMethod == 'post') {
            $rs = $this->request(
                $this->getApiUrl($method, $format == 'array' ? 'json' : $format), "POST", $parameters);
        } else {
            $rs = $this->request($this->createUrl(
                $this->getApiUrl($method, $format == 'array' ? 'json' : $format), $parameters));
        }
        return $format == 'array' ? json_decode($rs, true) : $rs;
    }

    /**
     * Concatenate keys and values to url format and return url.
     * @param   string $url
     * @param   array $parameters
     * @return  string
     */
    private function createUrl($url, $parameters)
    {
        $url .= '?' . http_build_query($parameters);
        return $url;
    }

    /**
     * Executes request on link.
     * @param   string $url
     * @param   string $method
     * @param   array $postFields
     * @return  string
     */
    private function request($url, $method = 'GET', $postFields = array())
    {
        curl_setopt_array($this->ch, array(
            CURLOPT_USERAGENT      => 'VK/1.0 (+https://github.com/vladkens/VK))',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_POST           => ($method == 'POST'),
            CURLOPT_POSTFIELDS     => $postFields,
            CURLOPT_URL            => $url
        ));
        return curl_exec($this->ch);
    }
}