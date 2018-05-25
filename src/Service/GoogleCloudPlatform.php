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

use CloudRail\Interfaces\BusinessCloudStorage;
use CloudRail\Type\Bucket;
use CloudRail\Type\BusinessFileMetaData;
use CloudRail\Interfaces\AdvancedRequestSupporter;
use CloudRail\Type\AdvancedRequestSpecification;
use CloudRail\Type\AdvancedRequestResponse;
use CloudRail\Type\CloudRailError;

class GoogleCloudPlatform implements BusinessCloudStorage, AdvancedRequestSupporter
{
	private static $SERVICE_CODE = [
		'init' => [
			["set", '$P0.baseUrl', "https://www.googleapis.com"],
			["string.concat", '$P0.storageBase', '$P0.baseUrl', "/storage/v1"],
			["string.concat", '$P0.authBase', '$P0.baseUrl', "/oauth2/v4/token"],
			["string.substring", '$P0.privateKey', '$P0.privateKey', 28],
			["size", '$L0', '$P0.privateKey'],
			["math.add", '$L0', '$L0', -26],
			["string.substring", '$P0.privateKey', '$P0.privateKey', 0, '$L0']
		],
		'listBuckets' => [
			["callFunc", "checkAuthentication", '$P0'],
			["create", '$L0', "Object"],
			["set", '$L0.method', "GET"],
			["string.concat", '$L0.url', '$P0.storageBase', "/b?project=", '$P0.projectId'],
			["create", '$L0.requestHeaders', "Object"],
			["string.concat", '$L0.requestHeaders.Authorization', "Bearer ", '$P0.accessToken'],
			["http.requestCall", '$L1', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L1'],
			["json.parse", '$L2', '$L1.responseBody'],
			["create", '$P1', "Array"],
			["size", '$L3', '$L2.items'],
			["create", '$L4', "Number"],
			["if<than", '$L4', '$L3', 7],
			["get", '$L5', '$L2.items', '$L4'],
			["create", '$L6', "Bucket"],
			["set", '$L6.name', '$L5.name'],
			["set", '$L6.identifier', '$L5.id'],
			["push", '$P1', '$L6'],
			["math.add", '$L4', '$L4', 1],
			["jumpRel", -8]
		],
		'createBucket' => [
			["callFunc", "checkNull", '$P0', '$P2'],
			["callFunc", "checkAuthentication", '$P0'],
			["create", '$L0', "Object"],
			["set", '$L0.method', "POST"],
			["string.concat", '$L0.url', '$P0.storageBase', "/b?project=", '$P0.projectId'],
			["create", '$L0.requestHeaders', "Object"],
			["string.concat", '$L0.requestHeaders.Authorization', "Bearer ", '$P0.accessToken'],
			["set", '$L0.requestHeaders.Content-Type', "application/json"],
			["create", '$L1', "Object"],
			["set", '$L1.name', '$P2'],
			["json.stringify", '$L2', '$L1'],
			["stream.stringToStream", '$L3', '$L2'],
			["set", '$L0.requestBody', '$L3'],
			["http.requestCall", '$L1', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L1'],
			["json.parse", '$L2', '$L1.responseBody'],
			["create", '$P1', "Bucket"],
			["set", '$P1.name', '$L2.name'],
			["set", '$P1.identifier', '$L2.id']
		],
		'deleteBucket' => [
			["callFunc", "checkBucket", '$P0', '$P1'],
			["callFunc", "checkAuthentication", '$P0'],
			["create", '$L0', "Object"],
			["set", '$L0.method', "DELETE"],
			["string.concat", '$L0.url', '$P0.storageBase', "/b/", '$P1.name'],
			["create", '$L0.requestHeaders', "Object"],
			["string.concat", '$L0.requestHeaders.Authorization', "Bearer ", '$P0.accessToken'],
			["http.requestCall", '$L1', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L1']
		],
		'listFiles' => [
			["callFunc", "checkBucket", '$P0', '$P2'],
			["callFunc", "checkAuthentication", '$P0'],
			["create", '$L0', "Object"],
			["set", '$L0.method', "GET"],
			["string.concat", '$L0.url', '$P0.storageBase', "/b/", '$P2.name', "/o?maxResults=1000"],
			["create", '$L0.requestHeaders', "Object"],
			["string.concat", '$L0.requestHeaders.Authorization', "Bearer ", '$P0.accessToken'],
			["http.requestCall", '$L1', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L1'],
			["json.parse", '$L2', '$L1.responseBody'],
			["create", '$P1', "Array"],
			["size", '$L3', '$L2.items'],
			["create", '$L4', "Number"],
			["if<than", '$L4', '$L3', 6],
			["get", '$L5', '$L2.items', '$L4'],
			["if==than", '$L5.timeDeleted', NULL, 2],
			["callFunc", "makeMeta", '$P0', '$L6', '$L5'],
			["push", '$P1', '$L6'],
			["math.add", '$L4', '$L4', 1],
			["jumpRel", -7],
			["if!=than", '$L2.nextPageToken', NULL, 7],
			["string.concat", '$L0.url', '$P0.storageBase', "/b/", '$P2.name', "/o?maxResults=1000", "&pageToken=", '$L2.nextPageToken'],
			["create", '$L0.requestHeaders', "Object"],
			["string.concat", '$L0.requestHeaders.Authorization', "Bearer ", '$P0.accessToken'],
			["http.requestCall", '$L1', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L1'],
			["json.parse", '$L2', '$L1.responseBody'],
			["jumpRel", -17]
		],
		'listFilesWithPrefix' => [
			["callFunc", "checkBucket", '$P0', '$P2'],
			["callFunc", "checkPrefix", '$P0', '$P3'],
			["callFunc", "checkAuthentication", '$P0'],
			["create", '$L0', "Object"],
			["set", '$L0.method', "GET"],
			["string.concat", '$L0.url', '$P0.storageBase', "/b/", '$P2.name', "/o?maxResults=1000", "&prefix=", '$P3'],
			["create", '$L0.requestHeaders', "Object"],
			["string.concat", '$L0.requestHeaders.Authorization', "Bearer ", '$P0.accessToken'],
			["http.requestCall", '$L1', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L1'],
			["json.parse", '$L2', '$L1.responseBody'],
			["create", '$P1', "Array"],
			["size", '$L3', '$L2.items'],
			["create", '$L4', "Number"],
			["if<than", '$L4', '$L3', 6],
			["get", '$L5', '$L2.items', '$L4'],
			["if==than", '$L5.timeDeleted', NULL, 2],
			["callFunc", "makeMeta", '$P0', '$L6', '$L5'],
			["push", '$P1', '$L6'],
			["math.add", '$L4', '$L4', 1],
			["jumpRel", -7],
			["if!=than", '$L2.nextPageToken', NULL, 7],
			["string.concat", '$L0.url', '$P0.storageBase', "/b/", '$P2.name', "/o?maxResults=1000", "&pageToken=", '$L2.nextPageToken'],
			["create", '$L0.requestHeaders', "Object"],
			["string.concat", '$L0.requestHeaders.Authorization', "Bearer ", '$P0.accessToken'],
			["http.requestCall", '$L1', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L1'],
			["json.parse", '$L2', '$L1.responseBody'],
			["jumpRel", -17]
		],
		'checkPrefix' => [
			["if==than", '$P1', NULL, 2],
			["create", '$L1', "Error", "Prefix supplied is null", "IllegalArgument"],
			["throwError", '$L1']
		],
		'getFileMetadata' => [
			["callFunc", "checkBucket", '$P0', '$P2'],
			["callFunc", "checkNull", '$P0', '$P3'],
			["callFunc", "checkAuthentication", '$P0'],
			["create", '$L0', "Object"],
			["set", '$L0.method', "GET"],
			["callFunc", "encodeObjectName", '$P0', '$L3', '$P3'],
			["string.concat", '$L0.url', '$P0.storageBase', "/b/", '$P2.name', "/o/", '$L3'],
			["create", '$L0.requestHeaders', "Object"],
			["string.concat", '$L0.requestHeaders.Authorization', "Bearer ", '$P0.accessToken'],
			["http.requestCall", '$L1', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L1'],
			["json.parse", '$L2', '$L1.responseBody'],
			["callFunc", "makeMeta", '$P0', '$P1', '$L2']
		],
		'deleteFile' => [
			["callFunc", "checkBucket", '$P0', '$P2'],
			["callFunc", "checkNull", '$P0', '$P1'],
			["callFunc", "checkAuthentication", '$P0'],
			["create", '$L0', "Object"],
			["set", '$L0.method', "DELETE"],
			["callFunc", "encodeObjectName", '$P0', '$L2', '$P1'],
			["string.concat", '$L0.url', '$P0.storageBase', "/b/", '$P2.name', "/o/", '$L2'],
			["create", '$L0.requestHeaders', "Object"],
			["string.concat", '$L0.requestHeaders.Authorization', "Bearer ", '$P0.accessToken'],
			["http.requestCall", '$L1', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L1']
		],
		'uploadFile' => [
			["callFunc", "checkBucket", '$P0', '$P1'],
			["callFunc", "checkNull", '$P0', '$P2'],
			["callFunc", "checkNull", '$P0', '$P3'],
			["callFunc", "checkSize", '$P0', '$P4'],
			["callFunc", "checkAuthentication", '$P0'],
			["callFunc", "encodeObjectName", '$P0', '$L1', '$P2'],
			["callFunc", "initUpload", '$P0', '$L0', '$P1', '$L1'],
			["callFunc", "performUpload", '$P0', '$L0', '$P3', '$P4']
		],
		'downloadFile' => [
			["callFunc", "checkBucket", '$P0', '$P3'],
			["callFunc", "checkNull", '$P0', '$P2'],
			["callFunc", "checkAuthentication", '$P0'],
			["create", '$L0', "Object"],
			["set", '$L0.method', "GET"],
			["callFunc", "encodeObjectName", '$P0', '$L2', '$P2'],
			["string.concat", '$L0.url', '$P0.storageBase', "/b/", '$P3.name', "/o/", '$L2', "?alt=media"],
			["create", '$L0.requestHeaders', "Object"],
			["string.concat", '$L0.requestHeaders.Authorization', "Bearer ", '$P0.accessToken'],
			["http.requestCall", '$L1', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L1'],
			["set", '$P1', '$L1.responseBody']
		],
		'AdvancedRequestSupporter:advancedRequest' => [
			["create", '$L0', "Object"],
			["if!=than", '$P2.appendBaseUrl', 0, 3],
			["callFunc", "checkAuthentication", '$P0'],
			["string.concat", '$L0.url', '$P0.storageBase', '$P2.url'],
			["jumpRel", 1],
			["set", '$L0.url', '$P2.url'],
			["set", '$L0.requestHeaders', '$P2.headers'],
			["set", '$L0.method', '$P2.method'],
			["if!=than", '$P2.body', NULL, 1],
			["set", '$L0.requestBody', '$P2.body'],
			["if==than", '$L0.requestHeaders', NULL, 1],
			["create", '$L0.requestHeaders', "Object"],
			["if!=than", '$P2.appendAuthorization', 0, 2],
			["callFunc", "checkAuthentication", '$P0'],
			["string.concat", '$L0.requestHeaders.Authorization', "Bearer ", '$P0.accessToken'],
			["http.requestCall", '$L1', '$L0'],
			["if!=than", '$P2.checkErrors', 0, 1],
			["callFunc", "validateResponse", '$P0', '$L1'],
			["create", '$P1', "AdvancedRequestResponse"],
			["set", '$P1.status', '$L1.code'],
			["set", '$P1.headers', '$L1.responseHeaders'],
			["set", '$P1.body', '$L1.responseBody']
		],
		'initUpload' => [
			["create", '$L0', "Object"],
			["set", '$L0.method', "POST"],
			["string.concat", '$L0.url', '$P0.baseUrl', "/upload/storage/v1/b/", '$P2.name', "/o?uploadType=resumable&name=", '$P3'],
			["create", '$L0.requestHeaders', "Object"],
			["string.concat", '$L0.requestHeaders.Authorization', "Bearer ", '$P0.accessToken'],
			["http.requestCall", '$L1', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L1'],
			["set", '$P1', '$L1.responseHeaders.Location']
		],
		'performUpload' => [
			["create", '$L0', "Object"],
			["set", '$L0.method', "PUT"],
			["set", '$L0.url', '$P1'],
			["create", '$L0.requestHeaders', "Object"],
			["string.concat", '$L0.requestHeaders.Authorization', "Bearer ", '$P0.accessToken'],
			["string.concat", '$L0.requestHeaders.Content-Length', '$P3'],
			["set", '$L0.requestBody', '$P2'],
			["http.requestCall", '$L1', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L1']
		],
		'makeMeta' => [
			["create", '$P1', "BusinessFileMetaData"],
			["set", '$P1.fileName', '$P2.name'],
			["set", '$P1.fileID', '$P2.id'],
			["set", '$P1.size', '$P2.size'],
			["create", '$L0', "Date", '$P2.updated'],
			["set", '$P1.lastModified', '$L0.time']
		],
		'validateResponse' => [
			["if==than", '$P1.code', 404, 2],
			["create", '$L0', "Error", "Not Found", "NotFound"],
			["throwError", '$L0'],
			["if==than", '$P1.code', 409, 2],
			["create", '$L0', "Error", "File or Bucket already exists", "IllegalArgument"],
			["throwError", '$L0'],
			["if>=than", '$P1.code', 400, 4],
			["json.parse", '$L0', '$P1.responseBody'],
			["json.stringify", '$L0', '$L0'],
			["create", '$L1', "Error", '$L0', "HttpException"],
			["throwError", '$L1']
		],
		'checkAuthentication' => [
			["if!=than", '$P0.accessToken', NULL, 3],
			["create", '$L0', "Date"],
			["if<than", '$L0.time', '$P0.expires', 1],
			["return"],
			["callFunc", "createJWT", '$P0', '$L0'],
			["callFunc", "retrieveAccessToken", '$P0', '$L0']
		],
		'createJWT' => [
			["set", '$P1', "eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9"],
			["create", '$L1', "Object"],
			["set", '$L1.iss', '$P0.clientEmail'],
			["set", '$L1.scope', "https://www.googleapis.com/auth/cloud-platform"],
			["set", '$L1.aud', "https://www.googleapis.com/oauth2/v4/token"],
			["create", '$L2', "Date"],
			["math.add", '$L3', '$L2.time', 180000],
			["set", '$L1.exp', '$L3'],
			["math.multiply", '$L1.exp', '$L1.exp', 0.001],
			["set", '$L1.iat', '$L2.time'],
			["math.multiply", '$L1.iat', '$L1.iat', 0.001],
			["json.stringify", '$L0', '$L1'],
			["string.base64encode", '$L0', '$L0', 0, 1],
			["string.concat", '$P1', '$P1', ".", '$L0'],
			["stream.stringToStream", '$L0', '$P1'],
			["stream.streamToData", '$L1', '$L0'],
			["string.base64decode", '$L2', '$P0.privateKey'],
			["crypt.rsa.sha256", '$L2', '$L1', '$L2'],
			["string.base64encode", '$L3', '$L2', 0, 1],
			["string.concat", '$P1', '$P1', ".", '$L3']
		],
		'retrieveAccessToken' => [
			["create", '$L0', "Object"],
			["set", '$L0.method', "POST"],
			["set", '$L0.url', '$P0.authBase'],
			["create", '$L0.requestHeaders', "Object"],
			["set", '$L0.requestHeaders.Content-Type', "application/x-www-form-urlencoded"],
			["string.concat", '$L1', "grant_type=urn%3Aietf%3Aparams%3Aoauth%3Agrant-type%3Ajwt-bearer&assertion=", '$P1'],
			["stream.stringToStream", '$L0.requestBody', '$L1'],
			["http.requestCall", '$L1', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L1'],
			["json.parse", '$L2', '$L1.responseBody'],
			["set", '$P0.accessToken', '$L2.access_token'],
			["create", '$L3', "Date"],
			["math.multiply", '$L4', '$L2.expires_in', 1000],
			["math.add", '$P0.expires', '$L3.time', '$L4', -1000000]
		],
		'checkNull' => [
			["if==than", '$P1', NULL, 2],
			["create", '$L0', "Error", "Parameter should not be null.", "IllegalArgument"],
			["throwError", '$L0']
		],
		'checkBucket' => [
			["callFunc", "checkNull", '$P0', '$P1'],
			["if==than", '$P1.name', NULL, 3],
			["if==than", '$P1.identifier', NULL, 2],
			["create", '$L0', "Error", "Bucket name and identifier should not be null.", "IllegalArgument"],
			["throwError", '$L0']
		],
		'checkSize' => [
			["if<than", '$P1', 0, 2],
			["create", '$L0', "Error", "Size can not be negative.", "IllegalArgument"],
			["throwError", '$L0']
		],
		'replace' => [
			["string.split", '$L0', '$P2', '$P3'],
			["size", '$L1', '$L0'],
			["set", '$L2', 0],
			["if<than", '$L2', '$L1', 7],
			["get", '$L5', '$L0', '$L2'],
			["if==than", '$L2', 0, 2],
			["set", '$L4', '$L5'],
			["jumpRel", 1],
			["string.concat", '$L4', '$L4', '$P4', '$L5'],
			["math.add", '$L2', '$L2', 1],
			["jumpRel", -8],
			["set", '$P1', '$L4']
		],
		'encodeObjectName' => [
			["callFunc", "replace", '$P0', '$L1', '$P2', "’", "\'"],
			["string.urlEncode", '$P1', '$L1']
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
	 * @param string $clientEmail
	 * @param string $privateKey
	 * @param string $projectId
	 */
	public function __construct(string $clientEmail, string $privateKey, string $projectId)
	{
		$this->interpreterStorage = array();
		$this->instanceDependencyStorage = [];
		$this->persistentStorage = array(array());
		InitSelfTest::initTest('GoogleCloudPlatform');
		
		$this->interpreterStorage['clientEmail'] = $clientEmail;
		$this->interpreterStorage['privateKey'] = $privateKey;
		$this->interpreterStorage['projectId'] = $projectId;
		

		$ip = new Interpreter(new Sandbox(GoogleCloudPlatform::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		if (array_key_exists("init",GoogleCloudPlatform::$SERVICE_CODE)) {
			$parameters = [&$this->interpreterStorage];
		  $ip->callFunctionSync("init",$parameters );
		}
	}

	
	/**
	 * @param string $bucketName
	 * @throws IllegalArgumentError
	 * @throws AuthenticationError
	 * @throws NotFoundError
	 * @throws HttpError
	 * @throws ServiceUnavailableError
	 * @throws \Exception
	 * @return Bucket
	 */
	public function createBucket(string $bucketName):Bucket {
		Statistics::addCall("GoogleCloudPlatform", "createBucket");
		$ip = new Interpreter(new Sandbox(GoogleCloudPlatform::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null, $bucketName];
		$ip->callFunctionSync('createBucket', $auxArray);
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
	 * @return array
	 */
	public function listBuckets():array {
		Statistics::addCall("GoogleCloudPlatform", "listBuckets");
		$ip = new Interpreter(new Sandbox(GoogleCloudPlatform::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null];
		$ip->callFunctionSync('listBuckets', $auxArray);
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
	 * @param Bucket $bucket
	 * @throws IllegalArgumentError
	 * @throws AuthenticationError
	 * @throws NotFoundError
	 * @throws HttpError
	 * @throws ServiceUnavailableError
	 * @throws \Exception
	 */
	public function deleteBucket(Bucket $bucket):void {
		Statistics::addCall("GoogleCloudPlatform", "deleteBucket");
		$ip = new Interpreter(new Sandbox(GoogleCloudPlatform::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, $bucket];
		$ip->callFunctionSync('deleteBucket', $auxArray);
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
	 * @param string $fileName
	 * @param Bucket $bucket
	 * @throws IllegalArgumentError
	 * @throws AuthenticationError
	 * @throws NotFoundError
	 * @throws HttpError
	 * @throws ServiceUnavailableError
	 * @throws \Exception
	 */
	public function deleteFile(string $fileName, Bucket $bucket):void {
		Statistics::addCall("GoogleCloudPlatform", "deleteFile");
		$ip = new Interpreter(new Sandbox(GoogleCloudPlatform::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, $fileName, $bucket];
		$ip->callFunctionSync('deleteFile', $auxArray);
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
	 * @param Bucket $bucket
	 * @param string $fileName
	 * @throws IllegalArgumentError
	 * @throws AuthenticationError
	 * @throws NotFoundError
	 * @throws HttpError
	 * @throws ServiceUnavailableError
	 * @throws \Exception
	 * @return BusinessFileMetaData
	 */
	public function getFileMetadata(Bucket $bucket, string $fileName):BusinessFileMetaData {
		Statistics::addCall("GoogleCloudPlatform", "getFileMetadata");
		$ip = new Interpreter(new Sandbox(GoogleCloudPlatform::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null, $bucket, $fileName];
		$ip->callFunctionSync('getFileMetadata', $auxArray);
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
	 * @param Bucket $bucket
	 * @throws IllegalArgumentError
	 * @throws AuthenticationError
	 * @throws NotFoundError
	 * @throws HttpError
	 * @throws ServiceUnavailableError
	 * @throws \Exception
	 * @return array
	 */
	public function listFiles(Bucket $bucket):array {
		Statistics::addCall("GoogleCloudPlatform", "listFiles");
		$ip = new Interpreter(new Sandbox(GoogleCloudPlatform::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null, $bucket];
		$ip->callFunctionSync('listFiles', $auxArray);
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
	 * @param Bucket $bucket
	 * @param string $prefix
	 * @throws IllegalArgumentError
	 * @throws AuthenticationError
	 * @throws NotFoundError
	 * @throws HttpError
	 * @throws ServiceUnavailableError
	 * @throws \Exception
	 * @return array
	 */
	public function listFilesWithPrefix(Bucket $bucket, string $prefix):array {
		Statistics::addCall("GoogleCloudPlatform", "listFilesWithPrefix");
		$ip = new Interpreter(new Sandbox(GoogleCloudPlatform::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null, $bucket, $prefix];
		$ip->callFunctionSync('listFilesWithPrefix', $auxArray);
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
	 * @param Bucket $bucket
	 * @param string $name
	 * @param resource $stream
	 * @param int $size
	 * @throws IllegalArgumentError
	 * @throws AuthenticationError
	 * @throws NotFoundError
	 * @throws HttpError
	 * @throws ServiceUnavailableError
	 * @throws \Exception
	 */
	public function uploadFile(Bucket $bucket, string $name,  $stream, int $size):void {
		Statistics::addCall("GoogleCloudPlatform", "uploadFile");
		$ip = new Interpreter(new Sandbox(GoogleCloudPlatform::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, $bucket, $name, $stream, $size];
		$ip->callFunctionSync('uploadFile', $auxArray);
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
	 * @param string $fileName
	 * @param Bucket $bucket
	 * @throws IllegalArgumentError
	 * @throws AuthenticationError
	 * @throws NotFoundError
	 * @throws HttpError
	 * @throws ServiceUnavailableError
	 * @throws \Exception
	 * @return resource
	 */
	public function downloadFile(string $fileName, Bucket $bucket) {
		Statistics::addCall("GoogleCloudPlatform", "downloadFile");
		$ip = new Interpreter(new Sandbox(GoogleCloudPlatform::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null, $fileName, $bucket];
		$ip->callFunctionSync('downloadFile', $auxArray);
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
		Statistics::addCall("GoogleCloudPlatform", "advancedRequest");
		$ip = new Interpreter(new Sandbox(GoogleCloudPlatform::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		$ip = new Interpreter(new Sandbox(GoogleCloudPlatform::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		return $ip->saveAsString();
	}

	/**
	 * @param string $savedState
	 */
	public function loadAsString($savedState) {
		$sandbox = new Sandbox(GoogleCloudPlatform::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage);
		$ip = new Interpreter($sandbox);
		$ip->loadAsString($savedState);
		$this->persistentStorage = $sandbox->persistentStorage;
	}
}
