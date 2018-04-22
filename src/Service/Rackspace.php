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

class Rackspace implements BusinessCloudStorage, AdvancedRequestSupporter
{
	private static $SERVICE_CODE = [
		'Storage:createBucket' => [
			["callFunc", "checkNull", '$P0', '$P2'],
			["callFunc", "checkAuthentication", '$P0'],
			["create", '$L4', "Object"],
			["set", '$L4', "application/x-www-form-urlencoded", "Content-Type"],
			["set", '$L4', '$S0.authorizationToken', "X-Auth-Token"],
			["create", '$L5', "Object"],
			["string.concat", '$L0', '$S0.publicURL', "/", '$P2'],
			["set", '$L5.url', '$L0'],
			["set", '$L5.method', "PUT"],
			["set", '$L5.requestHeaders', '$L4'],
			["http.requestCall", '$L6', '$L5'],
			["if==than", '$L6.code', 202, 2],
			["create", '$L3', "Error", "Bucket already exists.", "IllegalArgument"],
			["throwError", '$L3'],
			["callFunc", "checkHttpErrors", '$P0', '$L6', "create bucket", 201],
			["create", '$L14', "Bucket"],
			["set", '$L14.name', '$P2'],
			["set", '$L14.identifier', '$P2'],
			["set", '$P1', '$L14']
		],
		'Storage:deleteBucket' => [
			["callFunc", "checkBucket", '$P0', '$P1'],
			["callFunc", "checkAuthentication", '$P0'],
			["create", '$L4', "Object"],
			["set", '$L4', "application/x-www-form-urlencoded", "Content-Type"],
			["set", '$L4', '$S0.authorizationToken', "X-Auth-Token"],
			["create", '$L5', "Object"],
			["string.concat", '$L0', '$S0.publicURL', "/", '$P1.name'],
			["set", '$L5.url', '$L0'],
			["set", '$L5.method', "DELETE"],
			["set", '$L5.requestHeaders', '$L4'],
			["http.requestCall", '$L6', '$L5'],
			["if!=than", '$L6.code', 204, 3],
			["if==than", '$L6.code', 404, 2],
			["create", '$L3', "Error", "Bucket not found.", "NotFound"],
			["throwError", '$L3']
		],
		'Storage:listBuckets' => [
			["callFunc", "checkAuthentication", '$P0'],
			["create", '$L4', "Object"],
			["set", '$L4', "application/x-www-form-urlencoded", "Content-Type"],
			["set", '$L4', '$S0.authorizationToken', "X-Auth-Token"],
			["set", '$L4', "application/json", "Accept"],
			["create", '$L5', "Object"],
			["string.concat", '$L0', '$S0.publicURL', "?format=json"],
			["set", '$L5.url', '$L0'],
			["set", '$L5.method', "GET"],
			["set", '$L5.requestHeaders', '$L4'],
			["http.requestCall", '$L6', '$L5'],
			["callFunc", "checkHttpErrors", '$P0', '$L6', "authentication", 200],
			["create", '$L14', "Array"],
			["stream.streamToString", '$L12', '$L6.responseBody'],
			["json.parse", '$L12', '$L12'],
			["size", '$L10', '$L12'],
			["if!=than", '$L10', 0, 7],
			["math.add", '$L10', '$L10', -1],
			["create", '$L13', "Bucket"],
			["get", '$L15', '$L12', '$L10'],
			["set", '$L13.identifier', '$L15.name'],
			["set", '$L13.name', '$L15.name'],
			["push", '$L14', '$L13'],
			["jumpRel", -8],
			["set", '$P1', '$L14']
		],
		'Storage:upload' => [
			["callFunc", "checkBucket", '$P0', '$P1'],
			["callFunc", "checkNull", '$P0', '$P2'],
			["callFunc", "checkNull", '$P0', '$P3'],
			["callFunc", "checkSize", '$P0', '$P4'],
			["callFunc", "checkAuthentication", '$P0'],
			["create", '$L4', "Object"],
			["set", '$L4', "application/octet-stream", "Content-Type"],
			["set", '$L4', '$S0.authorizationToken', "X-Auth-Token"],
			["string.concat", '$L1', '$S0.publicURL', "/", '$P1.name', "/", '$P2'],
			["string.concat", '$L4.Content-Length', '$P4'],
			["create", '$L5', "Object"],
			["set", '$L5.url', '$L1'],
			["set", '$L5.method', "PUT"],
			["set", '$L5.requestHeaders', '$L4'],
			["set", '$L5.requestBody', '$P3'],
			["http.requestCall", '$L6', '$L5'],
			["if!=than", '$L6.code', 201, 4],
			["if!=than", '$L6.code', 202, 3],
			["if==than", '$L6.code', 401, 2],
			["create", '$L3', "Error", "Error while uploading the file", "Upload Error"],
			["throwError", '$L3']
		],
		'Storage:download' => [
			["callFunc", "checkNull", '$P0', '$P2'],
			["callFunc", "checkBucket", '$P0', '$P3'],
			["callFunc", "checkAuthentication", '$P0'],
			["create", '$L4', "Object"],
			["set", '$L4', "application/x-www-form-urlencoded", "Content-Type"],
			["set", '$L4', '$S0.authorizationToken', "X-Auth-Token"],
			["string.concat", '$L1', '$S0.publicURL', "/", '$P3.name', "/", '$P2'],
			["create", '$L5', "Object"],
			["set", '$L5.url', '$L1'],
			["set", '$L5.method', "GET"],
			["set", '$L5.requestHeaders', '$L4'],
			["http.requestCall", '$L6', '$L5'],
			["callFunc", "checkHttpErrors", '$P0', '$L6', "authentication", 200],
			["set", '$P1', '$L6.responseBody']
		],
		'Storage:listFiles' => [
			["callFunc", "checkBucket", '$P0', '$P2'],
			["callFunc", "checkAuthentication", '$P0'],
			["create", '$L4', "Object"],
			["set", '$L4', "application/x-www-form-urlencoded", "Content-Type"],
			["set", '$L4', '$S0.authorizationToken', "X-Auth-Token"],
			["create", '$L5', "Object"],
			["string.concat", '$L10', '$S0.publicURL', "/", '$P2.name', "?format=json"],
			["set", '$L5.url', '$L10'],
			["set", '$L5.method', "GET"],
			["set", '$L5.requestHeaders', '$L4'],
			["http.requestCall", '$L6', '$L5'],
			["callFunc", "checkHttpErrors", '$P0', '$L6', "authentication", 200],
			["create", '$L14', "Array"],
			["stream.streamToString", '$L12', '$L6.responseBody'],
			["json.parse", '$L12', '$L12'],
			["size", '$L10', '$L12'],
			["if!=than", '$L10', 0, 8],
			["math.add", '$L10', '$L10', -1],
			["create", '$L13', "BusinessFileMetaData"],
			["get", '$L15', '$L12', '$L10'],
			["set", '$L13.fileID', '$L15.hash'],
			["set", '$L13.fileName', '$L15.name'],
			["set", '$L13.size', '$L15.bytes'],
			["push", '$L14', '$L13'],
			["jumpRel", -9],
			["set", '$P1', '$L14']
		],
		'Storage:listFilesWithPrefix' => [
			["callFunc", "checkBucket", '$P0', '$P2'],
			["callFunc", "checkPrefix", '$P0', '$P3'],
			["callFunc", "checkAuthentication", '$P0'],
			["create", '$L4', "Object"],
			["set", '$L4', "application/x-www-form-urlencoded", "Content-Type"],
			["set", '$L4', '$S0.authorizationToken', "X-Auth-Token"],
			["create", '$L5', "Object"],
			["string.concat", '$L10', '$S0.publicURL', "/", '$P2.name', "?format=json"],
			["if!=than", '$P3', NULL, 2],
			["string.concat", '$L2', '$L10', "&prefix=", '$P3'],
			["set", '$L10', '$L2'],
			["set", '$L5.url', '$L10'],
			["set", '$L5.method', "GET"],
			["set", '$L5.requestHeaders', '$L4'],
			["http.requestCall", '$L6', '$L5'],
			["callFunc", "checkHttpErrors", '$P0', '$L6', "authentication", 200],
			["create", '$L14', "Array"],
			["stream.streamToString", '$L12', '$L6.responseBody'],
			["json.parse", '$L12', '$L12'],
			["size", '$L10', '$L12'],
			["if!=than", '$L10', 0, 8],
			["math.add", '$L10', '$L10', -1],
			["create", '$L13', "BusinessFileMetaData"],
			["get", '$L15', '$L12', '$L10'],
			["set", '$L13.fileID', '$L15.hash'],
			["set", '$L13.fileName', '$L15.name'],
			["set", '$L13.size', '$L15.bytes'],
			["push", '$L14', '$L13'],
			["jumpRel", -9],
			["set", '$P1', '$L14']
		],
		'checkPrefix' => [
			["if==than", '$P1', NULL, 2],
			["create", '$L1', "Error", "Prefix supplied is null", "IllegalArgument"],
			["throwError", '$L1']
		],
		'Storage:getFileMetadata' => [
			["callFunc", "checkBucket", '$P0', '$P2'],
			["callFunc", "checkNull", '$P0', '$P3'],
			["callFunc", "checkAuthentication", '$P0'],
			["create", '$L0', "Object"],
			["string.concat", '$L0.url', '$S0.publicURL', "/", '$P2.name', "/", '$P3'],
			["set", '$L0.method', "HEAD"],
			["create", '$L1', "Object"],
			["set", '$L0.requestHeaders', '$L1'],
			["set", '$L1.X-Auth-Token', '$S0.authorizationToken'],
			["create", '$L2', "Object"],
			["http.requestCall", '$L2', '$L0'],
			["callFunc", "checkHttpErrors", '$P0', '$L2', "get metadata", 200],
			["get", '$L3', '$L2.responseHeaders'],
			["create", '$P1', "BusinessFileMetaData"],
			["set", '$P1.fileName', '$P3'],
			["set", '$P1.fileID', '$P3'],
			["set", '$P1.size', '$L3.Content-Length'],
			["callFunc", "parseDate", '$P0', '$L4', '$L3.Last-Modified'],
			["set", '$P1.lastModified', '$L4']
		],
		'Storage:deleteFile' => [
			["callFunc", "checkBucket", '$P0', '$P2'],
			["callFunc", "checkNull", '$P0', '$P1'],
			["callFunc", "checkAuthentication", '$P0'],
			["create", '$L4', "Object"],
			["set", '$L4', "application/x-www-form-urlencoded", "Content-Type"],
			["set", '$L4', '$S0.authorizationToken', "X-Auth-Token"],
			["create", '$L5', "Object"],
			["string.concat", '$L0', '$S0.publicURL', "/", '$P2.name', "/", '$P1'],
			["set", '$L5.url', '$L0'],
			["set", '$L5.method', "DELETE"],
			["set", '$L5.requestHeaders', '$L4'],
			["http.requestCall", '$L6', '$L5'],
			["if!=than", '$L6.code', 204, 3],
			["if==than", '$L6.code', 404, 2],
			["create", '$L3', "Error", "File not found.", "NotFound"],
			["throwError", '$L3']
		],
		'AdvancedRequestSupporter:advancedRequest' => [
			["create", '$L0', "Object"],
			["if!=than", '$P2.appendBaseUrl', 0, 3],
			["callFunc", "checkAuthentication", '$P0'],
			["string.concat", '$L0.url', '$S0.publicURL', '$P2.url'],
			["jumpRel", 1],
			["set", '$L0.url', '$P2.url'],
			["set", '$L0.requestHeaders', '$P2.headers'],
			["set", '$L0.method', '$P2.method'],
			["set", '$L0.requestBody', '$P2.body'],
			["if==than", '$L0.requestHeaders', NULL, 1],
			["create", '$L0.requestHeaders', "Object"],
			["if!=than", '$P2.appendAuthorization', 0, 2],
			["callFunc", "checkAuthentication", '$P0'],
			["set", '$L0.requestHeaders.X-Auth-Token', '$S0.authorizationToken'],
			["http.requestCall", '$L1', '$L0'],
			["if!=than", '$P2.checkErrors', 0, 1],
			["callFunc", "checkHttpErrors", '$P0', '$L1', "advancedRequest"],
			["create", '$P1', "AdvancedRequestResponse"],
			["set", '$P1.status', '$L1.code'],
			["set", '$P1.headers', '$L1.responseHeaders'],
			["set", '$P1.body', '$L1.responseBody']
		],
		'checkAuthentication' => [
			["if==than", '$S0.tokenID', NULL, 2],
			["callFunc", "authenticate", '$P0'],
			["return"]
		],
		'authenticate' => [
			["create", '$L2', "String"],
			["create", '$L4', "Object"],
			["set", '$L4', "application/json", "Content-Type"],
			["string.concat", '$L9', "{\"auth\": { \"RAX-KSKEY:apiKeyCredentials\": {\"username\": \"", '$P0.username', "\", \"apiKey\": \"", '$P0.apiKey', "\"}}}"],
			["size", '$L10', '$L9'],
			["string.concat", '$L4.Content-Length', '$L10'],
			["create", '$L5', "Object"],
			["set", '$L5.url', "https://identity.api.rackspacecloud.com/v2.0/tokens"],
			["set", '$L5.method', "POST"],
			["set", '$L5.requestHeaders', '$L4'],
			["stream.stringToStream", '$L9', '$L9'],
			["set", '$L5.requestBody', '$L9'],
			["http.requestCall", '$L6', '$L5'],
			["callFunc", "checkHttpErrors", '$P0', '$L6', "authentication", 200],
			["stream.streamToString", '$L7', '$L6.responseBody'],
			["json.parse", '$L8', '$L7'],
			["set", '$L8', '$L8.access'],
			["set", '$L9', '$L8.token'],
			["set", '$S0.authorizationToken', '$L9.id'],
			["set", '$S0.expires', '$L9.expires'],
			["set", '$L8', '$L8.serviceCatalog'],
			["size", '$L10', '$L8'],
			["if!=than", '$L10', 0, 13],
			["math.add", '$L10', '$L10', -1],
			["get", '$L11', '$L8', '$L10'],
			["if==than", '$L11.name', "cloudFiles", 9],
			["size", '$L12', '$L11.endpoints'],
			["if!=than", '$L12', 0, 7],
			["math.add", '$L12', '$L12', -1],
			["get", '$L13', '$L11.endpoints', '$L12'],
			["if==than", '$L13.region', '$P0.region', 3],
			["set", '$S0.tenantId', '$L13.tenantId'],
			["set", '$S0.publicURL', '$L13.publicURL'],
			["set", '$S0.internalURL', '$L13.internalURL'],
			["jumpRel", -8],
			["jumpRel", -14]
		],
		'checkHttpErrors' => [
			["if==than", '$P3', NULL, 2],
			["if>=than", '$P1.code', 400, 22],
			["jumpRel", 1],
			["if!=than", '$P1.code', '$P3', 20],
			["if==than", '$P1.code', 404, 2],
			["create", '$L3', "Error", "Requested object wasn't found.", "NotFound"],
			["throwError", '$L3'],
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
			["throwError", '$L3']
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
		'parseDate' => [
			["string.substr", '$L0', '$P2', 5, 2],
			["string.substr", '$L1', '$P2', 8, 3],
			["callFunc", "getMonthNumber", '$P0', '$L6', '$L1'],
			["string.substr", '$L2', '$P2', 12, 4],
			["string.substr", '$L3', '$P2', 17, 8],
			["string.concat", '$L4', '$L2', "-", '$L6', "-", '$L0', "T", '$L3', "Z"],
			["create", '$L5', "Date", '$L4'],
			["set", '$P1', '$L5.time']
		],
		'getMonthNumber' => [
			["if==than", '$P2', "Jan", 2],
			["set", '$P1', "01"],
			["return"],
			["if==than", '$P2', "Feb", 2],
			["set", '$P1', "02"],
			["return"],
			["if==than", '$P2', "Mar", 2],
			["set", '$P1', "03"],
			["return"],
			["if==than", '$P2', "Apr", 2],
			["set", '$P1', "04"],
			["return"],
			["if==than", '$P2', "May", 2],
			["set", '$P1', "05"],
			["return"],
			["if==than", '$P2', "Jun", 2],
			["set", '$P1', "06"],
			["return"],
			["if==than", '$P2', "Jul", 2],
			["set", '$P1', "07"],
			["return"],
			["if==than", '$P2', "Aug", 2],
			["set", '$P1', "08"],
			["return"],
			["if==than", '$P2', "Sep", 2],
			["set", '$P1', "09"],
			["return"],
			["if==than", '$P2', "Oct", 2],
			["set", '$P1', "10"],
			["return"],
			["if==than", '$P2', "Nov", 2],
			["set", '$P1', "11"],
			["return"],
			["if==than", '$P2', "Dec", 2],
			["set", '$P1', "12"],
			["return"],
			["create", '$L0', "Error", "Could not recognize month in Date"],
			["throwError", '$L0']
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
	 * @param string $username
	 * @param string $apiKey
	 * @param string $region
	 */
	public function __construct(string $username, string $apiKey, string $region)
	{
		$this->interpreterStorage = array();
		$this->instanceDependencyStorage = [];
		$this->persistentStorage = array(array());
		InitSelfTest::initTest('Rackspace');
		
		$this->interpreterStorage['username'] = $username;
		$this->interpreterStorage['apiKey'] = $apiKey;
		$this->interpreterStorage['region'] = $region;
		

		$ip = new Interpreter(new Sandbox(Rackspace::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		if (array_key_exists("init",Rackspace::$SERVICE_CODE)) {
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
		Statistics::addCall("Rackspace", "createBucket");
		$ip = new Interpreter(new Sandbox(Rackspace::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null, $bucketName];
		$ip->callFunctionSync('Storage:createBucket', $auxArray);
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
		Statistics::addCall("Rackspace", "listBuckets");
		$ip = new Interpreter(new Sandbox(Rackspace::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null];
		$ip->callFunctionSync('Storage:listBuckets', $auxArray);
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
		Statistics::addCall("Rackspace", "deleteBucket");
		$ip = new Interpreter(new Sandbox(Rackspace::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, $bucket];
		$ip->callFunctionSync('Storage:deleteBucket', $auxArray);
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
		Statistics::addCall("Rackspace", "deleteFile");
		$ip = new Interpreter(new Sandbox(Rackspace::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, $fileName, $bucket];
		$ip->callFunctionSync('Storage:deleteFile', $auxArray);
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
		Statistics::addCall("Rackspace", "getFileMetadata");
		$ip = new Interpreter(new Sandbox(Rackspace::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null, $bucket, $fileName];
		$ip->callFunctionSync('Storage:getFileMetadata', $auxArray);
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
		Statistics::addCall("Rackspace", "listFiles");
		$ip = new Interpreter(new Sandbox(Rackspace::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null, $bucket];
		$ip->callFunctionSync('Storage:listFiles', $auxArray);
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
		Statistics::addCall("Rackspace", "listFilesWithPrefix");
		$ip = new Interpreter(new Sandbox(Rackspace::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null, $bucket, $prefix];
		$ip->callFunctionSync('Storage:listFilesWithPrefix', $auxArray);
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
		Statistics::addCall("Rackspace", "uploadFile");
		$ip = new Interpreter(new Sandbox(Rackspace::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, $bucket, $name, $stream, $size];
		$ip->callFunctionSync('Storage:upload', $auxArray);
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
		Statistics::addCall("Rackspace", "downloadFile");
		$ip = new Interpreter(new Sandbox(Rackspace::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null, $fileName, $bucket];
		$ip->callFunctionSync('Storage:download', $auxArray);
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
		Statistics::addCall("Rackspace", "advancedRequest");
		$ip = new Interpreter(new Sandbox(Rackspace::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		$ip = new Interpreter(new Sandbox(Rackspace::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		return $ip->saveAsString();
	}

	/**
	 * @param string $savedState
	 */
	public function loadAsString($savedState) {
		$sandbox = new Sandbox(Rackspace::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage);
		$ip = new Interpreter($sandbox);
		$ip->loadAsString($savedState);
		$this->persistentStorage = $sandbox->persistentStorage;
	}
}
