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
use CloudRail\Interfaces\Social;

use CloudRail\Interfaces\AdvancedRequestSupporter;
use CloudRail\Type\AdvancedRequestSpecification;
use CloudRail\Type\AdvancedRequestResponse;
use CloudRail\Type\CloudRailError;

class Facebook implements Profile, Social, AdvancedRequestSupporter
{
	private static $SERVICE_CODE = [
		'init' => [
			["set", '$P0.boundary', "Amgrmg43ghg3g39glv0k2ldk"],
			["if==than", '$P0.scopes', NULL, 2],
			["set", '$P0.scope', "public_profile%2Cemail%2Cuser_birthday%2Cpublish_actions%2Cuser_friends%2Cuser_photos"],
			["jumpRel", 11],
			["create", '$P0.scope', "String"],
			["size", '$L0', '$P0.scopes'],
			["create", '$L1', "Number", 0],
			["if<than", '$L1', '$L0', 7],
			["if!=than", '$L1', 0, 1],
			["string.concat", '$P0.scope', '$P0.scope', "%2C"],
			["get", '$L2', '$P0.scopes', '$L1'],
			["string.urlEncode", '$L2', '$L2'],
			["string.concat", '$P0.scope', '$P0.scope', '$L2'],
			["math.add", '$L1', '$L1', 1],
			["jumpRel", -8]
		],
		'AdvancedRequestSupporter:advancedRequest' => [
			["create", '$L0', "Object"],
			["create", '$L0.url', "String"],
			["if!=than", '$P2.appendBaseUrl', 0, 1],
			["set", '$L0.url', "https://graph.facebook.com"],
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
		'Social:postUpdate' => [
			["if==than", '$P1', NULL, 2],
			["create", '$L0', "Error", "The content is not allowed to be null.", "IllegalArgument"],
			["throwError", '$L0'],
			["callFunc", "checkAuthentication", '$P0'],
			["create", '$L3', "String"],
			["string.urlEncode", '$L3', '$P1'],
			["create", '$L0', "Object"],
			["set", '$L0.method', "POST"],
			["create", '$L1', "String"],
			["string.concat", '$L1', "https://graph.facebook.com/v2.8/me/feed?message=", '$L3'],
			["set", '$L0.url', '$L1'],
			["create", '$L0.requestHeaders', "Object"],
			["string.concat", '$L0.requestHeaders.Authorization', "Bearer ", '$S0.accessToken'],
			["create", '$L2', "Object"],
			["http.requestCall", '$L2', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L2']
		],
		'Social:postImage' => [
			["if==than", '$P1', NULL, 2],
			["create", '$L0', "Error", "The message is not allowed to be null.", "IllegalArgument"],
			["throwError", '$L0'],
			["if==than", '$P2', NULL, 2],
			["create", '$L0', "Error", "The image is not allowed to be null.", "IllegalArgument"],
			["throwError", '$L0'],
			["callFunc", "checkAuthentication", '$P0'],
			["create", '$L0', "Object"],
			["set", '$L0.method', "POST"],
			["set", '$L0.url', "https://graph.facebook.com/v2.8/me/photos"],
			["create", '$L0.requestHeaders', "Object"],
			["string.concat", '$L0.requestHeaders.Content-Type', "multipart/form-data; boundary=", '$P0.boundary'],
			["string.concat", '$L0.requestHeaders.Authorization', "Bearer ", '$S0.accessToken'],
			["string.concat", '$L1', "--", '$P0.boundary', "\r\n"],
			["string.concat", '$L1', '$L1', "Content-Disposition: form-data; name=\"caption\"\r\n\r\n"],
			["string.concat", '$L1', '$L1', '$P1', "\r\n"],
			["string.concat", '$L1', '$L1', "--", '$P0.boundary', "\r\n"],
			["string.concat", '$L1', '$L1', "Content-Disposition: form-data; name=\"source\"; filename=\"somename\"\r\n"],
			["string.concat", '$L1', '$L1', "Content-Transfer-Encoding: binary\r\n\r\n"],
			["stream.stringToStream", '$L1', '$L1'],
			["string.concat", '$L2', "\r\n--", '$P0.boundary', "--"],
			["stream.stringToStream", '$L2', '$L2'],
			["stream.makeJoinedStream", '$L0.requestBody', '$L1', '$P2', '$L2'],
			["http.requestCall", '$L4', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L4']
		],
		'Social:postVideo' => [
			["if==than", '$P1', NULL, 2],
			["create", '$L0', "Error", "The message is not allowed to be null.", "IllegalArgument"],
			["throwError", '$L0'],
			["if==than", '$P2', NULL, 2],
			["create", '$L0', "Error", "The image is not allowed to be null.", "IllegalArgument"],
			["throwError", '$L0'],
			["callFunc", "checkAuthentication", '$P0'],
			["create", '$L0', "Object"],
			["set", '$L0.method', "POST"],
			["set", '$L0.url', "https://graph-video.facebook.com/v2.8/me/videos"],
			["string.indexOf", '$L20', '$P4', "/"],
			["math.add", '$L20', '$L20', 1],
			["string.substring", '$L21', '$P4', '$L20'],
			["create", '$L0.requestHeaders', "Object"],
			["string.concat", '$L0.requestHeaders.Content-Type', "multipart/form-data; boundary=", '$P0.boundary'],
			["string.concat", '$L0.requestHeaders.Authorization', "Bearer ", '$S0.accessToken'],
			["string.concat", '$L1', "--", '$P0.boundary', "\r\n"],
			["string.concat", '$L1', '$L1', "Content-Disposition: form-data; name=\"description\"\r\n\r\n"],
			["string.concat", '$L1', '$L1', '$P1', "\r\n"],
			["string.concat", '$L1', '$L1', "--", '$P0.boundary', "\r\n"],
			["string.concat", '$L1', '$L1', "Content-Disposition: form-data; name=\"source\"; filename=\"somename.", '$L21', "\"\r\n"],
			["string.concat", '$L1', '$L1', "Content-Transfer-Encoding: binary\r\n\r\n"],
			["stream.stringToStream", '$L1', '$L1'],
			["string.concat", '$L2', "\r\n--", '$P0.boundary', "--"],
			["stream.stringToStream", '$L2', '$L2'],
			["stream.makeJoinedStream", '$L0.requestBody', '$L1', '$P2', '$L2'],
			["http.requestCall", '$L4', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L4']
		],
		'Social:getConnections' => [
			["callFunc", "checkAuthentication", '$P0'],
			["create", '$L0', "Object"],
			["set", '$L0.method', "GET"],
			["create", '$L1', "String"],
			["string.concat", '$L1', "https://graph.facebook.com/v2.8/me/friends"],
			["set", '$L0.url', '$L1'],
			["create", '$L0.requestHeaders', "Object"],
			["string.concat", '$L0.requestHeaders.Authorization', "Bearer ", '$S0.accessToken'],
			["create", '$L2', "Object"],
			["http.requestCall", '$L2', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L2'],
			["create", '$L3', "String"],
			["stream.streamToString", '$L3', '$L2.responseBody'],
			["json.parse", '$L4', '$L3'],
			["create", '$L9', "Number"],
			["size", '$L9', '$L4.data'],
			["create", '$P1', "Array"],
			["if!=than", '$L9', 0, 11],
			["set", '$L5', '$L4.data.items'],
			["create", '$L6', "Number"],
			["size", '$L6', '$L5'],
			["create", '$L7', "Number", 0],
			["if<than", '$L7', '$L6', 6],
			["create", '$L8', "Object"],
			["get", '$L8', '$L5', '$L7'],
			["string.concat", '$L9', "facebook-", '$L8.id'],
			["push", '$P1', '$L9'],
			["math.add", '$L7', '$L7', 1],
			["jumpRel", -7]
		],
		'Authenticating:login' => [
			["callFunc", "checkAuthentication", '$P0']
		],
		'Authenticating:logout' => [
			["set", '$P0.userInfo', NULL],
			["if!=than", '$S0.accessToken', NULL, 11],
			["create", '$L0', "Object"],
			["set", '$L0.method', "DELETE"],
			["create", '$L1', "String"],
			["string.concat", '$L1', "https://graph.facebook.com/v2.8/me/permissions"],
			["set", '$L0.url', '$L1'],
			["create", '$L0.requestHeaders', "Object"],
			["string.concat", '$L0.requestHeaders.Authorization', "Bearer ", '$S0.accessToken'],
			["create", '$L2', "Object"],
			["http.requestCall", '$L2', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L2'],
			["set", '$S0.accessToken', NULL]
		],
		'Profile:getIdentifier' => [
			["callFunc", "checkUserInfo", '$P0'],
			["string.concat", '$P1', "facebook-", '$P0.userInfo.id']
		],
		'Profile:getFullName' => [
			["callFunc", "checkUserInfo", '$P0'],
			["if!=than", '$P0.userInfo.name', NULL, 1],
			["set", '$P1', '$P0.userInfo.name']
		],
		'Profile:getEmail' => [
			["callFunc", "checkUserInfo", '$P0'],
			["if!=than", '$P0.userInfo.email', NULL, 1],
			["set", '$P1', '$P0.userInfo.email']
		],
		'Profile:getGender' => [
			["callFunc", "checkUserInfo", '$P0'],
			["if!=than", '$P0.userInfo.gender', NULL, 7],
			["if==than", '$P0.userInfo.gender', "male", 2],
			["set", '$P1', '$P0.userInfo.gender'],
			["return"],
			["if==than", '$P0.userInfo.gender', "female", 2],
			["set", '$P1', '$P0.userInfo.gender'],
			["return"],
			["set", '$P1', "other"]
		],
		'Profile:getDescription' => [
			["callFunc", "checkUserInfo", '$P0'],
			["if!=than", '$P0.userInfo.about', NULL, 1],
			["set", '$P1', '$P0.userInfo.about']
		],
		'Profile:getDateOfBirth' => [
			["callFunc", "checkUserInfo", '$P0'],
			["create", '$P1', "DateOfBirth"],
			["if!=than", '$P0.userInfo.birthday', NULL, 10],
			["string.split", '$L0', '$P0.userInfo.birthday', "/"],
			["get", '$L1', '$L0', 0],
			["if!=than", '$L1', "00", 1],
			["math.add", '$P1.month', '$L1', 0],
			["get", '$L1', '$L0', 1],
			["if!=than", '$L1', "00", 1],
			["math.add", '$P1.day', '$L1', 0],
			["get", '$L1', '$L0', 2],
			["if!=than", '$L1', "0000", 1],
			["math.add", '$P1.year', '$L1', 0]
		],
		'Profile:getLocale' => [
			["callFunc", "checkUserInfo", '$P0'],
			["if!=than", '$P0.userInfo.locale', NULL, 3],
			["create", '$L1', "String"],
			["string.substring", '$L1', '$P0.userInfo.locale', 0, 2],
			["set", '$P1', '$L1']
		],
		'Profile:getPictureURL' => [
			["callFunc", "checkUserInfo", '$P0'],
			["if!=than", '$P0.userInfo.picture.data.url', NULL, 1],
			["set", '$P1', '$P0.userInfo.picture.data.url']
		],
		'checkUserInfo' => [
			["create", '$L0', "Date"],
			["if!=than", '$P0.userInfo', NULL, 2],
			["if>than", '$P0.expirationTime', '$L0', 1],
			["return"],
			["callFunc", "checkAuthentication", '$P0'],
			["create", '$L2', "Object"],
			["set", '$L2.url', "https://graph.facebook.com/v2.8/me?fields=locale,gender,name,birthday,email,about"],
			["create", '$L2.requestHeaders', "Object"],
			["string.concat", '$L2.requestHeaders.Authorization', "Bearer ", '$S0.accessToken'],
			["set", '$L2.method', "GET"],
			["http.requestCall", '$L3', '$L2'],
			["callFunc", "validateResponse", '$P0', '$L3'],
			["json.parse", '$P0.userInfo', '$L3.responseBody'],
			["create", '$L2', "Object"],
			["set", '$L2.url', "https://graph.facebook.com/v2.8/me/picture?fields=url&type=large&redirect=false"],
			["create", '$L2.requestHeaders', "Object"],
			["string.concat", '$L2.requestHeaders.Authorization', "Bearer ", '$S0.accessToken'],
			["set", '$L2.method', "GET"],
			["http.requestCall", '$L3', '$L2'],
			["callFunc", "validateResponse", '$P0', '$L3'],
			["json.parse", '$L4', '$L3.responseBody'],
			["set", '$P0.userInfo.picture', '$L4'],
			["create", '$P0.expirationTime', "Date"],
			["math.add", '$P0.expirationTime.time', '$P0.expirationTime.time', 60000]
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
			["string.urlEncode", '$L3', '$P0.redirectUri'],
			["string.concat", '$L0', "https://www.facebook.com/dialog/oauth?response_type=code&client_id=", '$P0.clientId', "&redirect_uri=", '$L3', "&state=", '$P0.state', "&scope=", '$P0.scope'],
			["awaitCodeRedirect", '$L2', '$L0', NULL, '$P0.redirectUri'],
			["string.concat", '$L1', "https://graph.facebook.com/v2.8/oauth/access_token?client_id=", '$P0.clientId', "&redirect_uri=", '$L3', "&client_secret=", '$P0.clientSecret', "&code=", '$L2'],
			["create", '$L5', "Object"],
			["set", '$L5.url', '$L1'],
			["set", '$L5.method', "GET"],
			["http.requestCall", '$L6', '$L5'],
			["callFunc", "validateResponse", '$P0', '$L6'],
			["stream.streamToString", '$L7', '$L6.responseBody'],
			["json.parse", '$L8', '$L7'],
			["set", '$S0.accessToken', '$L8.access_token'],
			["create", '$L0', "Object"],
			["set", '$L0.method', "GET"],
			["string.concat", '$L0.url', "https://graph.facebook.com/v2.8/oauth/access_token?client_id=", '$P0.clientId', "&client_secret=", '$P0.clientSecret', "&grant_type=fb_exchange_token&fb_exchange_token=", '$S0.accessToken'],
			["http.requestCall", '$L1', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L1'],
			["json.parse", '$L2', '$L1.responseBody'],
			["set", '$S0.accessToken', '$L2.access_token'],
			["create", '$L10', "Date"],
			["math.multiply", '$L9', 60, 24, 60, 60, 1000],
			["math.add", '$L9', '$L9', '$L10.time', -60000],
			["set", '$S0.expireIn', '$L9']
		],
		'validateResponse' => [
			["if>=than", '$P1.code', 400, 3],
			["stream.streamToString", '$L2', '$P1.responseBody'],
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
		InitSelfTest::initTest('Facebook');
		
		$this->interpreterStorage['clientId'] = $clientId;
		$this->interpreterStorage['clientSecret'] = $clientSecret;
		$this->interpreterStorage['redirectUri'] = $redirectUri;
		$this->interpreterStorage['state'] = $state;
		$this->interpreterStorage['scopes'] = $scopes;
		

		$ip = new Interpreter(new Sandbox(Facebook::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		if (array_key_exists("init",Facebook::$SERVICE_CODE)) {
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
		Statistics::addCall("Facebook", "getIdentifier");
		$ip = new Interpreter(new Sandbox(Facebook::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Facebook", "getFullName");
		$ip = new Interpreter(new Sandbox(Facebook::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Facebook", "getEmail");
		$ip = new Interpreter(new Sandbox(Facebook::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Facebook", "getGender");
		$ip = new Interpreter(new Sandbox(Facebook::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Facebook", "getDescription");
		$ip = new Interpreter(new Sandbox(Facebook::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Facebook", "getDateOfBirth");
		$ip = new Interpreter(new Sandbox(Facebook::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Facebook", "getLocale");
		$ip = new Interpreter(new Sandbox(Facebook::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Facebook", "getPictureURL");
		$ip = new Interpreter(new Sandbox(Facebook::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Facebook", "login");
		$ip = new Interpreter(new Sandbox(Facebook::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Facebook", "logout");
		$ip = new Interpreter(new Sandbox(Facebook::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
	 * @param string $content
	 * @throws IllegalArgumentError
	 * @throws AuthenticationError
	 * @throws NotFoundError
	 * @throws HttpError
	 * @throws ServiceUnavailableError
	 * @throws \Exception
	 */
	public function postUpdate(string $content):void {
		Statistics::addCall("Facebook", "postUpdate");
		$ip = new Interpreter(new Sandbox(Facebook::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, $content];
		$ip->callFunctionSync('Social:postUpdate', $auxArray);
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
	 * @param string $message
	 * @param resource $image
	 * @throws IllegalArgumentError
	 * @throws AuthenticationError
	 * @throws NotFoundError
	 * @throws HttpError
	 * @throws ServiceUnavailableError
	 * @throws \Exception
	 */
	public function postImage(string $message,  $image):void {
		Statistics::addCall("Facebook", "postImage");
		$ip = new Interpreter(new Sandbox(Facebook::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, $message, $image];
		$ip->callFunctionSync('Social:postImage', $auxArray);
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
	 * @param string $message
	 * @param resource $video
	 * @param int $size
	 * @param string $mimeType
	 * @throws IllegalArgumentError
	 * @throws AuthenticationError
	 * @throws NotFoundError
	 * @throws HttpError
	 * @throws ServiceUnavailableError
	 * @throws \Exception
	 */
	public function postVideo(string $message,  $video, int $size, string $mimeType):void {
		Statistics::addCall("Facebook", "postVideo");
		$ip = new Interpreter(new Sandbox(Facebook::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, $message, $video, $size, $mimeType];
		$ip->callFunctionSync('Social:postVideo', $auxArray);
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
	 * @return array
	 */
	public function getConnections():array {
		Statistics::addCall("Facebook", "getConnections");
		$ip = new Interpreter(new Sandbox(Facebook::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null];
		$ip->callFunctionSync('Social:getConnections', $auxArray);
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
		Statistics::addCall("Facebook", "advancedRequest");
		$ip = new Interpreter(new Sandbox(Facebook::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		$ip = new Interpreter(new Sandbox(Facebook::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		return $ip->saveAsString();
	}

	/**
	 * @param string $savedState
	 */
	public function loadAsString($savedState) {
		$sandbox = new Sandbox(Facebook::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage);
		$ip = new Interpreter($sandbox);
		$ip->loadAsString($savedState);
		$this->persistentStorage = $sandbox->persistentStorage;
	}
}
