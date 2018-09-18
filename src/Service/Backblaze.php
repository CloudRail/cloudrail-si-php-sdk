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

class Backblaze implements BusinessCloudStorage, AdvancedRequestSupporter
{
	private static $SERVICE_CODE = [
		'Storage:createBucket' => [
			["callFunc", "checkNull", '$P0', '$P2'],
			["callFunc", "checkAuthentication", '$P0'],
			["create", '$L4', "Object"],
			["set", '$L4', "application/json", "Content-Type"],
			["set", '$L4', '$S0.authorizationToken', "Authorization"],
			["string.concat", '$L9', "{\"accountId\":\"", '$P0.accountID', "\",\"bucketName\":\"", '$P2', "\",\"bucketType\":\"allPrivate\"}"],
			["size", '$L10', '$L9'],
			["string.concat", '$L4.Content-Length', '$L10'],
			["create", '$L5', "Object"],
			["string.concat", '$L0', '$S0.apiUrl', "/b2api/v1/b2_create_bucket"],
			["set", '$L5.url', '$L0'],
			["set", '$L5.method', "POST"],
			["set", '$L5.requestHeaders', '$L4'],
			["stream.stringToStream", '$L9', '$L9'],
			["set", '$L5.requestBody', '$L9'],
			["http.requestCall", '$L6', '$L5'],
			["callFunc", "checkHttpErrors", '$P0', '$L6', "authentication", 200],
			["stream.streamToString", '$L12', '$L6.responseBody'],
			["json.parse", '$L12', '$L12'],
			["create", '$L5', "Bucket"],
			["set", '$L5.name', '$P2'],
			["set", '$L5.identifier', '$L12.bucketId'],
			["set", '$P1', '$L5']
		],
		'Storage:deleteBucket' => [
			["callFunc", "checkBucket", '$P0', '$P1'],
			["callFunc", "checkAuthentication", '$P0'],
			["create", '$L4', "Object"],
			["set", '$L4', "application/json", "Content-Type"],
			["set", '$L4', '$S0.authorizationToken', "Authorization"],
			["string.concat", '$L9', "{\"accountId\":\"", '$P0.accountID', "\",\"bucketId\":\"", '$P1.identifier', "\"}"],
			["size", '$L10', '$L9'],
			["string.concat", '$L4.Content-Length', '$L10'],
			["create", '$L5', "Object"],
			["string.concat", '$L0', '$S0.apiUrl', "/b2api/v1/b2_delete_bucket"],
			["set", '$L5.url', '$L0'],
			["set", '$L5.method', "POST"],
			["set", '$L5.requestHeaders', '$L4'],
			["stream.stringToStream", '$L9', '$L9'],
			["set", '$L5.requestBody', '$L9'],
			["http.requestCall", '$L6', '$L5'],
			["callFunc", "checkHttpErrors", '$P0', '$L6', "authentication", 200]
		],
		'Storage:listBuckets' => [
			["callFunc", "checkAuthentication", '$P0'],
			["create", '$L4', "Object"],
			["set", '$L4', "application/x-www-form-urlencoded", "Content-Type"],
			["set", '$L4', '$S0.authorizationToken', "Authorization"],
			["string.concat", '$L9', "{\"accountId\":\"", '$P0.accountID', "\"}"],
			["size", '$L10', '$L9'],
			["string.concat", '$L4.Content-Length', '$L10'],
			["create", '$L5', "Object"],
			["string.concat", '$L0', '$S0.apiUrl', "/b2api/v1/b2_list_buckets"],
			["set", '$L5.url', '$L0'],
			["set", '$L5.method', "POST"],
			["set", '$L5.requestHeaders', '$L4'],
			["stream.stringToStream", '$L9', '$L9'],
			["set", '$L5.requestBody', '$L9'],
			["http.requestCall", '$L6', '$L5'],
			["callFunc", "checkHttpErrors", '$P0', '$L6', "authentication", 200],
			["stream.streamToString", '$L12', '$L6.responseBody'],
			["json.parse", '$L12', '$L12'],
			["size", '$L10', '$L12.buckets'],
			["create", '$L14', "Array"],
			["if!=than", '$L10', 0, 7],
			["math.add", '$L10', '$L10', -1],
			["create", '$L13', "Bucket"],
			["get", '$L15', '$L12.buckets', '$L10'],
			["set", '$L13.identifier', '$L15.bucketId'],
			["set", '$L13.name', '$L15.bucketName'],
			["push", '$L14', '$L13'],
			["jumpRel", -8],
			["set", '$P1', '$L14']
		],
		'Storage:getFileMetadata' => [
			["callFunc", "checkBucket", '$P0', '$P2'],
			["callFunc", "checkNull", '$P0', '$P3'],
			["callFunc", "checkAuthentication", '$P0'],
			["create", '$L0', "Object"],
			["string.concat", '$L0.url', '$S0.apiUrl', "/b2api/v1/b2_list_file_names"],
			["set", '$L0.method', "POST"],
			["create", '$L1', "Object"],
			["set", '$L0.requestHeaders', '$L1'],
			["set", '$L1.Authorization', '$S0.authorizationToken'],
			["create", '$L2', "Object"],
			["set", '$L2.bucketId', '$P2.identifier'],
			["set", '$L2.startFileName', '$P3'],
			["set", '$L2.maxFileCount', 1],
			["json.stringify", '$L3', '$L2'],
			["size", '$L4', '$L3'],
			["string.concat", '$L4', '$L4'],
			["set", '$L1.Content-Length', '$L4'],
			["stream.stringToStream", '$L3', '$L3'],
			["set", '$L0.requestBody', '$L3'],
			["create", '$L4', "Object"],
			["http.requestCall", '$L4', '$L0'],
			["callFunc", "checkHttpErrors", '$P0', '$L4', "list file names", 200],
			["json.parse", '$L5', '$L4.responseBody'],
			["size", '$L6', '$L5.files'],
			["if==than", '$L6', 0, 2],
			["create", '$L6', "Error", "File not found!", "NotFound"],
			["throwError", '$L6'],
			["get", '$L6', '$L5.files', 0],
			["if!=than", '$P3', '$L6.fileName', 2],
			["create", '$L7', "Error", "File not found!", "NotFound"],
			["throwError", '$L7'],
			["create", '$P1', "BusinessFileMetaData"],
			["set", '$P1.fileName', '$L6.fileName'],
			["set", '$P1.fileID', '$L6.fileId'],
			["set", '$P1.size', '$L6.contentLength'],
			["set", '$P1.lastModified', '$L6.uploadTimestamp']
		],
		'Storage:upload' => [
			["callFunc", "checkBucket", '$P0', '$P1'],
			["callFunc", "checkNull", '$P0', '$P2'],
			["callFunc", "checkNull", '$P0', '$P3'],
			["callFunc", "checkSize", '$P0', '$P4'],
			["callFunc", "checkAuthentication", '$P0'],
			["callFunc", "getUploadURL", '$P0', '$P1'],
			["create", '$L4', "Object"],
			["set", '$L4', "application/octet-stream", "Content-Type"],
			["set", '$L4', '$S0.uploadAuthorizationToken', "Authorization"],
			["set", '$L4', '$P2', "X-Bz-File-Name"],
			["stream.streamToData", '$L3', '$P3'],
			["hash.sha1", '$L2', '$L3'],
			["callFunc", "arrayToHex", '$P0', '$L20', '$L2'],
			["set", '$L4', '$L20', "X-Bz-Content-Sha1"],
			["string.concat", '$L4.Content-Length', '$P4'],
			["create", '$L5', "Object"],
			["set", '$L5.url', '$S0.uploadURL'],
			["set", '$L5.method', "POST"],
			["set", '$L5.requestHeaders', '$L4'],
			["stream.dataToStream", '$L3', '$L3'],
			["set", '$L5.requestBody', '$L3'],
			["http.requestCall", '$L6', '$L5'],
			["callFunc", "checkHttpErrors", '$P0', '$L6', "authentication", 200],
			["stream.streamToString", '$L12', '$L6.responseBody'],
			["json.parse", '$L12', '$L12'],
			["set", '$P1', '$L12.bucketId']
		],
		'Storage:download' => [
			["callFunc", "checkNull", '$P0', '$P2'],
			["callFunc", "checkBucket", '$P0', '$P3'],
			["callFunc", "checkAuthentication", '$P0'],
			["create", '$L4', "Object"],
			["set", '$L4', "application/x-www-form-urlencoded", "Content-Type"],
			["set", '$L4', '$S0.authorizationToken', "Authorization"],
			["string.concat", '$L1', '$S0.downloadUrl', "/file/", '$P3.name', "/", '$P2'],
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
			["set", '$L4', '$S0.authorizationToken', "Authorization"],
			["string.concat", '$L9', "{\"bucketId\":\"", '$P2.identifier', "\"", ",", "\"maxFileCount\":", 1000, "}"],
			["size", '$L10', '$L9'],
			["string.concat", '$L4.Content-Length', '$L10'],
			["create", '$L5', "Object"],
			["string.concat", '$L0', '$S0.apiUrl', "/b2api/v1/b2_list_file_names"],
			["set", '$L5.url', '$L0'],
			["set", '$L5.method', "POST"],
			["set", '$L5.requestHeaders', '$L4'],
			["stream.stringToStream", '$L9', '$L9'],
			["set", '$L5.requestBody', '$L9'],
			["http.requestCall", '$L6', '$L5'],
			["callFunc", "checkHttpErrors", '$P0', '$L6', "authentication", 200],
			["create", '$L14', "Array"],
			["stream.streamToString", '$L12', '$L6.responseBody'],
			["json.parse", '$L12', '$L12'],
			["size", '$L10', '$L12.files'],
			["if!=than", '$L10', 0, 9],
			["math.add", '$L10', '$L10', -1],
			["create", '$L13', "BusinessFileMetaData"],
			["get", '$L15', '$L12.files', '$L10'],
			["set", '$L13.fileID', '$L15.fileId'],
			["set", '$L13.fileName', '$L15.fileName'],
			["set", '$L13.lastModified', '$L15.uploadTimestamp'],
			["set", '$L13.size', '$L15.contentLength'],
			["push", '$L14', '$L13'],
			["jumpRel", -10],
			["if!=than", '$L12.nextFileName', NULL, 9],
			["string.concat", '$L9', "{\"bucketId\":\"", '$P2.identifier', "\"", ",", "\"maxFileCount\":", 1000, ",", "\"startFileName\":", "\"", '$L12.nextFileName', "\"", "}"],
			["size", '$L10', '$L9'],
			["string.concat", '$L4.Content-Length', '$L10'],
			["set", '$L5.requestHeaders', '$L4'],
			["stream.stringToStream", '$L9', '$L9'],
			["set", '$L5.requestBody', '$L9'],
			["http.requestCall", '$L6', '$L5'],
			["callFunc", "checkHttpErrors", '$P0', '$L6', "authentication", 200],
			["jumpRel", -23],
			["set", '$P1', '$L14']
		],
		'Storage:listFilesWithPrefix' => [
			["callFunc", "checkBucket", '$P0', '$P2'],
			["callFunc", "checkPrefix", '$P0', '$P3'],
			["callFunc", "checkAuthentication", '$P0'],
			["create", '$L4', "Object"],
			["set", '$L4', "application/x-www-form-urlencoded", "Content-Type"],
			["set", '$L4', '$S0.authorizationToken', "Authorization"],
			["string.concat", '$L9', "{\"bucketId\":\"", '$P2.identifier', "\"", ",", "\"maxFileCount\":", 1000, ",", "\"prefix\":\"", '$P3', "\"", "}"],
			["size", '$L10', '$L9'],
			["string.concat", '$L4.Content-Length', '$L10'],
			["create", '$L5', "Object"],
			["string.concat", '$L0', '$S0.apiUrl', "/b2api/v1/b2_list_file_names"],
			["set", '$L5.url', '$L0'],
			["set", '$L5.method', "POST"],
			["set", '$L5.requestHeaders', '$L4'],
			["stream.stringToStream", '$L9', '$L9'],
			["set", '$L5.requestBody', '$L9'],
			["http.requestCall", '$L6', '$L5'],
			["callFunc", "checkHttpErrors", '$P0', '$L6', "authentication", 200],
			["create", '$L14', "Array"],
			["stream.streamToString", '$L12', '$L6.responseBody'],
			["json.parse", '$L12', '$L12'],
			["size", '$L10', '$L12.files'],
			["if!=than", '$L10', 0, 9],
			["math.add", '$L10', '$L10', -1],
			["create", '$L13', "BusinessFileMetaData"],
			["get", '$L15', '$L12.files', '$L10'],
			["set", '$L13.fileID', '$L15.fileId'],
			["set", '$L13.fileName', '$L15.fileName'],
			["set", '$L13.lastModified', '$L15.uploadTimestamp'],
			["set", '$L13.size', '$L15.contentLength'],
			["push", '$L14', '$L13'],
			["jumpRel", -10],
			["if!=than", '$L12.nextFileName', NULL, 9],
			["string.concat", '$L9', "{\"bucketId\":\"", '$P2.identifier', "\"", ",", "\"maxFileCount\":", 1000, ",", "\"startFileName\":", "\"", '$L12.nextFileName', "\"", "}"],
			["size", '$L10', '$L9'],
			["string.concat", '$L4.Content-Length', '$L10'],
			["set", '$L5.requestHeaders', '$L4'],
			["stream.stringToStream", '$L9', '$L9'],
			["set", '$L5.requestBody', '$L9'],
			["http.requestCall", '$L6', '$L5'],
			["callFunc", "checkHttpErrors", '$P0', '$L6', "authentication", 200],
			["jumpRel", -23],
			["set", '$P1', '$L14']
		],
		'checkPrefix' => [
			["if==than", '$P1', NULL, 2],
			["create", '$L1', "Error", "Prefix supplied is null", "IllegalArgument"],
			["throwError", '$L1']
		],
		'Storage:deleteFile' => [
			["callFunc", "Storage:getFileMetadata", '$P0', '$L0', '$P2', '$P1'],
			["get", '$L15', '$L0.fileID'],
			["create", '$L4', "Object"],
			["set", '$L4', "application/x-www-form-urlencoded", "Content-Type"],
			["set", '$L4', '$S0.authorizationToken', "Authorization"],
			["string.concat", '$L9', "{\"fileName\":\"", '$P1', "\",\"fileId\":\"", '$L15', "\"}"],
			["size", '$L10', '$L9'],
			["string.concat", '$L4.Content-Length', '$L10'],
			["create", '$L5', "Object"],
			["string.concat", '$L0', '$S0.apiUrl', "/b2api/v1/b2_delete_file_version"],
			["set", '$L5.url', '$L0'],
			["set", '$L5.method', "POST"],
			["set", '$L5.requestHeaders', '$L4'],
			["stream.stringToStream", '$L9', '$L9'],
			["set", '$L5.requestBody', '$L9'],
			["http.requestCall", '$L6', '$L5'],
			["callFunc", "checkHttpErrors", '$P0', '$L6', "authentication", 200]
		],
		'AdvancedRequestSupporter:advancedRequest' => [
			["create", '$L0', "Object"],
			["if!=than", '$P2.appendBaseUrl', 0, 3],
			["callFunc", "checkAuthentication", '$P0'],
			["string.concat", '$L0.url', '$S0.apiUrl', '$P2.url'],
			["jumpRel", 1],
			["set", '$L0.url', '$P2.url'],
			["set", '$L0.requestHeaders', '$P2.headers'],
			["set", '$L0.method', '$P2.method'],
			["set", '$L0.requestBody', '$P2.body'],
			["if==than", '$L0.requestHeaders', NULL, 1],
			["create", '$L0.requestHeaders', "Object"],
			["if!=than", '$P2.appendAuthorization', 0, 2],
			["callFunc", "checkAuthentication", '$P0'],
			["set", '$L0.requestHeaders.Authorization', '$S0.authorizationToken'],
			["if!=than", '$P2.body', NULL, 6],
			["if==than", '$L0.requestHeaders.Content-Length', NULL, 5],
			["jumpRel", 1],
			["if==than", '$L0.requestHeaders.content-length', NULL, 3],
			["stream.streamToString", '$L1', '$L0.requestBody'],
			["size", '$L2', '$L1'],
			["string.concat", '$L0.requestHeaders.Content-Length', '$L2', ""],
			["stream.stringToStream", '$L0.requestBody', '$L1'],
			["http.requestCall", '$L1', '$L0'],
			["if!=than", '$P2.checkErrors', 0, 1],
			["callFunc", "checkHttpErrors", '$P0', '$L1', "advancedRequest"],
			["create", '$P1', "AdvancedRequestResponse"],
			["set", '$P1.status', '$L1.code'],
			["set", '$P1.headers', '$L1.responseHeaders'],
			["set", '$P1.body', '$L1.responseBody']
		],
		'getUploadURL' => [
			["create", '$L4', "Object"],
			["set", '$L4', "application/x-www-form-urlencoded", "Content-Type"],
			["set", '$L4', '$S0.authorizationToken', "Authorization"],
			["string.concat", '$L9', "{\"bucketId\":\"", '$P1.identifier', "\"}"],
			["size", '$L10', '$L9'],
			["string.concat", '$L4.Content-Length', '$L10'],
			["create", '$L5', "Object"],
			["string.concat", '$L0', '$S0.apiUrl', "/b2api/v1/b2_get_upload_url"],
			["set", '$L5.url', '$L0'],
			["set", '$L5.method', "POST"],
			["set", '$L5.requestHeaders', '$L4'],
			["stream.stringToStream", '$L9', '$L9'],
			["set", '$L5.requestBody', '$L9'],
			["http.requestCall", '$L6', '$L5'],
			["callFunc", "checkHttpErrors", '$P0', '$L6', "authentication", 200],
			["stream.streamToString", '$L12', '$L6.responseBody'],
			["json.parse", '$L12', '$L12'],
			["set", '$S0.uploadURL', '$L12.uploadUrl'],
			["set", '$S0.uploadAuthorizationToken', '$L12.authorizationToken']
		],
		'checkAuthentication' => [
			["if==than", '$S0.authorizationToken', NULL, 2],
			["callFunc", "authenticate", '$P0'],
			["return"]
		],
		'authenticate' => [
			["create", '$L2', "String"],
			["create", '$L4', "Object"],
			["set", '$L4', "application/x-www-form-urlencoded", "Content-Type"],
			["string.concat", '$L0', '$P0.accountID', ":", '$P0.appKey'],
			["string.base64encode", '$L0', '$L0'],
			["string.concat", '$L0', "Basic ", '$L0'],
			["set", '$L4', '$L0', "Authorization"],
			["create", '$L5', "Object"],
			["set", '$L5.url', "https://api.backblazeb2.com/b2api/v1/b2_authorize_account"],
			["set", '$L5.method', "GET"],
			["set", '$L5.requestHeaders', '$L4'],
			["http.requestCall", '$L6', '$L5'],
			["callFunc", "checkHttpErrors", '$P0', '$L6', "authentication", 200],
			["stream.streamToString", '$L7', '$L6.responseBody'],
			["json.parse", '$L8', '$L7'],
			["set", '$S0.authorizationToken', '$L8.authorizationToken'],
			["set", '$S0.accountId', '$L8.accountId'],
			["set", '$S0.apiUrl', '$L8.apiUrl'],
			["set", '$S0.downloadUrl', '$L8.downloadUrl'],
			["set", '$S0.minimumPartSize', '$L8.minimumPartSize']
		],
		'checkHttpErrors' => [
			["if==than", '$P3', NULL, 2],
			["if>=than", '$P1.code', 400, 24],
			["jumpRel", 1],
			["if!=than", '$P1.code', '$P3', 22],
			["json.parse", '$L0', '$P1.responseBody'],
			["set", '$L2', '$L0.message'],
			["if==than", '$P1.code', 401, 2],
			["create", '$L3', "Error", '$L2', "Authentication"],
			["throwError", '$L3'],
			["if==than", '$P1.code', 503, 2],
			["create", '$L3', "Error", '$L2', "ServiceUnavailable"],
			["throwError", '$L3'],
			["if==than", '$P1.code', 404, 2],
			["create", '$L3', "Error", '$L2', "NotFound"],
			["throwError", '$L3'],
			["if>=than", '$P1.code', 400, 10],
			["string.indexOf", '$L4', '$L2', "Invalid bucketId"],
			["if!=than", '$L4', -1, 2],
			["create", '$L3', "Error", "The requested bucket could not be found.", "NotFound"],
			["throwError", '$L3'],
			["string.indexOf", '$L4', '$L2', "Bucket name is already in use"],
			["if!=than", '$L4', -1, 2],
			["create", '$L3', "Error", "The bucket already exists.", "IllegalArgument"],
			["throwError", '$L3'],
			["create", '$L3', "Error", '$L2', "Http"],
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
		'arrayToHex' => [
			["size", '$L1', '$P2'],
			["set", '$L2', 0],
			["create", '$P1', "String", ""],
			["get", '$L3', '$P2', '$L2'],
			["string.format", '$L4', "%02x", '$L3'],
			["string.concat", '$P1', '$P1', '$L4'],
			["math.add", '$L2', '$L2', 1],
			["if>=than", '$L2', '$L1', -5]
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
	 * @param string $accountID
	 * @param string $appKey
	 */
	public function __construct(string $accountID, string $appKey)
	{
		$this->interpreterStorage = array();
		$this->instanceDependencyStorage = [];
		$this->persistentStorage = array(array());
		InitSelfTest::initTest('Backblaze');
		
		$this->interpreterStorage['accountID'] = $accountID;
		$this->interpreterStorage['appKey'] = $appKey;
		

		$ip = new Interpreter(new Sandbox(Backblaze::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		if (array_key_exists("init",Backblaze::$SERVICE_CODE)) {
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
		Statistics::addCall("Backblaze", "createBucket");
		$ip = new Interpreter(new Sandbox(Backblaze::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Backblaze", "listBuckets");
		$ip = new Interpreter(new Sandbox(Backblaze::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Backblaze", "deleteBucket");
		$ip = new Interpreter(new Sandbox(Backblaze::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Backblaze", "deleteFile");
		$ip = new Interpreter(new Sandbox(Backblaze::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Backblaze", "getFileMetadata");
		$ip = new Interpreter(new Sandbox(Backblaze::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Backblaze", "listFiles");
		$ip = new Interpreter(new Sandbox(Backblaze::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Backblaze", "listFilesWithPrefix");
		$ip = new Interpreter(new Sandbox(Backblaze::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Backblaze", "uploadFile");
		$ip = new Interpreter(new Sandbox(Backblaze::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Backblaze", "downloadFile");
		$ip = new Interpreter(new Sandbox(Backblaze::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Backblaze", "advancedRequest");
		$ip = new Interpreter(new Sandbox(Backblaze::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		$ip = new Interpreter(new Sandbox(Backblaze::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		return $ip->saveAsString();
	}

	/**
	 * @param string $savedState
	 */
	public function loadAsString($savedState) {
		$sandbox = new Sandbox(Backblaze::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage);
		$ip = new Interpreter($sandbox);
		$ip->loadAsString($savedState);
		$this->persistentStorage = $sandbox->persistentStorage;
	}
}
