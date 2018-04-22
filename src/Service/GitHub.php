<?php
/**
 * @author CloudRail (licobo GmbH) <dev@cloudrail.com>
 * @copyright 2016 licobo GmbH
 * @license http://cloudrail.com/licenses/LICENSE.txt
 * @link https://docs.cloudrail.com Documentation
 * @link http://cloudrail.com
 */

namespace CloudRail\Service;

use CloudRail\Error\AuthenticationError;
use CloudRail\Error\HttpError;
use CloudRail\Error\IllegalArgumentError;
use CloudRail\Error\NotFoundError;
use CloudRail\Error\ServiceUnavailableError;
use CloudRail\ServiceCode\Sandbox;
use CloudRail\Statistics;
use CloudRail\ServiceCode\InitSelfTest;
use CloudRail\ServiceCode\Interpreter;

use CloudRail\Interfaces\Profile;
use CloudRail\Type\DateOfBirth;
use CloudRail\Interfaces\AdvancedRequestSupporter;
use CloudRail\Type\AdvancedRequestSpecification;
use CloudRail\Type\AdvancedRequestResponse;
use CloudRail\Type\CloudRailError;

class GitHub implements Profile, AdvancedRequestSupporter
{
	private static $SERVICE_CODE = [
		'init' => [
			["if==than", '$P0.scopes', NULL, 2],
			["set", '$P0.scope', "user%3Aemail"],
			["jumpRel", 10],
			["create", '$P0.scope', "String"],
			["size", '$L0', '$P0.scopes'],
			["create", '$L1', "Number", 0],
			["if<than", '$L1', '$L0', 6],
			["if!=than", '$L1', 0, 1],
			["string.concat", '$P0.scope', '$P0.scope', "%20"],
			["get", '$L2', '$P0.scopes', '$L1'],
			["string.concat", '$P0.scope', '$P0.scope', '$L2'],
			["math.add", '$L1', '$L1', 1],
			["jumpRel", -7]
		],
		'AdvancedRequestSupporter:advancedRequest' => [
			["create", '$L0', "Object"],
			["create", '$L0.url', "String"],
			["if!=than", '$P2.appendBaseUrl', 0, 1],
			["set", '$L0.url', "https://api.github.com"],
			["string.concat", '$L0.url', '$L0.url', '$P2.url'],
			["set", '$L0.requestHeaders', '$P2.headers'],
			["if==than", '$L0.requestHeaders.User-Agent', NULL, 1],
			["set", '$L0.requestHeaders.User-Agent', "CloudRailSI"],
			["set", '$L0.method', '$P2.method'],
			["set", '$L0.requestBody', '$P2.body'],
			["if!=than", '$P2.appendAuthorization', 0, 2],
			["callFunc", "checkAuthentication", '$P0'],
			["string.concat", '$L0.requestHeaders.Authorization', "Bearer ", '$S0.accessToken'],
			["http.requestCall", '$L1', '$L0'],
			["if!=than", '$P2.checkErrors', 0, 1],
			["callFunc", "validateResponse", '$P0', '$L1'],
			["create", '$P1', "AdvancedRequestResponse"],
			["set", '$P1.status', '$L1.code'],
			["set", '$P1.headers', '$L1.responseHeaders'],
			["set", '$P1.body', '$L1.responseBody']
		],
		'getGHIdentifier' => [
			["callFunc", "checkAuthentication", '$P0'],
			["if==than", '$P0.cachedObject', NULL, 2],
			["callFunc", "makeHTTPRequest", '$P0'],
			["jumpRel", 1],
			["callFunc", "checkExpirationTime", '$P0'],
			["string.concat", '$L2', '$P0.cachedObject.id', ""],
			["string.concat", '$P1', "github-", '$L2']
		],
		'getGHFullName' => [
			["callFunc", "checkAuthentication", '$P0'],
			["if==than", '$P0.cachedObject', NULL, 2],
			["callFunc", "makeHTTPRequest", '$P0'],
			["jumpRel", 1],
			["callFunc", "checkExpirationTime", '$P0'],
			["set", '$P1', '$P0.cachedObject.name']
		],
		'getGHEmail' => [
			["callFunc", "checkAuthentication", '$P0'],
			["if==than", '$P0.cachedObject', NULL, 2],
			["callFunc", "makeHTTPRequest", '$P0'],
			["jumpRel", 1],
			["callFunc", "checkExpirationTime", '$P0'],
			["set", '$P1', '$P0.cachedObject.email']
		],
		'getGHGender' => [
			["set", '$P1', NULL]
		],
		'getGHDescription' => [
			["callFunc", "checkAuthentication", '$P0'],
			["if==than", '$P0.cachedObject', NULL, 2],
			["callFunc", "makeHTTPRequest", '$P0'],
			["jumpRel", 1],
			["callFunc", "checkExpirationTime", '$P0'],
			["set", '$P1', '$P0.cachedObject.bio']
		],
		'getGHDateOfBirth' => [
			["create", '$P1', "DateOfBirth"]
		],
		'getGHLocale' => [
			["set", '$P1', NULL]
		],
		'getGHPictureURL' => [
			["callFunc", "checkAuthentication", '$P0'],
			["if==than", '$P0.cachedObject', NULL, 2],
			["callFunc", "makeHTTPRequest", '$P0'],
			["jumpRel", 1],
			["callFunc", "checkExpirationTime", '$P0'],
			["set", '$P1', '$P0.cachedObject.avatar_url']
		],
		'loginGH' => [
			["callFunc", "checkAuthentication", '$P0']
		],
		'logoutGH' => [
			["set", '$S0.accessToken', NULL],
			["set", '$P0.cachedObject', NULL]
		],
		'makeHTTPRequest' => [
			["create", '$L0', "Object"],
			["set", '$L0.method', "GET"],
			["set", '$L0.url', "https://api.github.com/user"],
			["create", '$L0.requestHeaders', "Object"],
			["string.concat", '$L0.requestHeaders.Authorization', "token ", '$S0.accessToken'],
			["set", '$L0.requestHeaders.User-Agent', "CloudRailSI"],
			["create", '$L1', "Object"],
			["http.requestCall", '$L1', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L1'],
			["json.parse", '$P0.cachedObject', '$L1.responseBody'],
			["create", '$L2', "Date"],
			["set", '$P0.readTime', '$L2.time']
		],
		'checkExpirationTime' => [
			["create", '$L0', "Date"],
			["math.multiply", '$L1', '$P0.readTime', -1],
			["math.add", '$L2', '$L0.time', '$L1'],
			["if>than", '$L2', 60000, 1],
			["callFunc", "makeHTTPRequest", '$P0']
		],
		'checkAuthentication' => [
			["if==than", '$S0.accessToken', NULL, 2],
			["callFunc", "authenticate", '$P0'],
			["return"]
		],
		'authenticate' => [
			["create", '$L0', "String"],
			["string.concat", '$L0', "https://github.com/login/oauth/authorize?client_id=", '$P0.clientId', "&redirect_uri=", '$P0.redirectUri', "&state=", '$P0.state', "&scope=", '$P0.scope'],
			["awaitCodeRedirect", '$L1', '$L0', NULL, '$P0.redirectUri'],
			["string.concat", '$L2', "client_id=", '$P0.clientId', "&client_secret=", '$P0.clientSecret', "&code=", '$L1'],
			["stream.stringToStream", '$L3', '$L2'],
			["create", '$L4', "Object"],
			["set", '$L4.Accept', "application/json"],
			["set", '$L4', "application/x-www-form-urlencoded", "Content-Type"],
			["create", '$L5', "Object"],
			["set", '$L5.url', "https://github.com/login/oauth/access_token"],
			["set", '$L5.method', "POST"],
			["set", '$L5.requestBody', '$L3'],
			["set", '$L5.requestHeaders', '$L4'],
			["http.requestCall", '$L6', '$L5'],
			["callFunc", "validateResponse", '$P0', '$L6'],
			["create", '$L7', "String"],
			["stream.streamToString", '$L7', '$L6.responseBody'],
			["json.parse", '$L8', '$L7'],
			["set", '$S0.accessToken', '$L8.access_token']
		],
		'validateResponse' => [
			["if>=than", '$P1.code', 400, 13],
			["json.parse", '$L0', '$P1.responseBody'],
			["set", '$L2', '$L0.message'],
			["if==than", '$P1.code', 401, 2],
			["create", '$L3', "Error", '$L2', "Authentication"],
			["throwError", '$L3'],
			["if==than", '$P1.code', 404, 2],
			["create", '$L3', "Error", '$L2', "NotFound"],
			["throwError", '$L3'],
			["if==than", '$P1.code', 503, 2],
			["create", '$L3', "Error", '$L2', "ServiceUnavailable"],
			["throwError", '$L3'],
			["create", '$L3', "Error", '$L2', "Http"],
			["throwError", '$L3']
		]
	];

	/** @var mixed[] */
	private $interpreterStorage;

	/** @var mixed[] */
	private $instanceDependencyStorage;

	/** @var mixed[] */
	private $persistentStorage;
	
	
	/**
	 * 
	 * @param string $clientId
	 * @param string $clientSecret
	 * @param string $redirectUri
	 * @param string $state
	 * @param array $scopes
	 */
	public function __construct(callable $redirectReceiver, string $clientId, string $clientSecret, string $redirectUri, string $state, array $scopes=null)
	{
		$this->interpreterStorage = array();
		$this->instanceDependencyStorage = ["redirectReceiver" => $redirectReceiver];
		$this->persistentStorage = array(array());
		InitSelfTest::initTest('GitHub');
		
		$this->interpreterStorage['clientId'] = $clientId;
		$this->interpreterStorage['clientSecret'] = $clientSecret;
		$this->interpreterStorage['redirectUri'] = $redirectUri;
		$this->interpreterStorage['state'] = $state;
		$this->interpreterStorage['scopes'] = $scopes;
		

		$ip = new Interpreter(new Sandbox(GitHub::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		if (array_key_exists("init",GitHub::$SERVICE_CODE)) {
			$parameters = [&$this->interpreterStorage];
		  $ip->callFunctionSync("init",$parameters );
		}
	}

	
	/**
	 * @throws IllegalArgumentError
	 * @throws AuthenticationError
	 * @throws NotFoundError
	 * @throws HttpError
	 * @throws ServiceUnavailableError
	 * @throws \Exception
	 * @return string
	 */
	public function getIdentifier():string {
		Statistics::addCall("GitHub", "getIdentifier");
		$ip = new Interpreter(new Sandbox(GitHub::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null];
		$ip->callFunctionSync('getGHIdentifier', $auxArray);
		$error = $ip->getError();
		if (!is_null($error)) {
			if ($error->getType() == CloudRailError::ILLEGAL_ARGUMENT) {
				throw new IllegalArgumentError($error->getMessage());
			} else if ($error->getType() == CloudRailError::AUTHENTICATION) {
				throw new AuthenticationError($error->getMessage());
			} else if ($error->getType() == CloudRailError::NOT_FOUND) {
				throw new NotFoundError($error->getMessage());
			} else if ($error->getType() == CloudRailError::HTTP) {
				throw new HttpError($error->getMessage());
			} else if ($error->getType() == CloudRailError::SERVICE_UNAVAILABLE) {
				throw new ServiceUnavailableError($error->getMessage());
			} else {
				throw new \Exception($error->getMessage());
			}
		}
		return $ip->getParameter(1);
	}
	
	/**
	 * @throws IllegalArgumentError
	 * @throws AuthenticationError
	 * @throws NotFoundError
	 * @throws HttpError
	 * @throws ServiceUnavailableError
	 * @throws \Exception
	 * @return string
	 */
	public function getFullName():string {
		Statistics::addCall("GitHub", "getFullName");
		$ip = new Interpreter(new Sandbox(GitHub::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null];
		$ip->callFunctionSync('getGHFullName', $auxArray);
		$error = $ip->getError();
		if (!is_null($error)) {
			if ($error->getType() == CloudRailError::ILLEGAL_ARGUMENT) {
				throw new IllegalArgumentError($error->getMessage());
			} else if ($error->getType() == CloudRailError::AUTHENTICATION) {
				throw new AuthenticationError($error->getMessage());
			} else if ($error->getType() == CloudRailError::NOT_FOUND) {
				throw new NotFoundError($error->getMessage());
			} else if ($error->getType() == CloudRailError::HTTP) {
				throw new HttpError($error->getMessage());
			} else if ($error->getType() == CloudRailError::SERVICE_UNAVAILABLE) {
				throw new ServiceUnavailableError($error->getMessage());
			} else {
				throw new \Exception($error->getMessage());
			}
		}
		return $ip->getParameter(1);
	}
	
	/**
	 * @throws IllegalArgumentError
	 * @throws AuthenticationError
	 * @throws NotFoundError
	 * @throws HttpError
	 * @throws ServiceUnavailableError
	 * @throws \Exception
	 * @return string
	 */
	public function getEmail():string {
		Statistics::addCall("GitHub", "getEmail");
		$ip = new Interpreter(new Sandbox(GitHub::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null];
		$ip->callFunctionSync('getGHEmail', $auxArray);
		$error = $ip->getError();
		if (!is_null($error)) {
			if ($error->getType() == CloudRailError::ILLEGAL_ARGUMENT) {
				throw new IllegalArgumentError($error->getMessage());
			} else if ($error->getType() == CloudRailError::AUTHENTICATION) {
				throw new AuthenticationError($error->getMessage());
			} else if ($error->getType() == CloudRailError::NOT_FOUND) {
				throw new NotFoundError($error->getMessage());
			} else if ($error->getType() == CloudRailError::HTTP) {
				throw new HttpError($error->getMessage());
			} else if ($error->getType() == CloudRailError::SERVICE_UNAVAILABLE) {
				throw new ServiceUnavailableError($error->getMessage());
			} else {
				throw new \Exception($error->getMessage());
			}
		}
		return $ip->getParameter(1);
	}
	
	/**
	 * @throws IllegalArgumentError
	 * @throws AuthenticationError
	 * @throws NotFoundError
	 * @throws HttpError
	 * @throws ServiceUnavailableError
	 * @throws \Exception
	 * @return string
	 */
	public function getGender():string {
		Statistics::addCall("GitHub", "getGender");
		$ip = new Interpreter(new Sandbox(GitHub::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null];
		$ip->callFunctionSync('getGHGender', $auxArray);
		$error = $ip->getError();
		if (!is_null($error)) {
			if ($error->getType() == CloudRailError::ILLEGAL_ARGUMENT) {
				throw new IllegalArgumentError($error->getMessage());
			} else if ($error->getType() == CloudRailError::AUTHENTICATION) {
				throw new AuthenticationError($error->getMessage());
			} else if ($error->getType() == CloudRailError::NOT_FOUND) {
				throw new NotFoundError($error->getMessage());
			} else if ($error->getType() == CloudRailError::HTTP) {
				throw new HttpError($error->getMessage());
			} else if ($error->getType() == CloudRailError::SERVICE_UNAVAILABLE) {
				throw new ServiceUnavailableError($error->getMessage());
			} else {
				throw new \Exception($error->getMessage());
			}
		}
		return $ip->getParameter(1);
	}
	
	/**
	 * @throws IllegalArgumentError
	 * @throws AuthenticationError
	 * @throws NotFoundError
	 * @throws HttpError
	 * @throws ServiceUnavailableError
	 * @throws \Exception
	 * @return string
	 */
	public function getDescription():string {
		Statistics::addCall("GitHub", "getDescription");
		$ip = new Interpreter(new Sandbox(GitHub::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null];
		$ip->callFunctionSync('getGHDescription', $auxArray);
		$error = $ip->getError();
		if (!is_null($error)) {
			if ($error->getType() == CloudRailError::ILLEGAL_ARGUMENT) {
				throw new IllegalArgumentError($error->getMessage());
			} else if ($error->getType() == CloudRailError::AUTHENTICATION) {
				throw new AuthenticationError($error->getMessage());
			} else if ($error->getType() == CloudRailError::NOT_FOUND) {
				throw new NotFoundError($error->getMessage());
			} else if ($error->getType() == CloudRailError::HTTP) {
				throw new HttpError($error->getMessage());
			} else if ($error->getType() == CloudRailError::SERVICE_UNAVAILABLE) {
				throw new ServiceUnavailableError($error->getMessage());
			} else {
				throw new \Exception($error->getMessage());
			}
		}
		return $ip->getParameter(1);
	}
	
	/**
	 * @throws IllegalArgumentError
	 * @throws AuthenticationError
	 * @throws NotFoundError
	 * @throws HttpError
	 * @throws ServiceUnavailableError
	 * @throws \Exception
	 * @return DateOfBirth
	 */
	public function getDateOfBirth():DateOfBirth {
		Statistics::addCall("GitHub", "getDateOfBirth");
		$ip = new Interpreter(new Sandbox(GitHub::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null];
		$ip->callFunctionSync('getGHDateOfBirth', $auxArray);
		$error = $ip->getError();
		if (!is_null($error)) {
			if ($error->getType() == CloudRailError::ILLEGAL_ARGUMENT) {
				throw new IllegalArgumentError($error->getMessage());
			} else if ($error->getType() == CloudRailError::AUTHENTICATION) {
				throw new AuthenticationError($error->getMessage());
			} else if ($error->getType() == CloudRailError::NOT_FOUND) {
				throw new NotFoundError($error->getMessage());
			} else if ($error->getType() == CloudRailError::HTTP) {
				throw new HttpError($error->getMessage());
			} else if ($error->getType() == CloudRailError::SERVICE_UNAVAILABLE) {
				throw new ServiceUnavailableError($error->getMessage());
			} else {
				throw new \Exception($error->getMessage());
			}
		}
		return $ip->getParameter(1);
	}
	
	/**
	 * @throws IllegalArgumentError
	 * @throws AuthenticationError
	 * @throws NotFoundError
	 * @throws HttpError
	 * @throws ServiceUnavailableError
	 * @throws \Exception
	 * @return string
	 */
	public function getLocale():string {
		Statistics::addCall("GitHub", "getLocale");
		$ip = new Interpreter(new Sandbox(GitHub::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null];
		$ip->callFunctionSync('getGHLocale', $auxArray);
		$error = $ip->getError();
		if (!is_null($error)) {
			if ($error->getType() == CloudRailError::ILLEGAL_ARGUMENT) {
				throw new IllegalArgumentError($error->getMessage());
			} else if ($error->getType() == CloudRailError::AUTHENTICATION) {
				throw new AuthenticationError($error->getMessage());
			} else if ($error->getType() == CloudRailError::NOT_FOUND) {
				throw new NotFoundError($error->getMessage());
			} else if ($error->getType() == CloudRailError::HTTP) {
				throw new HttpError($error->getMessage());
			} else if ($error->getType() == CloudRailError::SERVICE_UNAVAILABLE) {
				throw new ServiceUnavailableError($error->getMessage());
			} else {
				throw new \Exception($error->getMessage());
			}
		}
		return $ip->getParameter(1);
	}
	
	/**
	 * @throws IllegalArgumentError
	 * @throws AuthenticationError
	 * @throws NotFoundError
	 * @throws HttpError
	 * @throws ServiceUnavailableError
	 * @throws \Exception
	 * @return string
	 */
	public function getPictureURL():string {
		Statistics::addCall("GitHub", "getPictureURL");
		$ip = new Interpreter(new Sandbox(GitHub::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null];
		$ip->callFunctionSync('getGHPictureURL', $auxArray);
		$error = $ip->getError();
		if (!is_null($error)) {
			if ($error->getType() == CloudRailError::ILLEGAL_ARGUMENT) {
				throw new IllegalArgumentError($error->getMessage());
			} else if ($error->getType() == CloudRailError::AUTHENTICATION) {
				throw new AuthenticationError($error->getMessage());
			} else if ($error->getType() == CloudRailError::NOT_FOUND) {
				throw new NotFoundError($error->getMessage());
			} else if ($error->getType() == CloudRailError::HTTP) {
				throw new HttpError($error->getMessage());
			} else if ($error->getType() == CloudRailError::SERVICE_UNAVAILABLE) {
				throw new ServiceUnavailableError($error->getMessage());
			} else {
				throw new \Exception($error->getMessage());
			}
		}
		return $ip->getParameter(1);
	}
	
	/**
	 * @throws IllegalArgumentError
	 * @throws AuthenticationError
	 * @throws NotFoundError
	 * @throws HttpError
	 * @throws ServiceUnavailableError
	 * @throws \Exception
	 */
	public function login():void {
		Statistics::addCall("GitHub", "login");
		$ip = new Interpreter(new Sandbox(GitHub::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage];
		$ip->callFunctionSync('loginGH', $auxArray);
		$error = $ip->getError();
		if (!is_null($error)) {
			if ($error->getType() == CloudRailError::ILLEGAL_ARGUMENT) {
				throw new IllegalArgumentError($error->getMessage());
			} else if ($error->getType() == CloudRailError::AUTHENTICATION) {
				throw new AuthenticationError($error->getMessage());
			} else if ($error->getType() == CloudRailError::NOT_FOUND) {
				throw new NotFoundError($error->getMessage());
			} else if ($error->getType() == CloudRailError::HTTP) {
				throw new HttpError($error->getMessage());
			} else if ($error->getType() == CloudRailError::SERVICE_UNAVAILABLE) {
				throw new ServiceUnavailableError($error->getMessage());
			} else {
				throw new \Exception($error->getMessage());
			}
		}
		
	}
	
	/**
	 * @throws IllegalArgumentError
	 * @throws AuthenticationError
	 * @throws NotFoundError
	 * @throws HttpError
	 * @throws ServiceUnavailableError
	 * @throws \Exception
	 */
	public function logout():void {
		Statistics::addCall("GitHub", "logout");
		$ip = new Interpreter(new Sandbox(GitHub::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage];
		$ip->callFunctionSync('logoutGH', $auxArray);
		$error = $ip->getError();
		if (!is_null($error)) {
			if ($error->getType() == CloudRailError::ILLEGAL_ARGUMENT) {
				throw new IllegalArgumentError($error->getMessage());
			} else if ($error->getType() == CloudRailError::AUTHENTICATION) {
				throw new AuthenticationError($error->getMessage());
			} else if ($error->getType() == CloudRailError::NOT_FOUND) {
				throw new NotFoundError($error->getMessage());
			} else if ($error->getType() == CloudRailError::HTTP) {
				throw new HttpError($error->getMessage());
			} else if ($error->getType() == CloudRailError::SERVICE_UNAVAILABLE) {
				throw new ServiceUnavailableError($error->getMessage());
			} else {
				throw new \Exception($error->getMessage());
			}
		}
		
	}
	
	/**
	 * @param AdvancedRequestSpecification $specification
	 * @throws IllegalArgumentError
	 * @throws AuthenticationError
	 * @throws NotFoundError
	 * @throws HttpError
	 * @throws ServiceUnavailableError
	 * @throws \Exception
	 * @return AdvancedRequestResponse
	 */
	public function advancedRequest(AdvancedRequestSpecification $specification):AdvancedRequestResponse {
		Statistics::addCall("GitHub", "advancedRequest");
		$ip = new Interpreter(new Sandbox(GitHub::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null, $specification];
		$ip->callFunctionSync('AdvancedRequestSupporter:advancedRequest', $auxArray);
		$error = $ip->getError();
		if (!is_null($error)) {
			if ($error->getType() == CloudRailError::ILLEGAL_ARGUMENT) {
				throw new IllegalArgumentError($error->getMessage());
			} else if ($error->getType() == CloudRailError::AUTHENTICATION) {
				throw new AuthenticationError($error->getMessage());
			} else if ($error->getType() == CloudRailError::NOT_FOUND) {
				throw new NotFoundError($error->getMessage());
			} else if ($error->getType() == CloudRailError::HTTP) {
				throw new HttpError($error->getMessage());
			} else if ($error->getType() == CloudRailError::SERVICE_UNAVAILABLE) {
				throw new ServiceUnavailableError($error->getMessage());
			} else {
				throw new \Exception($error->getMessage());
			}
		}
		return $ip->getParameter(1);
	}
	

	/**
	 * @return string
	 */
	public function saveAsString() {
		$ip = new Interpreter(new Sandbox(GitHub::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		return $ip->saveAsString();
	}

	/**
	 * @param string $savedState
	 */
	public function loadAsString($savedState) {
		$sandbox = new Sandbox(GitHub::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage);
		$ip = new Interpreter($sandbox);
		$ip->loadAsString($savedState);
		$this->persistentStorage = $sandbox->persistentStorage;
	}
}
