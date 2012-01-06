<?php

/**
 * Thrown when an API call returns an exception.
 */
class FacebookApiException extends Exception
{

	/**
	 * The result from the API server that represents the exception information.
	 */
	protected $result;

	/**
	 * Make a new API Exception with the given result.
	 *
	 * @param array $result The result from the API server
	 */
	public function __construct($result)
	{
		$this->result = $result;

		$code = isset($result['error_code']) ? $result['error_code'] : 0;

		if (isset($result['error_description']))
		{
			// OAuth 2.0 Draft 10 style
			$msg = $result['error_description'];
		}
		else if (isset($result['error']) && is_array($result['error']))
		{
			// OAuth 2.0 Draft 00 style
			$msg = $result['error']['message'];
		}
		else if (isset($result['error_msg']))
		{
			// Rest server style
			$msg = $result['error_msg'];
		}
		else
		{
			$msg = 'Unknown Error. Check getResult()';
		}

		parent::__construct($msg, $code);
	}

	/**
	 * Return the associated result object returned by the API server.
	 *
	 * @return array The result from the API server
	 */
	public function getResult()
	{
		return $this->result;
	}

	/**
	 * Returns the associated type for the error. This will default to
	 * 'Exception' when a type is not available.
	 *
	 * @return string
	 */
	public function getType()
	{
		if (isset($this->result['error']))
		{
			$error = $this->result['error'];
			if (is_string($error))
			{
				// OAuth 2.0 Draft 10 style
				return $error;
			}
			elseif (is_array($error))
			{
				// OAuth 2.0 Draft 00 style
				if (isset($error['type']))
				{
					return $error['type'];
				}
			}
		}

		return 'Exception';
	}

	/**
	 * To make debugging easier.
	 *
	 * @return string The string representation of the error
	 */
	public function __toString()
	{
		$str = $this->getType() . ': ';
		if ($this->code != 0)
		{
			$str .= $this->code . ': ';
		}

		return $str . $this->message;
	}

}

/**
 * FConnect class
 * Based on Fasebook SDK v.3.1.1
 */
class FConnect
{

	/**
	 * The Application ID.
	 *
	 * @var string
	 */
	public static $appId;

	/**
	 * The Application API Secret.
	 *
	 * @var string
	 */
	public static $secret;

	protected static $DOMAIN_MAP = array(
		'api' => 'https://api.facebook.com/',
		'graph' => 'https://graph.facebook.com/',
		'www' => 'https://www.facebook.com/',
	);

	/**
	 * Default options for curl.
	 */
	public static $CURL_OPTS = array(
		CURLOPT_CONNECTTIMEOUT => 10,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_TIMEOUT => 60,
		CURLOPT_USERAGENT => 'facebook-php-3.1',
	);

	/**
	 * List of query parameters that get automatically dropped when rebuilding
	 * the current URL.
	 */
	protected static $DROP_QUERY_PARAMS = array(
		'code',
		'state',
		'signed_request',
	);

	/**
	 * A CSRF state variable to assist in the defense against CSRF attacks.
	 */
	protected static $state;

	/**
	 * Keys to store in session
	 *
	 * @var array
	 */
	protected static $supportedKeys = array('state', 'code', 'access_token', 'user_id');

	/**
	 * The data from the signed_request token.
	 */
	protected static $signedRequest;

	/**
	 * The ID of the Facebook user, or 0 if the user is logged out.
	 *
	 * @var integer
	 */
	protected static $user;

	/**
	 * The OAuth access token received in exchange for a valid authorization
	 * code.  null means the access token has yet to be determined.
	 *
	 * @var string
	 */
	protected static $accessToken = null;

	public static function init($appId, $secret)
	{
		self::$appId = $appId;
		self::$secret = $secret;
		$state = self::getPersistentData('state');
		if (!empty($state))
		{
			self::$state = $state;
		}
	}

	/**
	 * Set the Application ID.
	 *
	 * @param string $appId The Application ID
	 */
	public static function setAppId($appId)
	{
		self::$appId = $appId;
	}

	/**
	 * Get the Application ID.
	 *
	 * @return string the Application ID
	 */
	public static function getAppId()
	{
		if (is_null(self::$appId))
		{
			self::setAppId(Yii::app()->facebookconnect->appId);
		}

		return self::$appId;
	}

	/**
	 * Set the API Secret.
	 *
	 * @param string $secret The API Secret
	 */
	public static function setSecret($secret)
	{
		self::$secret = $secret;
	}

	/**
	 * Get the API Secret.
	 *
	 * @return string the API Secret
	 */
	public static function getSecret()
	{
		if (is_null(self::$secret))
		{
			self::setSecret(Yii::app()->facebookconnect->secret);
		}

		return self::$secret;
	}

	/**
	 * Returns the access token that should be used for logged out
	 * users when no authorization code is available.
	 *
	 * @return string The application access token, useful for gathering
	 *                public information about users and applications.
	 */
	protected static function getApplicationAccessToken()
	{
		return self::$appId . '|' . self::$secret;
	}

	/**
	 * Lays down a CSRF state token for this process.
	 *
	 * @return void
	 */
	protected static function establishCSRFTokenState()
	{
		if (self::$state === null)
		{
			self::$state = md5(uniqid(mt_rand(), true));
			self::setPersistentData('state', self::$state);
		}
	}

	/**
	 * Retrieves an access token for the given authorization code
	 * (previously generated from www.facebook.com on behalf of
	 * a specific user).  The authorization code is sent to graph.facebook.com
	 * and a legitimate access token is generated provided the access token
	 * and the user for which it was generated all match, and the user is
	 * either logged in to Facebook or has granted an offline access permission.
	 *
	 * @param string $code An authorization code.
	 * @return mixed An access token exchanged for the authorization code, or
	 *               false if an access token could not be generated.
	 */
	protected static function getAccessTokenFromCode($code, $redirect_uri = null)
	{
		if (empty($code))
		{
			return false;
		}

		if ($redirect_uri === null)
		{
			$redirect_uri = self::getCurrentUrl();
		}

		try
		{
			// need to circumvent json_decode by calling _oauthRequest
			// directly, since response isn't JSON format.
			$access_token_response = self::_oauthRequest(self::getUrl('graph', '/oauth/access_token'), $params = array(
						'client_id' => self::getAppId(),
						'client_secret' => self::getSecret(),
						'redirect_uri' => $redirect_uri,
						'code' => $code
					));
		}
		catch (FacebookApiException $e)
		{
			// most likely that user very recently revoked authorization.
			// In any event, we don't have an access token, so say so.
			return false;
		}

		if (empty($access_token_response))
		{
			return false;
		}

		$response_params = array();
		parse_str($access_token_response, $response_params);
		if (!isset($response_params['access_token']))
		{
			return false;
		}

		return $response_params['access_token'];
	}

	protected static function setPersistentData($key, $value)
	{
		if (!in_array($key, self::$supportedKeys))
		{
			return;
		}

		$session_var_name = self::constructSessionVariableName($key);
		Yii::app()->session->add($session_var_name, $value);
	}

	protected static function getPersistentData($key, $default = false)
	{
		if (!in_array($key, self::$supportedKeys))
		{
			return $default;
		}

		$session_var_name = self::constructSessionVariableName($key);
		return Yii::app()->session->offsetExists($session_var_name) ? Yii::app()->session->get($session_var_name) : $default;
	}

	protected static function clearPersistentData($key)
	{
		if (!in_array($key, self::$supportedKeys))
		{
			return;
		}

		$session_var_name = self::constructSessionVariableName($key);
		Yii::app()->session->offsetUnset($session_var_name);
	}

	protected static function clearAllPersistentData()
	{
		foreach (self::$supportedKeys as $key)
		{
			self::clearPersistentData($key);
		}
	}

	protected static function constructSessionVariableName($key)
	{
		return implode('_', array(
			'fb',
			self::getAppId(),
			$key
		));
	}

	/**
	 * Get a Login URL for use with redirects. By default, full page redirect is
	 * assumed. If you are using the generated URL with a window.open() call in
	 * JavaScript, you can pass in display=popup as part of the $params.
	 *
	 * The parameters:
	 * - redirect_uri: the url to go to after a successful login
	 * - scope: comma separated list of requested extended perms
	 *
	 * @param array $params Provide custom parameters
	 * @return string The URL for the login flow
	 */
	public static function getLoginUrl($params=array())
	{
		self::establishCSRFTokenState();

		return self::getUrl('www', 'dialog/oauth', array_merge(
			array(
				'client_id' => self::getAppId(),
				'state' => self::$state
			), $params));
	}

	/**
	 * Build the URL for given domain alias, path and parameters.
	 *
	 * @param $name string The name of the domain
	 * @param $path string Optional path (without a leading slash)
	 * @param $params array Optional query parameters
	 *
	 * @return string The URL for the given parameters
	 */
	protected static function getUrl($name, $path='', $params=array())
	{
		$url = self::$DOMAIN_MAP[$name];
		if ($path)
		{
			if ($path[0] === '/')
			{
				$path = substr($path, 1);
			}
			$url .= $path;
		}
		if ($params)
		{
			$url .= '?' . http_build_query($params, null, '&');
		}

		return $url;
	}

	/**
	 * Get the UID of the connected user, or 0
	 * if the Facebook user is not connected.
	 *
	 * @return string the UID if available.
	 */
	public static function getUser()
	{
		if (self::$user !== null)
		{
			// we've already determined this and cached the value.
			return self::$user;
		}

		return self::$user = self::getUserFromAvailableData();
	}

	/**
	 * Determines the connected user by first examining any signed
	 * requests, then considering an authorization code, and then
	 * falling back to any persistent store storing the user.
	 *
	 * @return integer The id of the connected Facebook user,
	 *                 or 0 if no such user exists.
	 */
	protected static function getUserFromAvailableData()
	{
		// if a signed request is supplied, then it solely determines
		// who the user is.
		$signed_request = self::getSignedRequest();
		if ($signed_request)
		{
			if (array_key_exists('user_id', $signed_request))
			{
				$user = $signed_request['user_id'];
				self::setPersistentData('user_id', $signed_request['user_id']);
				return $user;
			}

			// if the signed request didn't present a user id, then invalidate
			// all entries in any persistent store.
			self::clearAllPersistentData();
			return 0;
		}

		$user = self::getPersistentData('user_id', $default = 0);
		$persisted_access_token = self::getPersistentData('access_token');

		// use access_token to fetch user id if we have a user access_token, or if
		// the cached access token has changed.
		$access_token = self::getAccessToken();
		if ($access_token && $access_token != self::getApplicationAccessToken() && !($user && $persisted_access_token == $access_token))
		{
			$user = self::getUserFromAccessToken();
			if ($user)
			{
				self::setPersistentData('user_id', $user);
			}
			else
			{
				self::clearAllPersistentData();
			}
		}

		return $user;
	}

	/**
	 * Retrieve the signed request, either from a request parameter or,
	 * if not present, from a cookie.
	 *
	 * @return string the signed request, if available, or null otherwise.
	 */
	public static function getSignedRequest()
	{
		if (!self::$signedRequest)
		{
			if (isset($_REQUEST['signed_request']))
			{
				self::$signedRequest = self::parseSignedRequest($_REQUEST['signed_request']);
			}
			elseif (isset($_COOKIE[self::getSignedRequestCookieName()]))
			{
				self::$signedRequest = self::parseSignedRequest($_COOKIE[self::getSignedRequestCookieName()]);
			}
		}

		return self::$signedRequest;
	}

	/**
	 * Make an API call.
	 *
	 * @return mixed The decoded response
	 */
	public static function api(/* polymorphic */)
	{
		$args = func_get_args();

		return call_user_func_array(array(__CLASS__, '_graph'), $args);
	}

	/**
	 * Constructs and returns the name of the cookie that
	 * potentially houses the signed request for the app user.
	 *
	 * @return string the name of the cookie that would house
	 *         the signed request value.
	 */
	protected static function getSignedRequestCookieName()
	{
		return 'fbsr_' . self::getAppId();
	}

	/**
	 * Get the authorization code from the query parameters, if it exists,
	 * and otherwise return false to signal no authorization code was
	 * discoverable.
	 *
	 * @return mixed The authorization code, or false if the authorization
	 *               code could not be determined.
	 */
	protected static function getCode()
	{
		if (isset($_REQUEST['code']))
		{
			if (self::$state !== null && isset($_REQUEST['state']) && self::$state === $_REQUEST['state'])
			{
				// CSRF state has done its job, so clear it
				self::$state = null;
				self::clearPersistentData('state');
				return $_REQUEST['code'];
			}
			else
			{
				return false;
			}
		}

		return false;
	}

	/**
	 * Retrieves the UID with the understanding that
	 * $accessToken has already been set and is
	 * seemingly legitimate.  It relies on Facebook's Graph API
	 * to retrieve user information and then extract
	 * the user ID.
	 *
	 * @return integer Returns the UID of the Facebook user, or 0
	 *                 if the Facebook user could not be determined.
	 */
	protected static function getUserFromAccessToken()
	{
		try
		{
			$user_info = self::api('/me');
			return $user_info['id'];
		}
		catch (FacebookApiException $e)
		{
			return 0;
		}
	}

	/**
	 * Sets the access token for api calls.  Use this if you get
	 * your access token by other means and just want the SDK
	 * to use it.
	 *
	 * @param string $access_token an access token.
	 */
	public static function setAccessToken($access_token)
	{
		self::$accessToken = $access_token;
	}

	/**
	 * Determines the access token that should be used for API calls.
	 * The first time this is called, $accessToken is set equal
	 * to either a valid user access token, or it's set to the application
	 * access token if a valid user access token wasn't available.  Subsequent
	 * calls return whatever the first call returned.
	 *
	 * @return string The access token
	 */
	public static function getAccessToken()
	{
		if (self::$accessToken !== null)
		{
			// we've done this already and cached it.  Just return.
			return self::$accessToken;
		}

		// first establish access token to be the application
		// access token, in case we navigate to the /oauth/access_token
		// endpoint, where SOME access token is required.
		self::setAccessToken(self::getApplicationAccessToken());
		$user_access_token = self::getUserAccessToken();
		if ($user_access_token)
		{
			self::setAccessToken($user_access_token);
		}

		return self::$accessToken;
	}

	/**
	 * Determines and returns the user access token, first using
	 * the signed request if present, and then falling back on
	 * the authorization code if present.  The intent is to
	 * return a valid user access token, or false if one is determined
	 * to not be available.
	 *
	 * @return string A valid user access token, or false if one
	 *                could not be determined.
	 */
	protected static function getUserAccessToken()
	{
		// first, consider a signed request if it's supplied.
		// if there is a signed request, then it alone determines
		// the access token.
		$signed_request = self::getSignedRequest();
		if ($signed_request)
		{
			// apps.facebook.com hands the access_token in the signed_request
			if (array_key_exists('oauth_token', $signed_request))
			{
				$access_token = $signed_request['oauth_token'];
				self::setPersistentData('access_token', $access_token);
				return $access_token;
			}

			// the JS SDK puts a code in with the redirect_uri of ''
			if (array_key_exists('code', $signed_request))
			{
				$code = $signed_request['code'];
				$access_token = self::getAccessTokenFromCode($code, '');
				if ($access_token)
				{
					self::setPersistentData('code', $code);
					self::setPersistentData('access_token', $access_token);
					return $access_token;
				}
			}

			// signed request states there's no access token, so anything
			// stored should be cleared.
			self::clearAllPersistentData();
			return false; // respect the signed request's data, even
			// if there's an authorization code or something else
		}

		$code = self::getCode();
		if ($code && $code != self::getPersistentData('code'))
		{
			$access_token = self::getAccessTokenFromCode($code);
			if ($access_token)
			{
				self::setPersistentData('code', $code);
				self::setPersistentData('access_token', $access_token);
				return $access_token;
			}

			// code was bogus, so everything based on it should be invalidated.
			self::clearAllPersistentData();
			return false;
		}

		// as a fallback, just return whatever is in the persistent
		// store, knowing nothing explicit (signed request, authorization
		// code, etc.) was present to shadow it (or we saw a code in $_REQUEST,
		// but it's the same as what's in the persistent store)
		return self::getPersistentData('access_token');
	}

	/**
	 * Invoke the Graph API.
	 *
	 * @param string $path The path (required)
	 * @param string $method The http method (default 'GET')
	 * @param array $params The query/post data
	 *
	 * @return mixed The decoded response object
	 * @throws FacebookApiException
	 */
	protected static function _graph($path, $method = 'GET', $params = array())
	{
		if (is_array($method) && empty($params))
		{
			$params = $method;
			$method = 'GET';
		}

		$params['method'] = $method; // method override as we always do a POST

		$result = json_decode(self::_oauthRequest(self::getUrl('graph', $path), $params), true);

		// results are returned, errors are thrown
		if (is_array($result) && isset($result['error']))
		{
			self::throwAPIException($result);
		}

		return $result;
	}

	/**
	 * Make a OAuth Request.
	 *
	 * @param string $url The path (required)
	 * @param array $params The query/post data
	 *
	 * @return string The decoded response object
	 * @throws FacebookApiException
	 */
	protected static function _oauthRequest($url, $params)
	{
		if (!isset($params['access_token']))
		{
			$params['access_token'] = self::getAccessToken();
		}

		// json_encode all params values that are not strings
		foreach ($params as $key => $value)
		{
			if (!is_string($value))
			{
				$params[$key] = json_encode($value);
			}
		}

		return self::makeRequest($url, $params);
	}

	/**
	 * Makes an HTTP request. This method can be overridden by subclasses if
	 * developers want to do fancier things or use something other than curl to
	 * make the request.
	 *
	 * @param string $url The URL to make the request to
	 * @param array $params The parameters to use for the POST body
	 * @param CurlHandler $ch Initialized curl handle
	 *
	 * @return string The response text
	 */
	protected static function makeRequest($url, $params, $ch=null)
	{
		if (!$ch)
		{
			$ch = curl_init();
		}

		$opts = self::$CURL_OPTS;
		$opts[CURLOPT_POSTFIELDS] = http_build_query($params, null, '&');
		$opts[CURLOPT_URL] = $url;

		// disable the 'Expect: 100-continue' behaviour. This causes CURL to wait
		// for 2 seconds if the server does not support this header.
		if (isset($opts[CURLOPT_HTTPHEADER]))
		{
			$existing_headers = $opts[CURLOPT_HTTPHEADER];
			$existing_headers[] = 'Expect:';
			$opts[CURLOPT_HTTPHEADER] = $existing_headers;
		}
		else
		{
			$opts[CURLOPT_HTTPHEADER] = array('Expect:');
		}

		curl_setopt_array($ch, $opts);
		$result = curl_exec($ch);

		if (curl_errno($ch) == 60)
		{ // CURLE_SSL_CACERT
			curl_setopt($ch, CURLOPT_CAINFO, dirname(__FILE__) . '/fb_ca_chain_bundle.crt');
			$result = curl_exec($ch);
		}

		if ($result === false)
		{
			$e = new FacebookApiException(array(
				'error_code' => curl_errno($ch),
				'error' => array(
					'message' => curl_error($ch),
					'type' => 'CurlException',
				),
			));
			curl_close($ch);
			throw $e;
		}

		curl_close($ch);

		return $result;
	}

	/**
	 * Parses a signed_request and validates the signature.
	 *
	 * @param string $signed_request A signed token
	 * @return array The payload inside it or null if the sig is wrong
	 */
	protected static function parseSignedRequest($signed_request)
	{
		list($encoded_sig, $payload) = explode('.', $signed_request, 2);

		// decode the data
		$sig = self::base64UrlDecode($encoded_sig);
		$data = json_decode(self::base64UrlDecode($payload), true);

		if (strtoupper($data['algorithm']) !== 'HMAC-SHA256')
		{
			return null;
		}

		// check sig
		$expected_sig = hash_hmac('sha256', $payload, self::getSecret(), $raw = true);
		if ($sig !== $expected_sig)
		{
			return null;
		}

		return $data;
	}

	/**
	 * Base64 encoding that doesn't need to be urlencode()ed.
	 * Exactly the same as base64_encode except it uses
	 *   - instead of +
	 *   _ instead of /
	 *
	 * @param string $input base64UrlEncoded string
	 * @return string
	 */
	protected static function base64UrlDecode($input)
	{
		return base64_decode(strtr($input, '-_', '+/'));
	}

	/**
	 * Returns the Current URL, stripping it of known FB parameters that should
	 * not persist.
	 *
	 * @return string The current URL
	 */
	protected static function getCurrentUrl()
	{
		if (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] == 'on' || $_SERVER['HTTPS'] == 1) || isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')
		{
			$protocol = 'https://';
		}
		else
		{
			$protocol = 'http://';
		}

		$currentUrl = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
		$parts = parse_url($currentUrl);

		$query = '';
		if (!empty($parts['query']))
		{
			// drop known fb params
			$params = explode('&', $parts['query']);
			$retained_params = array();
			foreach ($params as $param)
			{
				if (self::shouldRetainParam($param))
				{
					$retained_params[] = $param;
				}
			}

			if (!empty($retained_params))
			{
				$query = '?' . implode($retained_params, '&');
			}
		}

		// use port if non default
		$port = isset($parts['port']) && (($protocol === 'http://' && $parts['port'] !== 80) || ($protocol === 'https://' && $parts['port'] !== 443)) ? ':' . $parts['port'] : '';

		// rebuild
		return $protocol . $parts['host'] . $port . $parts['path'] . $query;
	}

	/**
	 * Returns true if and only if the key or key/value pair should
	 * be retained as part of the query string.  This amounts to
	 * a brute-force search of the very small list of Facebook-specific
	 * params that should be stripped out.
	 *
	 * @param string $param A key or key/value pair within a URL's query (e.g.
	 *                     'foo=a', 'foo=', or 'foo'.
	 *
	 * @return boolean
	 */
	protected static function shouldRetainParam($param)
	{
		foreach (self::$DROP_QUERY_PARAMS as $drop_query_param)
		{
			if (strpos($param, $drop_query_param . '=') === 0)
			{
				return false;
			}
		}

		return true;
	}

	/**
	 * Analyzes the supplied result to see if it was thrown
	 * because the access token is no longer valid.  If that is
	 * the case, then the persistent store is cleared.
	 *
	 * @param $result array A record storing the error message returned
	 *                      by a failed API call.
	 */
	protected static function throwAPIException($result)
	{
		$e = new FacebookApiException($result);
		switch ($e->getType())
		{
			// OAuth 2.0 Draft 00 style
			case 'OAuthException':
			// OAuth 2.0 Draft 10 style
			case 'invalid_token':
			// REST server errors are just Exceptions
			case 'Exception':
				$message = $e->getMessage();
				if ((strpos($message, 'Error validating access token') !== false) || (strpos($message, 'Invalid OAuth access token') !== false))
				{
					self::setAccessToken(null);
					self::$user = 0;
					self::clearAllPersistentData();
				}
		}

		throw $e;
	}

	/**
	 * Logout user and clear all facebook session data
	 */
	public static function logout()
	{
		self::clearAllPersistentData();
		Yii::app()->user->logout();
	}

	/**
	 * Returns info about logged in user
	 *
	 * @return array
	 */
	public static function getUserInfo()
	{
		if (self::$user)
			return self::api("/me");

		return false;
	}

}