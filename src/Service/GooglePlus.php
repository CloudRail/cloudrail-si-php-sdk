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

class GooglePlus implements Profile, AdvancedRequestSupporter
{
	private static $SERVICE_CODE = [
		'init' => [
			["if==than", '$P0.scopes', NULL, 2],
			["set", '$P0.scope', "https%3A%2F%2Fwww.googleapis.com%2Fauth%2Fplus.login+https%3A%2F%2Fwww.googleapis.com%2Fauth%2Fplus.profile.emails.read"],
			["jumpRel", 10],
			["create", '$P0.scope', "String"],
			["size", '$L0', '$P0.scopes'],
			["create", '$L1', "Number", 0],
			["if<than", '$L1', '$L0', 6],
			["if!=than", '$L1', 0, 1],
			["string.concat", '$P0.scope', '$P0.scope', "+"],
			["get", '$L2', '$P0.scopes', '$L1'],
			["string.concat", '$P0.scope', '$P0.scope', '$L2'],
			["math.add", '$L1', '$L1', 1],
			["jumpRel", -7]
		],
		'AdvancedRequestSupporter:advancedRequest' => [
			["create", '$L0', "Object"],
			["create", '$L0.url', "String"],
			["if!=than", '$P2.appendBaseUrl', 0, 1],
			["set", '$L0.url', "https://www.googleapis.com/plus/v1"],
			["string.concat", '$L0.url', '$L0.url', '$P2.url'],
			["set", '$L0.requestHeaders', '$P2.headers'],
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
		'Authenticating:login' => [
			["callFunc", "checkAuthentication", '$P0']
		],
		'Authenticating:logout' => [
			["create", '$L0', "Object"],
			["string.concat", '$L0.url', "https://accounts.google.com/o/oauth2/revoke?token=", '$S0.accessToken'],
			["set", '$L0.method', "GET"],
			["http.requestCall", '$L1', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L1'],
			["set", '$S0.accessToken', NULL],
			["set", '$P0.userInfo', NULL]
		],
		'Profile:getIdentifier' => [
			["callFunc", "checkUserInfo", '$P0'],
			["string.concat", '$P1', "googleplus-", '$P0.userInfo.id']
		],
		'Profile:getFullName' => [
			["callFunc", "checkUserInfo", '$P0'],
			["if!=than", '$P0.userInfo.displayName', NULL, 1],
			["set", '$P1', '$P0.userInfo.displayName']
		],
		'Profile:getEmail' => [
			["callFunc", "checkUserInfo", '$P0'],
			["get", '$P1', '$P0.userInfo.emails', 0, "value"]
		],
		'Profile:getGender' => [
			["callFunc", "checkUserInfo", '$P0'],
			["if!=than", '$P0.userInfo.gender', NULL, 1],
			["set", '$P1', '$P0.userInfo.gender']
		],
		'Profile:getDescription' => [
			["callFunc", "checkUserInfo", '$P0'],
			["if!=than", '$P0.userInfo.aboutMe', NULL, 1],
			["set", '$P1', '$P0.userInfo.aboutMe']
		],
		'Profile:getDateOfBirth' => [
			["callFunc", "checkUserInfo", '$P0'],
			["create", '$P1', "DateOfBirth"],
			["if!=than", '$P0.userInfo.birthday', NULL, 10],
			["string.split", '$L0', '$P0.userInfo.birthday', "-"],
			["get", '$L1', '$L0', 0],
			["if!=than", '$L1', "0000", 1],
			["math.add", '$P1.year', '$L1', 0],
			["get", '$L1', '$L0', 1],
			["if!=than", '$L1', "00", 1],
			["math.add", '$P1.month', '$L1', 0],
			["get", '$L1', '$L0', 2],
			["if!=than", '$L1', "00", 1],
			["math.add", '$P1.day', '$L1', 0]
		],
		'Profile:getLocale' => [
			["callFunc", "checkUserInfo", '$P0'],
			["if!=than", '$P0.userInfo.language', NULL, 1],
			["set", '$P1', '$P0.userInfo.language']
		],
		'Profile:getPictureURL' => [
			["callFunc", "checkUserInfo", '$P0'],
			["if!=than", '$P0.userInfo.image.url', NULL, 1],
			["set", '$P1', '$P0.userInfo.image.url']
		],
		'checkUserInfo' => [
			["create", '$L0', "Date"],
			["if!=than", '$P0.userInfo', NULL, 2],
			["if>than", '$P0.expirationTime', '$L0', 1],
			["return"],
			["callFunc", "checkAuthentication", '$P0'],
			["create", '$L2', "Object"],
			["set", '$L2.url', "https://www.googleapis.com/plus/v1/people/me"],
			["create", '$L2.requestHeaders', "Object"],
			["string.concat", '$L2.requestHeaders.Authorization', "Bearer ", '$S0.accessToken'],
			["set", '$L2.method', "GET"],
			["http.requestCall", '$L3', '$L2'],
			["callFunc", "validateResponse", '$P0', '$L3'],
			["json.parse", '$P0.userInfo', '$L3.responseBody'],
			["create", '$P0.expirationTime', "Date"],
			["math.add", '$P0.expirationTime.time', '$P0.expirationTime.time', 60000]
		],
		'checkAuthentication' => [
			["create", '$L0', "Date"],
			["if==than", '$S0.accessToken', NULL, 2],
			["callFunc", "authenticate", '$P0', "accessToken"],
			["return"],
			["create", '$L1', "Date"],
			["set", '$L1.time', '$S0.expireIn'],
			["if<than", '$L1', '$L0', 1],
			["callFunc", "authenticate", '$P0', "refreshToken"]
		],
		'authenticate' => [
			["create", '$L2', "String"],
			["if==than", '$P1', "accessToken", 4],
			["string.concat", '$L0', "https://accounts.google.com/o/oauth2/v2/auth?client_id=", '$P0.clientId', "&scope=", '$P0.scope', "&response_type=code&prompt=consent&access_type=offline&redirect_uri=", '$P0.redirectUri', "&state=", '$P0.state', "&suppress_webview_warning=true"],
			["awaitCodeRedirect", '$L1', '$L0', NULL, '$P0.redirectUri'],
			["string.concat", '$L2', "client_id=", '$P0.clientId', "&redirect_uri=", '$P0.redirectUri', "&client_secret=", '$P0.clientSecret', "&code=", '$L1', "&grant_type=authorization_code"],
			["jumpRel", 1],
			["string.concat", '$L2', "client_id=", '$P0.clientId', "&redirect_uri=", '$P0.redirectUri', "&client_secret=", '$P0.clientSecret', "&refresh_token=", '$S0.refreshToken', "&grant_type=refresh_token"],
			["stream.stringToStream", '$L3', '$L2'],
			["create", '$L4', "Object"],
			["set", '$L4', "application/x-www-form-urlencoded", "Content-Type"],
			["create", '$L5', "Object"],
			["set", '$L5.url', "https://www.googleapis.com/oauth2/v4/token"],
			["set", '$L5.method', "POST"],
			["set", '$L5.requestBody', '$L3'],
			["set", '$L5.requestHeaders', '$L4'],
			["http.requestCall", '$L6', '$L5'],
			["callFunc", "validateResponse", '$P0', '$L6'],
			["stream.streamToString", '$L7', '$L6.responseBody'],
			["json.parse", '$L8', '$L7'],
			["set", '$S0.accessToken', '$L8.access_token'],
			["if!=than", '$L8.refresh_token', NULL, 1],
			["set", '$S0.refreshToken', '$L8.refresh_token'],
			["create", '$L10', "Date"],
			["math.multiply", '$L9', '$L8.expires_in', 1000],
			["math.add", '$L9', '$L9', '$L10.time', -60000],
			["set", '$S0.expireIn', '$L9']
		],
		'validateResponse' => [
			["if>=than", '$P1.code', 400, 19],
			["stream.streamToString", '$L2', '$P1.responseBody'],
			["if==than", '$P1.code', 401, 2],
			["create", '$L3', "Error", '$L2', "Authentication"],
			["throwError", '$L3'],
			["if==than", '$P1.code', 400, 2],
			["create", '$L3', "Error", '$L2', "Http"],
			["throwError", '$L3'],
			["if>=than", '$P1.code', 402, 5],
			["if<=than", '$P1.code', 509, 4],
			["if!=than", '$P1.code', 503, 3],
			["if!=than", '$P1.code', 404, 2],
			["create", '$L3', "Error", '$L2', "Http"],
			["throwError", '$L3'],
			["if==than", '$P1.code', 503, 2],
			["create", '$L3', "Error", '$L2', "ServiceUnavailable"],
			["throwError", '$L3'],
			["if==than", '$P1.code', 404, 2],
			["create", '$L3', "Error", '$L2', "NotFound"],
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
		InitSelfTest::initTest('GooglePlus');
		
		$this->interpreterStorage['clientId'] = $clientId;
		$this->interpreterStorage['clientSecret'] = $clientSecret;
		$this->interpreterStorage['redirectUri'] = $redirectUri;
		$this->interpreterStorage['state'] = $state;
		$this->interpreterStorage['scopes'] = $scopes;
		

		$ip = new Interpreter(new Sandbox(GooglePlus::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		if (array_key_exists("init",GooglePlus::$SERVICE_CODE)) {
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
		Statistics::addCall("GooglePlus", "getIdentifier");
		$ip = new Interpreter(new Sandbox(GooglePlus::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null];
		$ip->callFunctionSync('Profile:getIdentifier', $auxArray);
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
		Statistics::addCall("GooglePlus", "getFullName");
		$ip = new Interpreter(new Sandbox(GooglePlus::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null];
		$ip->callFunctionSync('Profile:getFullName', $auxArray);
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
		Statistics::addCall("GooglePlus", "getEmail");
		$ip = new Interpreter(new Sandbox(GooglePlus::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null];
		$ip->callFunctionSync('Profile:getEmail', $auxArray);
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
		Statistics::addCall("GooglePlus", "getGender");
		$ip = new Interpreter(new Sandbox(GooglePlus::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null];
		$ip->callFunctionSync('Profile:getGender', $auxArray);
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
		Statistics::addCall("GooglePlus", "getDescription");
		$ip = new Interpreter(new Sandbox(GooglePlus::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null];
		$ip->callFunctionSync('Profile:getDescription', $auxArray);
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
		Statistics::addCall("GooglePlus", "getDateOfBirth");
		$ip = new Interpreter(new Sandbox(GooglePlus::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null];
		$ip->callFunctionSync('Profile:getDateOfBirth', $auxArray);
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
		Statistics::addCall("GooglePlus", "getLocale");
		$ip = new Interpreter(new Sandbox(GooglePlus::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null];
		$ip->callFunctionSync('Profile:getLocale', $auxArray);
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
		Statistics::addCall("GooglePlus", "getPictureURL");
		$ip = new Interpreter(new Sandbox(GooglePlus::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null];
		$ip->callFunctionSync('Profile:getPictureURL', $auxArray);
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
		Statistics::addCall("GooglePlus", "login");
		$ip = new Interpreter(new Sandbox(GooglePlus::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage];
		$ip->callFunctionSync('Authenticating:login', $auxArray);
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
		Statistics::addCall("GooglePlus", "logout");
		$ip = new Interpreter(new Sandbox(GooglePlus::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage];
		$ip->callFunctionSync('Authenticating:logout', $auxArray);
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
		Statistics::addCall("GooglePlus", "advancedRequest");
		$ip = new Interpreter(new Sandbox(GooglePlus::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		$ip = new Interpreter(new Sandbox(GooglePlus::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		return $ip->saveAsString();
	}

	/**
	 * @param string $savedState
	 */
	public function loadAsString($savedState) {
		$sandbox = new Sandbox(GooglePlus::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage);
		$ip = new Interpreter($sandbox);
		$ip->loadAsString($savedState);
		$this->persistentStorage = $sandbox->persistentStorage;
	}
}
