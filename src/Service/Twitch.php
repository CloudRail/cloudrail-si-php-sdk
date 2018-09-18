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

use CloudRail\Interfaces\Video;

use CloudRail\Interfaces\AdvancedRequestSupporter;
use CloudRail\Type\AdvancedRequestSpecification;
use CloudRail\Type\AdvancedRequestResponse;
use CloudRail\Type\CloudRailError;

class Twitch implements Video, AdvancedRequestSupporter
{
	private static $SERVICE_CODE = [
		'uploadVideo' => [
			["callFunc", "checkMandatory", '$P0', '$P2', "title"],
			["callFunc", "checkMandatory", '$P0', '$P3', "description"],
			["callFunc", "checkMandatory", '$P0', '$P4', "video"],
			["callFunc", "checkMandatory", '$P0', '$P5', "size"],
			["callFunc", "checkMandatory", '$P0', '$P6', "channel ID"],
			["callFunc", "checkEmpty", '$P0', '$P2', "title"],
			["callFunc", "checkEmpty", '$P0', '$P3', "description"],
			["callFunc", "checkEmpty", '$P0', '$P6', "channel ID"],
			["callFunc", "checkGreater0", '$P0', '$P5', "size"],
			["string.urlEncode", '$P2', '$P2'],
			["string.urlEncode", '$P3', '$P3'],
			["string.urlEncode", '$P6', '$P6'],
			["callFunc", "checkAuthentication", '$P0'],
			["callFunc", "startNewVideo", '$P0', '$L0', '$P2', '$P3', '$P6'],
			["callFunc", "uploadVideoParts", '$P0', '$L0.upload.url', '$L0.upload.token', '$P5', '$P4'],
			["callFunc", "completeNewVideo", '$P0', '$L0.upload.url', '$L0.upload.token'],
			["set", '$L1', '$L0.video'],
			["create", '$L2', "Date", '$L1.created_at'],
			["set", '$L5', '$L2.time'],
			["string.concat", '$L3', '$L1._id'],
			["string.concat", '$L4', '$L1.channel._id'],
			["create", '$P1', "VideoMetaData", '$L3', '$L1.title', '$L1.description', '$L5', '$L4', '$L1.length', '$L1.preview.large', NULL, '$L1.views', NULL, NULL]
		],
		'searchVideos' => [
			["callFunc", "checkMandatory", '$P0', '$P2', "query"],
			["callFunc", "checkEmpty", '$P0', '$P2', "query"],
			["if==than", '$P3', NULL, 1],
			["set", '$P3', 0],
			["if<than", '$P3', 0, 1],
			["set", '$P3', 0],
			["if==than", '$P4', NULL, 1],
			["set", '$P4', 50],
			["if<=than", '$P4', 0, 1],
			["set", '$P4', 50],
			["create", '$L0', "Object"],
			["set", '$L0.method', "GET"],
			["string.concat", '$L0.url', "https://api.twitch.tv/kraken/search/streams?query=", '$P2', "&limit=", '$P4', "&offset=", '$P3'],
			["create", '$L0.requestHeaders', "Object"],
			["set", '$L0.requestHeaders.Client-ID', '$P0.clientId'],
			["set", '$L0.requestHeaders.Accept', "application/vnd.twitchtv.v5+json"],
			["create", '$L1', "Object"],
			["http.requestCall", '$L1', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L1'],
			["stream.streamToString", '$L3', '$L1.responseBody'],
			["json.parse", '$L4', '$L3'],
			["create", '$L2', "Array"],
			["set", '$L2', '$L4.streams'],
			["size", '$L3', '$L2'],
			["if==than", '$L3', 0, 1],
			["return"],
			["create", '$L4', "Number", 0],
			["create", '$P1', "Array"],
			["if<than", '$L4', '$L3', 9],
			["get", '$L7', '$L2', '$L4'],
			["create", '$L8', "Date", '$L7.created_at'],
			["set", '$L11', '$L8.time'],
			["string.concat", '$L9', '$L7._id'],
			["string.concat", '$L10', '$L7.channel._id'],
			["create", '$L6', "VideoMetaData", '$L9', NULL, NULL, '$L11', '$L10', NULL, '$L7.preview.large', NULL, '$L7.viewers', NULL, NULL],
			["push", '$P1', '$L6'],
			["math.add", '$L4', '$L4', 1],
			["jumpRel", -10]
		],
		'getVideo' => [
			["callFunc", "checkMandatory", '$P0', '$P2', "video ID"],
			["callFunc", "checkEmpty", '$P0', '$P2', "video ID"],
			["create", '$L0', "Object"],
			["set", '$L0.method', "GET"],
			["string.concat", '$L0.url', "https://api.twitch.tv/kraken/videos/", '$P2'],
			["create", '$L0.requestHeaders', "Object"],
			["set", '$L0.requestHeaders.Client-ID', '$P0.clientId'],
			["set", '$L0.requestHeaders.Accept', "application/vnd.twitchtv.v5+json"],
			["create", '$L1', "Object"],
			["http.requestCall", '$L1', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L1'],
			["stream.streamToString", '$L2', '$L1.responseBody'],
			["json.parse", '$L3', '$L2'],
			["create", '$L4', "Date", '$L3.created_at'],
			["set", '$L7', '$L4.time'],
			["string.concat", '$L5', '$L3._id'],
			["string.concat", '$L6', '$L3.channel._id'],
			["create", '$P1', "VideoMetaData", '$L5', '$L3.title', '$L3.description', '$L7', '$L6', '$L3.length', '$L3.preview.large', NULL, '$L3.views', NULL, NULL]
		],
		'getChannel' => [
			["callFunc", "checkMandatory", '$P0', '$P2', "channel ID"],
			["callFunc", "checkEmpty", '$P0', '$P2', "channel ID"],
			["create", '$L0', "Object"],
			["set", '$L0.method', "GET"],
			["string.concat", '$L0.url', "https://api.twitch.tv/kraken/channels/", '$P2'],
			["create", '$L0.requestHeaders', "Object"],
			["set", '$L0.requestHeaders.Client-ID', '$P0.clientId'],
			["set", '$L0.requestHeaders.Accept', "application/vnd.twitchtv.v5+json"],
			["create", '$L1', "Object"],
			["http.requestCall", '$L1', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L1'],
			["stream.streamToString", '$L3', '$L1.responseBody'],
			["json.parse", '$L2', '$L3'],
			["create", '$P1', "ChannelMetaData", '$L2._id', '$L2.display_name', '$L2.followers', '$L2.url', '$L2.logo', '$L2.profile_banner']
		],
		'getOwnChannel' => [
			["callFunc", "checkAuthentication", '$P0'],
			["create", '$L0', "Object"],
			["set", '$L0.method', "GET"],
			["set", '$L0.url', "https://api.twitch.tv/kraken/channel"],
			["create", '$L0.requestHeaders', "Object"],
			["set", '$L0.requestHeaders.Client-ID', '$P0.clientId'],
			["string.concat", '$L0.requestHeaders.Authorization', "OAuth ", '$S0.accessToken'],
			["set", '$L0.requestHeaders.Accept', "application/vnd.twitchtv.v5+json"],
			["create", '$L1', "Object"],
			["http.requestCall", '$L1', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L1'],
			["stream.streamToString", '$L2', '$L1.responseBody'],
			["json.parse", '$L3', '$L2'],
			["create", '$P1', "ChannelMetaData", '$L3._id', '$L3.display_name', '$L3.followers', '$L3.url', '$L3.logo', '$L3.profile_banner']
		],
		'listVideosForChannel' => [
			["callFunc", "checkMandatory", '$P0', '$P2', "channel ID"],
			["callFunc", "checkEmpty", '$P0', '$P2', "channel ID"],
			["if==than", '$P3', NULL, 1],
			["set", '$P3', 0],
			["if<than", '$P3', 0, 1],
			["set", '$P3', 0],
			["if==than", '$P4', NULL, 1],
			["set", '$P4', 50],
			["if<=than", '$P4', 0, 1],
			["set", '$P4', 50],
			["create", '$L0', "Object"],
			["set", '$L0.method', "GET"],
			["string.concat", '$L0.url', "https://api.twitch.tv/kraken/channels/", '$P2', "/videos?limit=", '$P4', "&offset=", '$P3'],
			["create", '$L0.requestHeaders', "Object"],
			["set", '$L0.requestHeaders.Client-ID', '$P0.clientId'],
			["set", '$L0.requestHeaders.Accept', "application/vnd.twitchtv.v5+json"],
			["create", '$L1', "Object"],
			["http.requestCall", '$L1', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L1'],
			["stream.streamToString", '$L8', '$L1.responseBody'],
			["json.parse", '$L9', '$L8'],
			["set", '$L2', '$L9.videos'],
			["size", '$L3', '$L2'],
			["if==than", '$L3', 0, 1],
			["return"],
			["create", '$L4', "Number", 0],
			["create", '$P1', "Array"],
			["if<than", '$L4', '$L3', 9],
			["get", '$L7', '$L2', '$L4'],
			["create", '$L8', "Date", '$L7.created_at'],
			["set", '$L11', '$L8.time'],
			["string.concat", '$L9', '$L7._id'],
			["string.concat", '$L10', '$L7.channel._id'],
			["create", '$L6', "VideoMetaData", '$L9', '$L7.title', '$L7.description', '$L11', '$L9', '$L7.length', '$L7.preview.large', NULL, '$L7.views', NULL, NULL],
			["push", '$P1', '$L6'],
			["math.add", '$L4', '$L4', 1],
			["jumpRel", -10]
		],
		'Authenticating:login' => [
			["callFunc", "checkAuthentication", '$P0']
		],
		'Authenticating:logout' => [
			["if!=than", '$S0.accessToken', NULL, 7],
			["create", '$L0', "Object"],
			["set", '$L0.method', "POST"],
			["string.concat", '$L0.url', "https://api.twitch.tv/kraken/oauth2/revoke?client_id=", '$P0.clientId', "&token=", '$S0.accessToken'],
			["create", '$L1', "Object"],
			["http.requestCall", '$L1', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L1'],
			["set", '$S0.accessToken', NULL]
		],
		'AdvancedRequestSupporter:advancedRequest' => [
			["create", '$L0', "Object"],
			["create", '$L0.url', "String"],
			["if!=than", '$P2.appendBaseUrl', 0, 1],
			["set", '$L0.url', "https://api.twitch.tv/kraken"],
			["string.concat", '$L0.url', '$L0.url', '$P2.url'],
			["set", '$L0.requestHeaders', '$P2.headers'],
			["set", '$L0.method', '$P2.method'],
			["set", '$L0.requestBody', '$P2.body'],
			["if!=than", '$P2.appendAuthorization', 0, 2],
			["callFunc", "checkAuthentication", '$P0'],
			["string.concat", '$L0.requestHeaders.Authorization', "Bearer ", '$S0.access_token'],
			["http.requestCall", '$L1', '$L0'],
			["if!=than", '$P2.checkErrors', 0, 1],
			["callFunc", "validateResponse", '$P0', '$L1'],
			["create", '$P1', "AdvancedRequestResponse"],
			["set", '$P1.status', '$L1.code'],
			["set", '$P1.headers', '$L1.responseHeaders'],
			["set", '$P1.body', '$L1.responseBody']
		],
		'startNewVideo' => [
			["create", '$L0', "Object"],
			["set", '$L0.method', "POST"],
			["string.concat", '$L0.url', "https://api.twitch.tv/kraken/videos?channel_id=", '$P4', "&title=", '$P2'],
			["if!=than", '$P3', NULL, 2],
			["if!=than", '$P3', "", 1],
			["string.concat", '$L0.url', '$L0.url', "&description=", '$P3'],
			["create", '$L0.requestHeaders', "Object"],
			["set", '$L0.requestHeaders.Client-ID', '$P0.clientId'],
			["set", '$L0.requestHeaders.Accept', "application/vnd.twitchtv.v5+json"],
			["string.concat", '$L0.requestHeaders.Authorization', "OAuth ", '$S0.accessToken'],
			["create", '$L1', "Object"],
			["http.requestCall", '$L1', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L1'],
			["create", '$L3', "String"],
			["stream.streamToString", '$L3', '$L1.responseBody'],
			["json.parse", '$P1', '$L3']
		],
		'uploadVideoParts' => [
			["set", '$L0', 1],
			["if>than", '$P3', 0, 12],
			["if>than", '$P3', 25000000, 4],
			["stream.makeLimitedStream", '$L1', '$P4', 25000000],
			["math.add", '$P3', '$P3', -25000000],
			["set", '$L2', 25000000],
			["jumpRel", 3],
			["set", '$L1', '$P4'],
			["set", '$L2', '$P3'],
			["set", '$P3', 0],
			["callFunc", "uploadChunk", '$P0', '$P1', '$P2', '$L0', '$L1', '$L2'],
			["math.add", '$L0', '$L0', 1],
			["jumpRel", -13]
		],
		'uploadChunk' => [
			["create", '$L0', "Object"],
			["set", '$L0.method', "PUT"],
			["string.concat", '$L0.url', '$P1', "?part=", '$P3', "&upload_token=", '$P2'],
			["create", '$L0.requestHeaders', "Object"],
			["string.concat", '$L0.requestHeaders.Content-Length', '$P5'],
			["set", '$L0.requestHeaders.Accept', "application/vnd.twitchtv.v5+json"],
			["set", '$L0.requestBody', '$P4'],
			["create", '$L1', "Object"],
			["http.requestCall", '$L1', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L1']
		],
		'completeNewVideo' => [
			["create", '$L0', "Object"],
			["set", '$L0.method', "POST"],
			["string.concat", '$L0.url', '$P1', "/complete?upload_token=", '$P2'],
			["create", '$L1', "Object"],
			["http.requestCall", '$L1', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L1']
		],
		'checkMandatory' => [
			["if==than", '$P1', NULL, 3],
			["string.concat", '$L1', "Field ", '$P2', " is mandatory"],
			["create", '$L0', "Error", '$L1', "IllegalArgument"],
			["throwError", '$L0']
		],
		'checkEmptyList' => [
			["size", '$L0', '$P1'],
			["if==than", '$L0', 0, 3],
			["string.concat", '$L2', "The list ", '$P2', " cannot be empty"],
			["create", '$L1', "Error", '$L2', "IllegalArgument"],
			["throwError", '$L1']
		],
		'checkEmpty' => [
			["if==than", '$P1', "", 3],
			["string.concat", '$L0', "Field ", '$P2', " is mandatory"],
			["create", '$L1', "Error", '$L0', "IllegalArgument"],
			["throwError", '$L1']
		],
		'checkEquals' => [
			["if!=than", '$P1', '$P2', 3],
			["string.concat", '$L0', '$P3', " does not match ", '$P4'],
			["create", '$L1', "Error", '$L0', "IllegalArgument"],
			["throwError", '$L1']
		],
		'checkGreater0' => [
			["if<=than", '$P1', 0, 3],
			["string.concat", '$L0', '$P2', " has to be greater than 0"],
			["create", '$L1', "Error", '$L0', "IllegalArgument"],
			["throwError", '$L1']
		],
		'checkAuthentication' => [
			["create", '$L0', "Date"],
			["if==than", '$S0.accessToken', NULL, 2],
			["callFunc", "authenticate", '$P0', "accessToken"],
			["return"],
			["callFunc", "authenticate", '$P0', "refreshToken"],
			["return"]
		],
		'authenticate' => [
			["if==than", '$P1', "refreshToken", 9],
			["create", '$L8', "Object"],
			["string.concat", '$L8.url', "https://api.twitch.tv/kraken/oauth2/token", "?grant_type=refresh_token", "&refresh_token=", '$S0.refreshToken', "&client_id=", '$P0.clientId', "&client_secret=", '$P0.clientSecret'],
			["set", '$L8.method', "POST"],
			["http.requestCall", '$L9', '$L8'],
			["stream.streamToString", '$L10', '$L9.responseBody'],
			["json.parse", '$L11', '$L10'],
			["set", '$S0.accessToken', '$L11.access_token'],
			["set", '$S0.refreshToken', '$L11.refresh_token'],
			["return"],
			["create", '$L0', "String"],
			["string.concat", '$L0', "https://api.twitch.tv/kraken/oauth2/authorize?client_id=", '$P0.clientId', "&redirect_uri=", '$P0.redirectUri', "&response_type=code&scope=channel_read+channel_editor&state=", '$P0.state', "&force_verify=true"],
			["awaitCodeRedirect", '$L1', '$L0', NULL, '$P0.redirectUri'],
			["create", '$L2', "Object"],
			["set", '$L2.method', "POST"],
			["string.concat", '$L2.url', "https://api.twitch.tv/kraken/oauth2/token", "?client_id=", '$P0.clientId', "&client_secret=", '$P0.clientSecret', "&code=", '$L1', "&grant_type=authorization_code", "&redirect_uri=", '$P0.redirectUri'],
			["http.requestCall", '$L5', '$L2'],
			["callFunc", "validateResponse", '$P0', '$L5'],
			["create", '$L6', "String"],
			["stream.streamToString", '$L6', '$L5.responseBody'],
			["json.parse", '$L7', '$L6'],
			["set", '$S0.accessToken', '$L7.access_token'],
			["set", '$S0.refreshToken', '$L7.refresh_token'],
			["set", '$S0.expireIn', '$L7.expires_in']
		],
		'validateResponse' => [
			["if>=than", '$P1.code', 400, 14],
			["stream.streamToString", '$L4', '$P1.responseBody'],
			["set", '$L2', ""],
			["debug.out", '$L4'],
			["if==than", '$P1.code', 401, 2],
			["create", '$L3', "Error", '$L2', "Authentication"],
			["throwError", '$L3'],
			["if==than", '$P1.code', 404, 2],
			["create", '$L3', "Error", '$L2', "NotFound"],
			["throwError", '$L3'],
			["if==than", '$P1.code', 503, 2],
			["create", '$L3', "Error", '$L2', "ServiceUnavailable"],
			["throwError", '$L3'],
			["create", '$L3', "Error", '$L2', "HTTP"],
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
	 */
	public function __construct(callable $redirectReceiver, string $clientId, string $clientSecret, string $redirectUri, string $state)
	{
		$this->interpreterStorage = array();
		$this->instanceDependencyStorage = ["redirectReceiver" => $redirectReceiver];
		$this->persistentStorage = array(array());
		InitSelfTest::initTest('Twitch');
		
		$this->interpreterStorage['clientId'] = $clientId;
		$this->interpreterStorage['clientSecret'] = $clientSecret;
		$this->interpreterStorage['redirectUri'] = $redirectUri;
		$this->interpreterStorage['state'] = $state;
		

		$ip = new Interpreter(new Sandbox(Twitch::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		if (array_key_exists("init",Twitch::$SERVICE_CODE)) {
			$parameters = [&$this->interpreterStorage];
		  $ip->callFunctionSync("init",$parameters );
		}
	}

	
	/**
	 * @param string $title
	 * @param string $description
	 * @param resource $stream
	 * @param int $size
	 * @param string $channelId
	 * @param string $mimeType
	 * @throws IllegalArgumentError
	 * @throws AuthenticationError
	 * @throws NotFoundError
	 * @throws HttpError
	 * @throws ServiceUnavailableError
	 * @throws \Exception
	 * @return VideoMetaData
	 */
	public function uploadVideo(string $title, string $description,  $stream, int $size, string $channelId, string $mimeType):VideoMetaData {
		Statistics::addCall("Twitch", "uploadVideo");
		$ip = new Interpreter(new Sandbox(Twitch::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null, $title, $description, $stream, $size, $channelId, $mimeType];
		$ip->callFunctionSync('uploadVideo', $auxArray);
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
	 * @param string $query
	 * @param int $offset
	 * @param int $limit
	 * @throws IllegalArgumentError
	 * @throws AuthenticationError
	 * @throws NotFoundError
	 * @throws HttpError
	 * @throws ServiceUnavailableError
	 * @throws \Exception
	 * @return array
	 */
	public function searchVideos(string $query, int $offset, int $limit):array {
		Statistics::addCall("Twitch", "searchVideos");
		$ip = new Interpreter(new Sandbox(Twitch::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null, $query, $offset, $limit];
		$ip->callFunctionSync('searchVideos', $auxArray);
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
	 * @param string $videoId
	 * @throws IllegalArgumentError
	 * @throws AuthenticationError
	 * @throws NotFoundError
	 * @throws HttpError
	 * @throws ServiceUnavailableError
	 * @throws \Exception
	 * @return VideoMetaData
	 */
	public function getVideo(string $videoId):VideoMetaData {
		Statistics::addCall("Twitch", "getVideo");
		$ip = new Interpreter(new Sandbox(Twitch::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null, $videoId];
		$ip->callFunctionSync('getVideo', $auxArray);
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
	 * @param string $channelId
	 * @throws IllegalArgumentError
	 * @throws AuthenticationError
	 * @throws NotFoundError
	 * @throws HttpError
	 * @throws ServiceUnavailableError
	 * @throws \Exception
	 * @return ChannelMetaData
	 */
	public function getChannel(string $channelId):ChannelMetaData {
		Statistics::addCall("Twitch", "getChannel");
		$ip = new Interpreter(new Sandbox(Twitch::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null, $channelId];
		$ip->callFunctionSync('getChannel', $auxArray);
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
	 * @return ChannelMetaData
	 */
	public function getOwnChannel():ChannelMetaData {
		Statistics::addCall("Twitch", "getOwnChannel");
		$ip = new Interpreter(new Sandbox(Twitch::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null];
		$ip->callFunctionSync('getOwnChannel', $auxArray);
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
	 * @param string $channelId
	 * @param int $offset
	 * @param int $limit
	 * @throws IllegalArgumentError
	 * @throws AuthenticationError
	 * @throws NotFoundError
	 * @throws HttpError
	 * @throws ServiceUnavailableError
	 * @throws \Exception
	 * @return array
	 */
	public function listVideosForChannel(string $channelId, int $offset, int $limit):array {
		Statistics::addCall("Twitch", "listVideosForChannel");
		$ip = new Interpreter(new Sandbox(Twitch::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null, $channelId, $offset, $limit];
		$ip->callFunctionSync('listVideosForChannel', $auxArray);
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
		Statistics::addCall("Twitch", "login");
		$ip = new Interpreter(new Sandbox(Twitch::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Twitch", "logout");
		$ip = new Interpreter(new Sandbox(Twitch::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Twitch", "advancedRequest");
		$ip = new Interpreter(new Sandbox(Twitch::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		$ip = new Interpreter(new Sandbox(Twitch::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		return $ip->saveAsString();
	}

	/**
	 * @param string $savedState
	 */
	public function loadAsString($savedState) {
		$sandbox = new Sandbox(Twitch::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage);
		$ip = new Interpreter($sandbox);
		$ip->loadAsString($savedState);
		$this->persistentStorage = $sandbox->persistentStorage;
	}
}
