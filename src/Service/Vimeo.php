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

class Vimeo implements Video, AdvancedRequestSupporter
{
	private static $SERVICE_CODE = [
		'uploadVideo' => [
			["callFunc", "checkMandatory", '$P0', '$P2', "title"],
			["callFunc", "checkMandatory", '$P0', '$P4', "video"],
			["callFunc", "checkMandatory", '$P0', '$P3', "desctiption"],
			["callFunc", "checkMandatory", '$P0', '$P5', "size"],
			["callFunc", "checkEmpty", '$P0', '$P2', "title"],
			["callFunc", "checkEmpty", '$P0', '$P3', "description"],
			["callFunc", "checkGreater0", '$P0', '$P5', "size"],
			["callFunc", "checkAuthentication", '$P0'],
			["callFunc", "checkQuota", '$P0', '$P5'],
			["callFunc", "generateUploadTicket", '$P0', '$L0', '$P6'],
			["callFunc", "uploadStream", '$P0', '$L0.uploadUri', '$P4', '$P5', 0, '$P7'],
			["callFunc", "verifyUpload", '$P0', '$L0.uploadUri', '$P4', '$P5', 0, '$P7'],
			["callFunc", "completeUpload", '$P0', '$L1', '$L0.completeUri'],
			["callFunc", "getVideo", '$P0', '$P1', '$L1', '$P2', '$P3']
		],
		'searchVideos' => [
			["callFunc", "checkMandatory", '$P0', '$P2', "query"],
			["callFunc", "checkEmpty", '$P0', '$P2', "query"],
			["callFunc", "getVideosFromAPI", '$P0', '$P1', '$P2', '$P3', '$P4', "search"]
		],
		'listVideosForChannel' => [
			["callFunc", "checkMandatory", '$P0', '$P2', "channel ID"],
			["callFunc", "checkEmpty", '$P0', '$P2', "channel ID"],
			["callFunc", "getVideosFromAPI", '$P0', '$P1', '$P2', '$P3', '$P4', "channel"]
		],
		'getVideo' => [
			["callFunc", "checkMandatory", '$P0', '$P2', "video ID"],
			["callFunc", "checkEmpty", '$P0', '$P2', "video ID"],
			["create", '$L0', "Object"],
			["string.concat", '$L0.url', "https://api.vimeo.com/videos/", '$P2'],
			["set", '$L0.method', "GET"],
			["set", '$L5', 0],
			["if!=than", '$P3', NULL, 5],
			["if!=than", '$P3', "", 4],
			["if!=than", '$P4', NULL, 3],
			["if!=than", '$P4', "", 2],
			["set", '$L5', 1],
			["set", '$L0.method', "PATCH"],
			["if==than", '$L5', 1, 6],
			["create", '$L6', "Object"],
			["set", '$L6.name', '$P3'],
			["set", '$L6.description', '$P4'],
			["json.stringify", '$L7', '$L6'],
			["stream.stringToStream", '$L8', '$L7'],
			["set", '$L0.requestBody', '$L8'],
			["create", '$L0.requestHeaders', "Object"],
			["callFunc", "generateUnauthenticatedTokens", '$P0', '$L1'],
			["string.concat", '$L0.requestHeaders.Authorization', "Bearer ", '$L1'],
			["set", '$L0.requestHeaders.Content-Type', "application/json"],
			["set", '$L9', 0],
			["if!=than", '$L7', NULL, 1],
			["size", '$L9', '$L7'],
			["string.concat", '$L0.requestHeaders.Content-Length', '$L9'],
			["create", '$L2', "Object"],
			["http.requestCall", '$L2', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L2'],
			["stream.streamToString", '$L3', '$L2.responseBody'],
			["json.parse", '$L4', '$L3'],
			["callFunc", "extractVideoMetaData", '$P0', '$P1', '$L4']
		],
		'getChannel' => [
			["callFunc", "checkMandatory", '$P0', '$P2', "channel ID"],
			["callFunc", "checkEmpty", '$P0', '$P2', "channel ID"],
			["create", '$L0', "Object"],
			["set", '$L0.method', "GET"],
			["string.concat", '$L0.url', "https://api.vimeo.com/users/", '$P2'],
			["create", '$L0.requestHeaders', "Object"],
			["callFunc", "generateUnauthenticatedTokens", '$P0', '$L1'],
			["string.concat", '$L0.requestHeaders.Authorization', "Bearer ", '$L1'],
			["create", '$L2', "Object"],
			["http.requestCall", '$L2', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L2'],
			["stream.streamToString", '$L3', '$L2.responseBody'],
			["json.parse", '$L4', '$L3'],
			["callFunc", "extractChannelMetaData", '$P0', '$P1', '$L4']
		],
		'getOwnChannel' => [
			["callFunc", "checkAuthentication", '$P0'],
			["create", '$L0', "Object"],
			["set", '$L0.method', "GET"],
			["string.concat", '$L0.url', "https://api.vimeo.com/me"],
			["create", '$L0.requestHeaders', "Object"],
			["string.concat", '$L0.requestHeaders.Authorization', "Bearer ", '$S0.accessToken'],
			["create", '$L1', "Object"],
			["http.requestCall", '$L1', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L1'],
			["stream.streamToString", '$L2', '$L1.responseBody'],
			["json.parse", '$L3', '$L2'],
			["callFunc", "extractChannelMetaData", '$P0', '$P1', '$L3']
		],
		'Authenticating:login' => [
			["callFunc", "checkAuthentication", '$P0']
		],
		'Authenticating:logout' => [
			["if!=than", '$S0.accessToken', NULL, 10],
			["create", '$L0', "Object"],
			["set", '$L0.method', "DELETE"],
			["set", '$L0.url', "https://api.vimeo.com/tokens"],
			["create", '$L0.requestHeaders', "Object"],
			["string.concat", '$L0.requestHeaders.Authorization', "Bearer ", '$S0.accessToken'],
			["create", '$L1', "Object"],
			["http.requestCall", '$L1', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L1'],
			["set", '$S0.accessToken', NULL],
			["set", '$S0.accessTokenUnauth', NULL]
		],
		'AdvancedRequestSupporter:advancedRequest' => [
			["create", '$L0', "Object"],
			["create", '$L0.url', "String"],
			["if!=than", '$P2.appendBaseUrl', 0, 1],
			["set", '$L0.url', "https://api.vimeo.com"],
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
		'checkQuota' => [
			["create", '$L0', "Object"],
			["set", '$L0.method', "GET"],
			["set", '$L0.url', "https://api.vimeo.com/me"],
			["create", '$L0.requestHeaders', "Object"],
			["string.concat", '$L0.requestHeaders.Authorization', "Bearer ", '$S0.accessToken'],
			["create", '$L1', "Object"],
			["http.requestCall", '$L1', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L1'],
			["stream.streamToString", '$L2', '$L1.responseBody'],
			["json.parse", '$L3', '$L2'],
			["set", '$L4', '$L3.upload_quota.space.free'],
			["if<than", '$L4', '$P1', 3],
			["string.concat", '$L5', "This upload would exceed your Vimeo upload quota"],
			["create", '$L6', "Error", '$L5', "Http"],
			["throwError", '$L6']
		],
		'generateUploadTicket' => [
			["create", '$L0', "Object"],
			["set", '$L0.method', "POST"],
			["set", '$L0.url', "https://api.vimeo.com/me/videos"],
			["if!=than", '$P2', NULL, 2],
			["if!=than", '$P2', "", 1],
			["string.concat", '$L0.url', "https://api.vimeo.com/users/", '$P2', "/videos"],
			["create", '$L4', "String", "type=streaming"],
			["stream.stringToStream", '$L5', '$L4'],
			["set", '$L0.requestBody', '$L5'],
			["create", '$L0.requestHeaders', "Object"],
			["size", '$L6', '$L4'],
			["string.concat", '$L0.requestHeaders.Content-Length', '$L6'],
			["string.concat", '$L0.requestHeaders.Authorization', "Bearer ", '$S0.accessToken'],
			["create", '$L1', "Object"],
			["http.requestCall", '$L1', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L1'],
			["stream.streamToString", '$L2', '$L1.responseBody'],
			["json.parse", '$L3', '$L2'],
			["create", '$P1', "Object"],
			["set", '$P1.uploadUri', '$L3.upload_link_secure'],
			["set", '$P1.completeUri', '$L3.complete_uri']
		],
		'uploadStream' => [
			["create", '$L0', "Object"],
			["set", '$L0.method', "PUT"],
			["set", '$L0.url', '$P1'],
			["create", '$L0.requestHeaders', "Object"],
			["string.concat", '$L0.requestHeaders.Content-Length', '$P3'],
			["set", '$L0.requestHeaders.Content-Type', '$P5'],
			["if!=than", '$P4', 0, 2],
			["math.add", '$L2', '$P4', 1],
			["string.concat", '$L0.requestHeaders.Content-Range', "bytes ", '$L2', "-", '$P3', "/", '$P3'],
			["set", '$L0.requestBody', '$P2'],
			["create", '$L1', "Object"],
			["http.requestCall", '$L1', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L1']
		],
		'verifyUpload' => [
			["create", '$L0', "Object"],
			["set", '$L0.method', "PUT"],
			["set", '$L0.url', '$P1'],
			["create", '$L0.requestHeaders', "Object"],
			["set", '$L0.requestHeaders.Content-Length', "0"],
			["string.concat", '$L0.requestHeaders.Content-Range', "bytes *", "/", "*"],
			["create", '$L1', "Object"],
			["http.requestCall", '$L1', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L1'],
			["string.split", '$L3', '$L1.responseHeaders.Range', "-"],
			["get", '$L3', '$L3', 1],
			["string.concat", '$L7', '$P3'],
			["if==than", '$L3', '$L7', 1],
			["return"],
			["math.add", '$L4', '$P4', 1],
			["if<=than", '$L4', 3, 3],
			["callFunc", "uploadStream", '$P0', '$P1', '$P2', '$P3', '$L3', '$P5'],
			["callFunc", "verifyUpload", '$P0', '$P1', '$P2', '$P3', '$L4', '$P5'],
			["return"],
			["create", '$L5', "Error", "Upload failed"],
			["throwError", '$L5']
		],
		'completeUpload' => [
			["create", '$L0', "Object"],
			["set", '$L0.method', "DELETE"],
			["string.concat", '$L0.url', "https://api.vimeo.com", '$P2'],
			["create", '$L0.requestHeaders', "Object"],
			["string.concat", '$L0.requestHeaders.Authorization', "Bearer ", '$S0.accessToken'],
			["create", '$L1', "Object"],
			["http.requestCall", '$L1', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L1'],
			["set", '$L2', '$L1.responseHeaders.Location'],
			["string.substr", '$P1', '$L2', 8]
		],
		'generateUnauthenticatedTokens' => [
			["if!=than", '$S0.accessToken', NULL, 3],
			["if!=than", '$S0.accessToken', "", 2],
			["set", '$P1', '$S0.accessToken'],
			["return"],
			["if!=than", '$S0.accessTokenUnauth', NULL, 3],
			["if!=than", '$S0.accessTokenUnauth', "", 2],
			["set", '$P1', '$S0.accessTokenUnauth'],
			["return"],
			["create", '$L0', "Object"],
			["set", '$L0.method', "POST"],
			["set", '$L0.url', "https://api.vimeo.com/oauth/authorize/client?grant_type=client_credentials"],
			["create", '$L0.requestHeaders', "Object"],
			["string.concat", '$L1', '$P0.clientId', ":", '$P0.clientSecret'],
			["string.base64encode", '$L1', '$L1'],
			["string.concat", '$L0.requestHeaders.Authorization', "basic ", '$L1'],
			["set", '$L0.requestHeaders.Accept', "application/vnd.vimeo.*+json; version=3.0"],
			["create", '$L2', "Object"],
			["http.requestCall", '$L2', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L2'],
			["stream.streamToString", '$L3', '$L2.responseBody'],
			["json.parse", '$L4', '$L3'],
			["set", '$P1', '$L4.access_token'],
			["set", '$S0.accessTokenUnauth', '$L4.access_token']
		],
		'checkAuthentication' => [
			["if==than", '$S0.accessToken', NULL, 1],
			["callFunc", "authenticate", '$P0']
		],
		'authenticate' => [
			["create", '$L0', "String"],
			["string.concat", '$L0', "https://api.vimeo.com/oauth/authorize?client_id=", '$P0.clientId', "&redirect_uri=", '$P0.redirectUri', "&response_type=code&scope=public+private+upload+edit&state=", '$P0.state'],
			["awaitCodeRedirect", '$L1', '$L0', NULL, '$P0.redirectUri'],
			["create", '$L2', "Object"],
			["set", '$L2.method', "POST"],
			["string.concat", '$L2.url', "https://api.vimeo.com/oauth/access_token"],
			["create", '$L7', "Object"],
			["set", '$L7.code', '$L1'],
			["set", '$L7.grant_type', "authorization_code"],
			["set", '$L7.redirect_uri', '$P0.redirectUri'],
			["json.stringify", '$L8', '$L7'],
			["stream.stringToStream", '$L9', '$L8'],
			["set", '$L2.requestBody', '$L9'],
			["create", '$L2.requestHeaders', "Object"],
			["string.concat", '$L3', '$P0.clientId', ":", '$P0.clientSecret'],
			["string.base64encode", '$L3', '$L3'],
			["string.concat", '$L2.requestHeaders.Authorization', "basic ", '$L3'],
			["set", '$L2.requestHeaders.Content-Type', "application/json"],
			["size", '$L10', '$L8'],
			["string.concat", '$L10', '$L10'],
			["set", '$L2.requestHeaders.Content-Length', '$L10'],
			["http.requestCall", '$L4', '$L2'],
			["callFunc", "validateResponse", '$P0', '$L4'],
			["create", '$L5', "String"],
			["stream.streamToString", '$L5', '$L4.responseBody'],
			["json.parse", '$L6', '$L5'],
			["set", '$S0.accessToken', '$L6.access_token']
		],
		'getVideosFromAPI' => [
			["callFunc", "checkSmallerEquals", '$P0', '$P4', 100, "limit"],
			["if==than", '$P3', NULL, 1],
			["set", '$P3', 0],
			["if<than", '$P3', 0, 1],
			["set", '$P3', 0],
			["if==than", '$P4', NULL, 1],
			["set", '$P4', 50],
			["if<=than", '$P4', 0, 1],
			["set", '$P4', 50],
			["math.add", '$L1', '$P3', '$P4'],
			["math.multiply", '$L2', '$P3', 0.01],
			["math.add", '$L2', '$L2', 1],
			["math.multiply", '$L3', '$L1', 0.01],
			["math.add", '$L3', '$L3', 1],
			["callFunc", "getVideoPage", '$P0', '$L4', '$P2', '$L2', 100, '$P5'],
			["callFunc", "modulo", '$P0', '$L5', '$P3', 100],
			["math.add", '$L6', '$L5', '$P4'],
			["set", '$L7', '$L6'],
			["if>than", '$L7', 100, 1],
			["set", '$L7', 100],
			["math.add", '$L6', '$L6', -100],
			["create", '$P1', "Array"],
			["callFunc", "pushVideosToArray", '$P0', '$P1', '$L4', '$L5', '$L7'],
			["if!=than", '$L2', '$L3', 2],
			["callFunc", "getVideoPage", '$P0', '$L8', '$P2', '$L3', 100, '$P5'],
			["callFunc", "pushVideosToArray", '$P0', '$P1', '$L8', 0, '$L6']
		],
		'getVideoPage' => [
			["create", '$L0', "Object"],
			["set", '$L0.method', "GET"],
			["set", '$L0.url', "https://api.vimeo.com/"],
			["if==than", '$P5', "search", 4],
			["string.concat", '$L0.url', '$L0.url', "videos?"],
			["if!=than", '$P2', NULL, 2],
			["if!=than", '$P2', "", 1],
			["string.concat", '$L0.url', '$L0.url', "query=", '$P2', "&"],
			["if==than", '$P5', "channel", 1],
			["string.concat", '$L0.url', '$L0.url', "users/", '$P2', "/videos?"],
			["string.concat", '$L0.url', '$L0.url', "page=", '$P3', "&per_page=", '$P4'],
			["create", '$L0.requestHeaders', "Object"],
			["callFunc", "generateUnauthenticatedTokens", '$P0', '$L1'],
			["string.concat", '$L0.requestHeaders.Authorization', "Bearer ", '$L1'],
			["create", '$L2', "Object"],
			["http.requestCall", '$L2', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L2'],
			["stream.streamToString", '$L3', '$L2.responseBody'],
			["json.parse", '$L4', '$L3'],
			["set", '$P1', '$L4.data']
		],
		'modulo' => [
			["math.divide", '$L0', '$P2', '$P3'],
			["math.floor", '$L0', '$L0'],
			["math.multiply", '$L0', '$P3', '$L0'],
			["math.multiply", '$L0', '$L0', -1],
			["math.add", '$P1', '$P2', '$L0']
		],
		'pushVideosToArray' => [
			["size", '$L0', '$P2'],
			["set", '$L1', '$P3'],
			["if<than", '$L1', '$L0', 6],
			["if<than", '$L1', '$P4', 5],
			["get", '$L2', '$P2', '$L1'],
			["callFunc", "extractVideoMetaData", '$P0', '$L3', '$L2'],
			["push", '$P1', '$L3'],
			["math.add", '$L1', '$L1', 1],
			["jumpRel", -7]
		],
		'extractVideoMetaData' => [
			["create", '$L0', "Date", '$P2.created_time'],
			["set", '$L6', '$L0.time'],
			["set", '$L1', '$P2.uri'],
			["string.substr", '$L1', '$L1', 8],
			["string.concat", '$L2', '$P2.user.uri'],
			["string.substr", '$L2', '$L2', 7],
			["set", '$L3', '$P2.pictures.sizes'],
			["size", '$L4', '$L3'],
			["math.add", '$L4', '$L4', -1],
			["get", '$L5', '$L3', '$L4'],
			["create", '$P1', "VideoMetaData", '$L1', '$P2.name', '$P2.description', '$L6', '$L2', '$P2.duration', '$L5.link', '$P2.embed.html', '$P2.stats.plays', '$P2.metadata.connections.likes.total', NULL]
		],
		'extractChannelMetaData' => [
			["set", '$L0', '$P2.uri'],
			["string.substr", '$L0', '$L0', 7],
			["set", '$L1', '$P2.pictures.sizes'],
			["size", '$L2', '$L1'],
			["math.add", '$L2', '$L2', -1],
			["get", '$L3', '$L1', '$L2'],
			["create", '$P1', "ChannelMetaData", '$L0', '$P2.name', '$P2.metadata.connections.followers.total', '$P2.link', '$L2.link', NULL]
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
		'checkSmallerEquals' => [
			["if>than", '$P1', '$P2', 3],
			["string.concat", '$L0', '$P3', " has to be smaller than or equal to ", '$P2'],
			["create", '$L1', "Error", '$L0', "IllegalArgument"],
			["throwError", '$L1']
		],
		'validateResponse' => [
			["if>=than", '$P1.code', 400, 17],
			["json.parse", '$L0', '$P1.responseBody'],
			["set", '$L2', '$L0.code'],
			["if!=than", '$L0.message', NULL, 1],
			["string.concat", '$L2', '; $L0.message'],
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
	 */
	public function __construct(callable $redirectReceiver, string $clientId, string $clientSecret, string $redirectUri, string $state)
	{
		$this->interpreterStorage = array();
		$this->instanceDependencyStorage = ["redirectReceiver" => $redirectReceiver];
		$this->persistentStorage = array(array());
		InitSelfTest::initTest('Vimeo');
		
		$this->interpreterStorage['clientId'] = $clientId;
		$this->interpreterStorage['clientSecret'] = $clientSecret;
		$this->interpreterStorage['redirectUri'] = $redirectUri;
		$this->interpreterStorage['state'] = $state;
		

		$ip = new Interpreter(new Sandbox(Vimeo::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		if (array_key_exists("init",Vimeo::$SERVICE_CODE)) {
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
		Statistics::addCall("Vimeo", "uploadVideo");
		$ip = new Interpreter(new Sandbox(Vimeo::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Vimeo", "searchVideos");
		$ip = new Interpreter(new Sandbox(Vimeo::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Vimeo", "getVideo");
		$ip = new Interpreter(new Sandbox(Vimeo::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Vimeo", "getChannel");
		$ip = new Interpreter(new Sandbox(Vimeo::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Vimeo", "getOwnChannel");
		$ip = new Interpreter(new Sandbox(Vimeo::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Vimeo", "listVideosForChannel");
		$ip = new Interpreter(new Sandbox(Vimeo::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Vimeo", "login");
		$ip = new Interpreter(new Sandbox(Vimeo::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Vimeo", "logout");
		$ip = new Interpreter(new Sandbox(Vimeo::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Vimeo", "advancedRequest");
		$ip = new Interpreter(new Sandbox(Vimeo::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		$ip = new Interpreter(new Sandbox(Vimeo::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		return $ip->saveAsString();
	}

	/**
	 * @param string $savedState
	 */
	public function loadAsString($savedState) {
		$sandbox = new Sandbox(Vimeo::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage);
		$ip = new Interpreter($sandbox);
		$ip->loadAsString($savedState);
		$this->persistentStorage = $sandbox->persistentStorage;
	}
}
