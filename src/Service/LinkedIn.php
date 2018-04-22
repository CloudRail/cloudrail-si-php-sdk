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

class LinkedIn implements Profile, AdvancedRequestSupporter
{
	private static $SERVICE_CODE = [
		'init' => [
			["if==than", '$P0.scopes', NULL, 2],
			["set", '$P0.scope', "r_basicprofile%20r_emailaddress"],
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
			["set", '$L0.url', "https://api.linkedin.com/v1"],
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
		'getLIIdentifier' => [
			["callFunc", "checkUserData", '$P0'],
			["string.concat", '$P1', "linkedin-", '$P0.userData.id']
		],
		'getLIFullName' => [
			["callFunc", "checkUserData", '$P0'],
			["string.concat", '$P1', '$P0.userData.firstName', " ", '$P0.userData.lastName']
		],
		'getLIEmail' => [
			["callFunc", "checkUserData", '$P0'],
			["set", '$P1', '$P0.userData.emailAddress']
		],
		'getLIGender' => [
		],
		'getLIDescription' => [
			["callFunc", "checkUserData", '$P0'],
			["set", '$P1', '$P0.userData.summary']
		],
		'getLIDateOfBirth' => [
			["callFunc", "checkUserData", '$P0'],
			["create", '$L0', "DateOfBirth"],
			["if!=than", '$P0.userData.dateOfBirth', NULL, 6],
			["if!=than", '$P0.userData.dateOfBirth.day', NULL, 1],
			["set", '$L0.day', '$P0.userData.dateOfBirth.day'],
			["if!=than", '$P0.userData.dateOfBirth.month', NULL, 1],
			["set", '$L0.month', '$P0.userData.dateOfBirth.month'],
			["if!=than", '$P0.userData.dateOfBirth.year', NULL, 1],
			["set", '$L0.year', '$P0.userData.dateOfBirth.year'],
			["set", '$P1', '$L0']
		],
		'getLILocale' => [
		],
		'getLIPictureURL' => [
			["callFunc", "checkUserData", '$P0'],
			["set", '$P1', '$P0.userData.pictureUrl']
		],
		'loginLI' => [
			["callFunc", "checkAuthentication", '$P0']
		],
		'logoutLI' => [
			["set", '$S0.accessToken', NULL],
			["set", '$P0.userData', NULL]
		],
		'checkAuthentication' => [
			["create", '$L0', "Date"],
			["if==than", '$S0.accessToken', NULL, 2],
			["callFunc", "authenticate", '$P0'],
			["return"],
			["create", '$L1', "Date"],
			["set", '$L1.time', '$S0.expireIn'],
			["if<than", '$L1', '$L0', 1],
			["callFunc", "authenticate", '$P0']
		],
		'authenticate' => [
			["create", '$L0', "String"],
			["create", '$L1', "String"],
			["string.concat", '$L0', "https://www.linkedin.com/uas/oauth2/authorization?response_type=code&client_id=", '$P0.clientId', "&redirect_uri=", '$P0.redirectUri', "&state=", '$P0.state', "&scope=", '$P0.scope'],
			["awaitCodeRedirect", '$L2', '$L0', NULL, '$P0.redirectUri'],
			["string.concat", '$L1', "grant_type=authorization_code&code=", '$L2', "&redirect_uri=", '$P0.redirectUri', "&client_id=", '$P0.clientId', "&client_secret=", '$P0.clientSecret'],
			["size", '$L15', '$L1'],
			["string.concat", '$L15', '$L15'],
			["stream.stringToStream", '$L3', '$L1'],
			["create", '$L4', "Object"],
			["set", '$L4', "application/x-www-form-urlencoded", "Content-Type"],
			["set", '$L4.Content-Length', '$L15'],
			["create", '$L5', "Object"],
			["set", '$L5.url', "https://www.linkedin.com/uas/oauth2/accessToken"],
			["set", '$L5.method', "POST"],
			["set", '$L5.requestBody', '$L3'],
			["set", '$L5.requestHeaders', '$L4'],
			["http.requestCall", '$L6', '$L5'],
			["callFunc", "validateResponse", '$P0', '$L6'],
			["stream.streamToString", '$L7', '$L6.responseBody'],
			["json.parse", '$L8', '$L7'],
			["set", '$S0.accessToken', '$L8.access_token'],
			["create", '$L10', "Date"],
			["math.multiply", '$L9', '$L8.expires_in', 1000],
			["math.add", '$L9', '$L9', '$L10.time', -60000],
			["set", '$S0.expireIn', '$L9']
		],
		'validateResponse' => [
			["if>=than", '$P1.code', 400, 20],
			["json.parse", '$L0', '$P1.responseBody'],
			["set", '$L2', '$L0.message'],
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
		],
		'getUserData' => [
			["callFunc", "checkAuthentication", '$P0'],
			["create", '$L0', "Object"],
			["set", '$L0.method', "GET"],
			["create", '$L1', "String"],
			["string.concat", '$L1', "https://api.linkedin.com/v1/people/~:(id,first-name,last-name,email-address,summary,picture-url)?format=json"],
			["set", '$L0.url', '$L1'],
			["create", '$L0.requestHeaders', "Object"],
			["string.concat", '$L0.requestHeaders.Authorization', "Bearer ", '$S0.accessToken'],
			["create", '$L2', "Object"],
			["http.requestCall", '$L2', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L2'],
			["create", '$L3', "String"],
			["stream.streamToString", '$L3', '$L2.responseBody'],
			["create", '$L4', "Object"],
			["json.parse", '$L4', '$L3'],
			["create", '$L5', "Date"],
			["math.add", '$L6', '$L5.time', 60000],
			["set", '$L4.expirationTime', '$L6'],
			["set", '$P0.userData', '$L4']
		],
		'checkUserData' => [
			["create", '$L0', "Date"],
			["if!=than", '$P0.userData', NULL, 2],
			["if>=than", '$P0.userData.expirationTime', '$L0.time', 1],
			["return"],
			["callFunc", "getUserData", '$P0']
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
		InitSelfTest::initTest('LinkedIn');
		
		$this->interpreterStorage['clientId'] = $clientId;
		$this->interpreterStorage['clientSecret'] = $clientSecret;
		$this->interpreterStorage['redirectUri'] = $redirectUri;
		$this->interpreterStorage['state'] = $state;
		$this->interpreterStorage['scopes'] = $scopes;
		

		$ip = new Interpreter(new Sandbox(LinkedIn::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		if (array_key_exists("init",LinkedIn::$SERVICE_CODE)) {
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
		Statistics::addCall("LinkedIn", "getIdentifier");
		$ip = new Interpreter(new Sandbox(LinkedIn::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null];
		$ip->callFunctionSync('getLIIdentifier', $auxArray);
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
		Statistics::addCall("LinkedIn", "getFullName");
		$ip = new Interpreter(new Sandbox(LinkedIn::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null];
		$ip->callFunctionSync('getLIFullName', $auxArray);
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
		Statistics::addCall("LinkedIn", "getEmail");
		$ip = new Interpreter(new Sandbox(LinkedIn::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null];
		$ip->callFunctionSync('getLIEmail', $auxArray);
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
		Statistics::addCall("LinkedIn", "getGender");
		$ip = new Interpreter(new Sandbox(LinkedIn::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null];
		$ip->callFunctionSync('getLIGender', $auxArray);
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
		Statistics::addCall("LinkedIn", "getDescription");
		$ip = new Interpreter(new Sandbox(LinkedIn::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null];
		$ip->callFunctionSync('getLIDescription', $auxArray);
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
		Statistics::addCall("LinkedIn", "getDateOfBirth");
		$ip = new Interpreter(new Sandbox(LinkedIn::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null];
		$ip->callFunctionSync('getLIDateOfBirth', $auxArray);
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
		Statistics::addCall("LinkedIn", "getLocale");
		$ip = new Interpreter(new Sandbox(LinkedIn::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null];
		$ip->callFunctionSync('getLILocale', $auxArray);
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
		Statistics::addCall("LinkedIn", "getPictureURL");
		$ip = new Interpreter(new Sandbox(LinkedIn::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null];
		$ip->callFunctionSync('getLIPictureURL', $auxArray);
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
		Statistics::addCall("LinkedIn", "login");
		$ip = new Interpreter(new Sandbox(LinkedIn::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage];
		$ip->callFunctionSync('loginLI', $auxArray);
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
		Statistics::addCall("LinkedIn", "logout");
		$ip = new Interpreter(new Sandbox(LinkedIn::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage];
		$ip->callFunctionSync('logoutLI', $auxArray);
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
		Statistics::addCall("LinkedIn", "advancedRequest");
		$ip = new Interpreter(new Sandbox(LinkedIn::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		$ip = new Interpreter(new Sandbox(LinkedIn::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		return $ip->saveAsString();
	}

	/**
	 * @param string $savedState
	 */
	public function loadAsString($savedState) {
		$sandbox = new Sandbox(LinkedIn::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage);
		$ip = new Interpreter($sandbox);
		$ip->loadAsString($savedState);
		$this->persistentStorage = $sandbox->persistentStorage;
	}
}
