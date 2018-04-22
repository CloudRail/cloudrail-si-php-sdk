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

class AmazonS3 implements BusinessCloudStorage, AdvancedRequestSupporter
{
	private static $SERVICE_CODE = [
		'init' => [
			["if!=than", '$P0.region', "us-east-1", 2],
			["string.concat", '$P0.baseUrl', "s3-", '$P0.region', ".amazonaws.com"],
			["jumpRel", 1],
			["set", '$P0.baseUrl', "s3.amazonaws.com"],
			["set", '$P0.empty_body_hash', "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"],
			["set", '$P0.chunk_size', 5242880]
		],
		'listBuckets' => [
			["create", '$L0', "Object"],
			["string.concat", '$L0.url', "https://", '$P0.baseUrl'],
			["set", '$L0.method', "GET"],
			["create", '$L1', "Object"],
			["set", '$L0.requestHeaders', '$L1'],
			["set", '$L1.host', '$P0.baseUrl'],
			["callFunc", "getCurrentDate", '$P0', '$L1.x-amz-date'],
			["set", '$L1.x-amz-content-sha256', '$P0.empty_body_hash'],
			["create", '$L10', "Array"],
			["push", '$L10', "host"],
			["push", '$L10', "x-amz-content-sha256"],
			["push", '$L10', "x-amz-date"],
			["callFunc", "signRequest", '$P0', '$L0', NULL, NULL, '$L10', '$P0.empty_body_hash'],
			["http.requestCall", '$L2', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L2', 200],
			["xml.parse", '$L3', '$L2.responseBody'],
			["get", '$L4', '$L3.children.1'],
			["size", '$L5', '$L4.children'],
			["create", '$L6', "Number", 0],
			["create", '$P1', "Array"],
			["if<than", '$L6', '$L5', 7],
			["get", '$L7', '$L4.children', '$L6'],
			["create", '$L8', "Bucket"],
			["set", '$L8.name', '$L7.children.0.text'],
			["set", '$L8.identifier', '$L7.children.0.text'],
			["push", '$P1', '$L8'],
			["math.add", '$L6', '$L6', 1],
			["jumpRel", -8]
		],
		'createBucket' => [
			["callFunc", "checkNull", '$P0', '$P2'],
			["create", '$L0', "Object"],
			["set", '$L0.method', "PUT"],
			["string.concat", '$L0.url', "https://", '$P2', ".", '$P0.baseUrl'],
			["create", '$L1', "Object"],
			["set", '$L0.requestHeaders', '$L1'],
			["string.concat", '$L1.host', '$P2', ".", '$P0.baseUrl'],
			["set", '$L1.x-amz-acl', "private"],
			["callFunc", "getCurrentDate", '$P0', '$L1.x-amz-date'],
			["if!=than", '$P0.region', "us-east-1", 19],
			["create", '$L2', "Object"],
			["set", '$L2.name', "CreateBucketConfiguration"],
			["create", '$L3', "Object"],
			["set", '$L3.xmlns', "http://s3.amazonaws.com/doc/2006-03-01/"],
			["set", '$L2.attributes', '$L3'],
			["create", '$L3', "Array"],
			["create", '$L4', "Object"],
			["set", '$L4.name', "LocationConstraint"],
			["set", '$L4.text', '$P0.region'],
			["push", '$L3', '$L4'],
			["set", '$L2.children', '$L3'],
			["xml.stringify", '$L3', '$L2'],
			["size", '$L1.content-length', '$L3'],
			["string.concat", '$L1.content-length', '$L1.content-length', ""],
			["set", '$L1.content-type', "text/plain"],
			["stream.stringToStream", '$L0.requestBody', '$L3'],
			["hash.sha256", '$L4', '$L3'],
			["callFunc", "arrayToHex", '$P0', '$L1.x-amz-content-sha256', '$L4'],
			["jumpRel", 1],
			["set", '$L1.x-amz-content-sha256', '$P0.empty_body_hash'],
			["create", '$L10', "Array"],
			["if!=than", '$P0.region', "us-east-1", 2],
			["push", '$L10', "content-length"],
			["push", '$L10', "content-type"],
			["push", '$L10', "host"],
			["push", '$L10', "x-amz-acl"],
			["push", '$L10', "x-amz-content-sha256"],
			["push", '$L10', "x-amz-date"],
			["callFunc", "signRequest", '$P0', '$L0', NULL, NULL, '$L10', '$L1.x-amz-content-sha256'],
			["http.requestCall", '$L5', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L5', 200],
			["create", '$P1', "Bucket"],
			["set", '$P1.name', '$P2'],
			["set", '$P1.identifier', '$P2']
		],
		'deleteBucket' => [
			["callFunc", "checkBucket", '$P0', '$P1'],
			["create", '$L0', "Object"],
			["set", '$L0.method', "DELETE"],
			["string.concat", '$L0.url', "https://", '$P1.name', ".", '$P0.baseUrl'],
			["create", '$L1', "Object"],
			["set", '$L0.requestHeaders', '$L1'],
			["string.concat", '$L1.host', '$P1.name', ".", '$P0.baseUrl'],
			["callFunc", "getCurrentDate", '$P0', '$L1.x-amz-date'],
			["set", '$L1.x-amz-content-sha256', '$P0.empty_body_hash'],
			["create", '$L10', "Array"],
			["push", '$L10', "host"],
			["push", '$L10', "x-amz-content-sha256"],
			["push", '$L10', "x-amz-date"],
			["callFunc", "signRequest", '$P0', '$L0', NULL, NULL, '$L10', '$L1.x-amz-content-sha256'],
			["http.requestCall", '$L2', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L2', 204]
		],
		'listFiles' => [
			["callFunc", "checkBucket", '$P0', '$P2'],
			["create", '$P1', "Array"],
			["callFunc", "listFilesChunk", '$P0', '$L1', '$P2.name', '$L0', NULL],
			["set", '$L0', NULL],
			["size", '$L2', '$L1.children'],
			["create", '$L3', "Number", 0],
			["if<than", '$L3', '$L2', 13],
			["get", '$L4', '$L1.children', '$L3'],
			["if==than", '$L4.name', "NextContinuationToken", 1],
			["set", '$L0', '$L4.text'],
			["if==than", '$L4.name', "Contents", 7],
			["create", '$L5', "BusinessFileMetaData"],
			["set", '$L5.fileName', '$L4.children.0.text'],
			["set", '$L5.fileID', '$L4.children.0.text'],
			["math.add", '$L5.size', '$L4.children.3.text', 0],
			["create", '$L6', "Date", '$L4.children.1.text'],
			["set", '$L5.lastModified', '$L6.time'],
			["push", '$P1', '$L5'],
			["math.add", '$L3', '$L3', 1],
			["jumpRel", -14],
			["if!=than", '$L0', NULL, 1],
			["jumpRel", -20]
		],
		'listFilesWithPrefix' => [
			["callFunc", "checkBucket", '$P0', '$P2'],
			["callFunc", "checkPrefix", '$P0', '$P3'],
			["create", '$P1', "Array"],
			["callFunc", "listFilesChunk", '$P0', '$L1', '$P2.name', '$L0', '$P3'],
			["set", '$L0', NULL],
			["size", '$L2', '$L1.children'],
			["create", '$L3', "Number", 0],
			["if<than", '$L3', '$L2', 13],
			["get", '$L4', '$L1.children', '$L3'],
			["if==than", '$L4.name', "NextContinuationToken", 1],
			["set", '$L0', '$L4.text'],
			["if==than", '$L4.name', "Contents", 7],
			["create", '$L5', "BusinessFileMetaData"],
			["set", '$L5.fileName', '$L4.children.0.text'],
			["set", '$L5.fileID', '$L4.children.0.text'],
			["math.add", '$L5.size', '$L4.children.3.text', 0],
			["create", '$L6', "Date", '$L4.children.1.text'],
			["set", '$L5.lastModified', '$L6.time'],
			["push", '$P1', '$L5'],
			["math.add", '$L3', '$L3', 1],
			["jumpRel", -14],
			["if!=than", '$L0', NULL, 1],
			["jumpRel", -20]
		],
		'checkPrefix' => [
			["if==than", '$P1', NULL, 2],
			["create", '$L1', "Error", "Prefix supplied is null", "IllegalArgument"],
			["throwError", '$L1']
		],
		'getFileMetadata' => [
			["callFunc", "checkBucket", '$P0', '$P2'],
			["callFunc", "checkNull", '$P0', '$P3'],
			["create", '$L0', "Object"],
			["set", '$L0.method', "HEAD"],
			["string.concat", '$L0.url', "https://", '$P2.name', ".", '$P0.baseUrl', "/", '$P3'],
			["create", '$L1', "Object"],
			["set", '$L0.requestHeaders', '$L1'],
			["string.concat", '$L1.host', '$P2.name', ".", '$P0.baseUrl'],
			["callFunc", "getCurrentDate", '$P0', '$L1.x-amz-date'],
			["set", '$L1.x-amz-content-sha256', '$P0.empty_body_hash'],
			["create", '$L10', "Array"],
			["push", '$L10', "host"],
			["push", '$L10', "x-amz-content-sha256"],
			["push", '$L10', "x-amz-date"],
			["callFunc", "signRequest", '$P0', '$L0', NULL, NULL, '$L10', '$L1.x-amz-content-sha256'],
			["http.requestCall", '$L2', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L2', 200],
			["get", '$L3', '$L2.responseHeaders'],
			["create", '$P1', "BusinessFileMetaData"],
			["set", '$P1.fileName', '$P3'],
			["set", '$P1.fileID', '$P3'],
			["math.add", '$P1.size', '$L3.Content-Length', 0],
			["callFunc", "parseDate", '$P0', '$P1.lastModified', '$L3.Last-Modified']
		],
		'deleteFile' => [
			["callFunc", "checkNull", '$P0', '$P1'],
			["callFunc", "checkBucket", '$P0', '$P2'],
			["create", '$L0', "Object"],
			["set", '$L0.method', "DELETE"],
			["string.concat", '$L0.url', "https://", '$P2.name', ".", '$P0.baseUrl', "/", '$P1'],
			["create", '$L1', "Object"],
			["set", '$L0.requestHeaders', '$L1'],
			["string.concat", '$L1.host', '$P2.name', ".", '$P0.baseUrl'],
			["callFunc", "getCurrentDate", '$P0', '$L1.x-amz-date'],
			["set", '$L1.x-amz-content-sha256', '$P0.empty_body_hash'],
			["create", '$L10', "Array"],
			["push", '$L10', "host"],
			["push", '$L10', "x-amz-content-sha256"],
			["push", '$L10', "x-amz-date"],
			["callFunc", "signRequest", '$P0', '$L0', NULL, NULL, '$L10', '$L1.x-amz-content-sha256'],
			["http.requestCall", '$L2', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L2', 204]
		],
		'uploadFile' => [
			["callFunc", "checkBucket", '$P0', '$P1'],
			["callFunc", "checkNull", '$P0', '$P2'],
			["callFunc", "checkNull", '$P0', '$P3'],
			["callFunc", "checkSize", '$P0', '$P4'],
			["create", '$L0', "Object"],
			["set", '$L0.method', "POST"],
			["string.concat", '$L0.url', "https://", '$P1.name', ".", '$P0.baseUrl', "/", '$P2', "?uploads"],
			["create", '$L1', "Object"],
			["set", '$L0.requestHeaders', '$L1'],
			["string.concat", '$L1.host', '$P1.name', ".", '$P0.baseUrl'],
			["callFunc", "getCurrentDate", '$P0', '$L1.x-amz-date'],
			["set", '$L1.x-amz-content-sha256', '$P0.empty_body_hash'],
			["create", '$L10', "Array"],
			["push", '$L10', "host"],
			["push", '$L10', "x-amz-content-sha256"],
			["push", '$L10', "x-amz-date"],
			["create", '$L11', "Object"],
			["set", '$L11.uploads', ""],
			["create", '$L12', "Array"],
			["push", '$L12', "uploads"],
			["callFunc", "signRequest", '$P0', '$L0', '$L11', '$L12', '$L10', '$L1.x-amz-content-sha256'],
			["http.requestCall", '$L2', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L2', 200],
			["xml.parse", '$L3', '$L2.responseBody'],
			["get", '$L0', '$L3.children.2.text'],
			["create", '$L1', "Number", 1],
			["create", '$L2', "Number", 0],
			["create", '$L20', "Array"],
			["create", '$L3', "Object"],
			["set", '$L3.method', "PUT"],
			["string.concat", '$L3.url', "https://", '$P1.name', ".", '$P0.baseUrl', "/", '$P2', "?partNumber=", '$L1', "&uploadId=", '$L0'],
			["create", '$L4', "Object"],
			["set", '$L3.requestHeaders', '$L4'],
			["string.concat", '$L4.host', '$P1.name', ".", '$P0.baseUrl'],
			["callFunc", "getCurrentDate", '$P0', '$L4.x-amz-date'],
			["set", '$L4.content-type', "application/octet-stream"],
			["math.add", '$L5', '$L2', '$P0.chunk_size'],
			["if>than", '$L5', '$P4', 6],
			["math.multiply", '$L6', '$L2', -1],
			["math.add", '$L6', '$P4', '$L6'],
			["string.concat", '$L4.content-length', '$L6', ""],
			["stream.makeLimitedStream", '$L7', '$P3', '$L6'],
			["set", '$L2', '$P4'],
			["jumpRel", 3],
			["string.concat", '$L4.content-length', '$P0.chunk_size'],
			["stream.makeLimitedStream", '$L7', '$P3', '$P0.chunk_size'],
			["math.add", '$L2', '$L2', '$P0.chunk_size'],
			["stream.streamToData", '$L8', '$L7'],
			["hash.sha256", '$L9', '$L8'],
			["callFunc", "arrayToHex", '$P0', '$L4.x-amz-content-sha256', '$L9'],
			["stream.dataToStream", '$L3.requestBody', '$L8'],
			["create", '$L10', "Array"],
			["push", '$L10', "content-length"],
			["push", '$L10', "content-type"],
			["push", '$L10', "host"],
			["push", '$L10', "x-amz-content-sha256"],
			["push", '$L10', "x-amz-date"],
			["create", '$L11', "Object"],
			["string.concat", '$L11.partNumber', '$L1', ""],
			["set", '$L11.uploadId', '$L0'],
			["create", '$L12', "Array"],
			["push", '$L12', "partNumber"],
			["push", '$L12', "uploadId"],
			["callFunc", "signRequest", '$P0', '$L3', '$L11', '$L12', '$L10', '$L4.x-amz-content-sha256'],
			["http.requestCall", '$L13', '$L3'],
			["callFunc", "validateResponse", '$P0', '$L13', 200],
			["push", '$L20', '$L13.responseHeaders.ETag'],
			["if<than", '$L2', '$P4', 2],
			["math.add", '$L1', '$L1', 1],
			["jumpRel", -42],
			["create", '$L1', "Object"],
			["set", '$L1.method', "POST"],
			["string.concat", '$L1.url', "https://", '$P1.name', ".", '$P0.baseUrl', "/", '$P2', "?uploadId=", '$L0'],
			["create", '$L2', "Object"],
			["set", '$L1.requestHeaders', '$L2'],
			["string.concat", '$L2.host', '$P1.name', ".", '$P0.baseUrl'],
			["callFunc", "getCurrentDate", '$P0', '$L2.x-amz-date'],
			["create", '$L3', "Object"],
			["set", '$L3.name', "CompleteMultipartUpload"],
			["create", '$L3.children', "Array"],
			["size", '$L4', '$L20'],
			["create", '$L5', "Number", 0],
			["if<than", '$L5', '$L4', 15],
			["create", '$L6', "Object"],
			["set", '$L6.name', "Part"],
			["create", '$L6.children', "Array"],
			["create", '$L7', "Object"],
			["set", '$L7.name', "PartNumber"],
			["math.add", '$L8', '$L5', 1],
			["string.concat", '$L7.text', '$L8', ""],
			["push", '$L6.children', '$L7'],
			["create", '$L7', "Object"],
			["set", '$L7.name', "ETag"],
			["get", '$L7.text', '$L20', '$L5'],
			["push", '$L6.children', '$L7'],
			["push", '$L3.children', '$L6'],
			["math.add", '$L5', '$L5', 1],
			["jumpRel", -16],
			["xml.stringify", '$L4', '$L3'],
			["size", '$L2.content-length', '$L4'],
			["string.concat", '$L2.content-length', '$L2.content-length', ""],
			["stream.stringToStream", '$L1.requestBody', '$L4'],
			["hash.sha256", '$L5', '$L4'],
			["callFunc", "arrayToHex", '$P0', '$L2.x-amz-content-sha256', '$L5'],
			["create", '$L10', "Array"],
			["push", '$L10', "content-length"],
			["push", '$L10', "host"],
			["push", '$L10', "x-amz-content-sha256"],
			["push", '$L10', "x-amz-date"],
			["create", '$L11', "Object"],
			["set", '$L11.uploadId', '$L0'],
			["create", '$L12', "Array"],
			["push", '$L12', "uploadId"],
			["callFunc", "signRequest", '$P0', '$L1', '$L11', '$L12', '$L10', '$L2.x-amz-content-sha256'],
			["http.requestCall", '$L6', '$L1'],
			["callFunc", "validateResponse", '$P0', '$L6', 200]
		],
		'downloadFile' => [
			["callFunc", "checkNull", '$P0', '$P2'],
			["callFunc", "checkBucket", '$P0', '$P3'],
			["create", '$L0', "Object"],
			["set", '$L0.method', "GET"],
			["string.concat", '$L0.url', "https://", '$P3.name', ".", '$P0.baseUrl', "/", '$P2'],
			["create", '$L1', "Object"],
			["set", '$L0.requestHeaders', '$L1'],
			["string.concat", '$L1.host', '$P3.name', ".", '$P0.baseUrl'],
			["callFunc", "getCurrentDate", '$P0', '$L1.x-amz-date'],
			["set", '$L1.x-amz-content-sha256', '$P0.empty_body_hash'],
			["create", '$L10', "Array"],
			["push", '$L10', "host"],
			["push", '$L10', "x-amz-content-sha256"],
			["push", '$L10', "x-amz-date"],
			["callFunc", "signRequest", '$P0', '$L0', NULL, NULL, '$L10', '$L1.x-amz-content-sha256'],
			["http.requestCall", '$L2', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L2', 200],
			["set", '$P1', '$L2.responseBody']
		],
		'AdvancedRequestSupporter:advancedRequest' => [
			["create", '$L0', "Object"],
			["if!=than", '$P2.appendBaseUrl', 0, 2],
			["create", '$L1', "Error", "Disable base URL appending and provide a full URL."],
			["throwError", '$L1'],
			["set", '$L0.url', '$P2.url'],
			["set", '$L0.requestHeaders', '$P2.headers'],
			["set", '$L0.method', '$P2.method'],
			["if!=than", '$P2.body', NULL, 1],
			["set", '$L0.requestBody', '$P2.body'],
			["if!=than", '$P2.appendAuthorization', 0, 23],
			["callFunc", "extractQuery", '$P0', '$L1', '$L0.url'],
			["if!=than", '$L1', NULL, 2],
			["object.getKeyArray", '$L2', '$L1'],
			["array.sort", '$L3', '$L2'],
			["if==than", '$L0.requestHeaders.x-amz-content-sha256', NULL, 7],
			["if==than", '$P2.body', NULL, 2],
			["set", '$L0.requestHeaders.x-amz-content-sha256', '$P0.empty_body_hash'],
			["jumpRel", 4],
			["stream.streamToData", '$L4', '$P2.body'],
			["hash.sha256", '$L5', '$L4'],
			["callFunc", "arrayToHex", '$P0', '$L0.requestHeaders.x-amz-content-sha256', '$L5'],
			["stream.dataToStream", '$L0.requestBody', '$L4'],
			["if==than", '$L0.requestHeaders.x-amz-date', NULL, 1],
			["callFunc", "getCurrentDate", '$P0', '$L0.requestHeaders.x-amz-date'],
			["if==than", '$L0.requestHeaders.host', NULL, 5],
			["string.indexOf", '$L4', '$L0.url', "/", 10],
			["if==than", '$L4', -1, 2],
			["string.substring", '$L0.requestHeaders.host', '$L0.url', 8],
			["jumpRel", 1],
			["string.substring", '$L0.requestHeaders.host', '$L0.url', 8, '$L4'],
			["object.getKeyArray", '$L4', '$L0.requestHeaders'],
			["array.sort", '$L5', '$L4'],
			["callFunc", "signRequest", '$P0', '$L0', '$L1', '$L3', '$L5', '$L0.requestHeaders.x-amz-content-sha256'],
			["http.requestCall", '$L1', '$L0'],
			["if!=than", '$P2.checkErrors', 0, 1],
			["callFunc", "validateResponse", '$P0', '$L1'],
			["create", '$P1', "AdvancedRequestResponse"],
			["set", '$P1.status', '$L1.code'],
			["set", '$P1.headers', '$L1.responseHeaders'],
			["set", '$P1.body', '$L1.responseBody']
		],
		'extractQuery' => [
			["string.split", '$L0', '$P2', "\\?", 2],
			["size", '$L1', '$L0'],
			["if==than", '$L1', 1, 1],
			["return"],
			["create", '$P1', "Object"],
			["string.split", '$L1', '$L0.1', "&"],
			["size", '$L2', '$L1'],
			["create", '$L3', "Number", 0],
			["if<than", '$L3', '$L2', 9],
			["get", '$L4', '$L1', '$L3'],
			["string.split", '$L5', '$L4', "="],
			["size", '$L6', '$L5'],
			["if==than", '$L6', 1, 2],
			["set", '$P1', "", '$L5.0'],
			["jumpRel", 1],
			["set", '$P1', '$L5.1', '$L5.0'],
			["math.add", '$L3', '$L3', 1],
			["jumpRel", -10]
		],
		'validateResponse' => [
			["if==than", '$P2', NULL, 2],
			["if>=than", '$P1.code', 400, 20],
			["jumpRel", 1],
			["if!=than", '$P1.code', '$P2', 18],
			["if==than", '$P1.code', 404, 2],
			["create", '$L1', "Error", "File does not exist", "NotFound"],
			["throwError", '$L1'],
			["stream.streamToString", '$L0', '$P1.responseBody'],
			["string.indexOf", '$L1', '$L0', "Your previous request to create the named bucket succeeded"],
			["if!=than", '$L1', -1, 2],
			["create", '$L1', "Error", "Bucket already exists!", "IllegalArgument"],
			["throwError", '$L1'],
			["string.indexOf", '$L1', '$L0', "The specified key does not exist"],
			["if!=than", '$L1', -1, 2],
			["create", '$L1', "Error", "File does not exist", "NotFound"],
			["throwError", '$L1'],
			["string.indexOf", '$L1', '$L0', "The specified bucket does not exist"],
			["if!=than", '$L1', -1, 2],
			["create", '$L1', "Error", "Bucket does not exist", "NotFound"],
			["throwError", '$L1'],
			["create", '$L1', "Error", '$L0', "Http"],
			["throwError", '$L1']
		],
		'signRequest' => [
			["create", '$L0', "String", ""],
			["string.concat", '$L0', '$P1.method', "\n"],
			["string.indexOf", '$L1', '$P1.url', "amazonaws.com"],
			["math.add", '$L1', '$L1', 13],
			["string.indexOf", '$L2', '$P1.url', "?"],
			["if==than", '$L2', -1, 2],
			["string.substring", '$L1', '$P1.url', '$L1'],
			["jumpRel", 1],
			["string.substring", '$L1', '$P1.url', '$L1', '$L2'],
			["if==than", '$L1', "", 1],
			["set", '$L1', "/"],
			["string.concat", '$L0', '$L0', '$L1', "\n"],
			["if==than", '$P2', NULL, 2],
			["string.concat", '$L0', '$L0', "\n"],
			["jumpRel", 13],
			["size", '$L1', '$P3'],
			["create", '$L2', "Number", 0],
			["create", '$L3', "String", ""],
			["if<than", '$L2', '$L1', 7],
			["get", '$L4', '$P3', '$L2'],
			["get", '$L5', '$P2', '$L4'],
			["string.urlEncode", '$L4', '$L4'],
			["string.urlEncode", '$L5', '$L5'],
			["string.concat", '$L3', '$L3', "&", '$L4', "=", '$L5'],
			["math.add", '$L2', '$L2', 1],
			["jumpRel", -8],
			["string.substring", '$L3', '$L3', 1],
			["string.concat", '$L0', '$L0', '$L3', "\n"],
			["size", '$L1', '$P4'],
			["create", '$L2', "Number", 0],
			["if<than", '$L2', '$L1', 5],
			["get", '$L3', '$P4', '$L2'],
			["get", '$L4', '$P1.requestHeaders', '$L3'],
			["string.concat", '$L0', '$L0', '$L3', ":", '$L4', "\n"],
			["math.add", '$L2', '$L2', 1],
			["jumpRel", -6],
			["string.concat", '$L0', '$L0', "\n"],
			["size", '$L1', '$P4'],
			["create", '$L2', "Number", 0],
			["create", '$L11', "String", ""],
			["if<than", '$L2', '$L1', 8],
			["if!=than", '$L2', 0, 2],
			["string.concat", '$L0', '$L0', ";"],
			["string.concat", '$L11', '$L11', ";"],
			["get", '$L3', '$P4', '$L2'],
			["string.concat", '$L0', '$L0', '$L3'],
			["string.concat", '$L11', '$L11', '$L3'],
			["math.add", '$L2', '$L2', 1],
			["jumpRel", -9],
			["string.concat", '$L0', '$L0', "\n"],
			["string.concat", '$L0', '$L0', '$P5'],
			["hash.sha256", '$L1', '$L0'],
			["callFunc", "arrayToHex", '$P0', '$L0', '$L1'],
			["string.substring", '$L1', '$P1.requestHeaders.x-amz-date', 0, 8],
			["string.concat", '$L1', '$L1', "/", '$P0.region', "/s3/aws4_request"],
			["string.concat", '$L0', "AWS4-HMAC-SHA256\n", '$P1.requestHeaders.x-amz-date', "\n", '$L1', "\n", '$L0'],
			["string.substring", '$L1', '$P1.requestHeaders.x-amz-date', 0, 8],
			["string.concat", '$L2', "AWS4", '$P0.secretAccessKey'],
			["crypt.hmac.sha256", '$L3', '$L2', '$L1'],
			["crypt.hmac.sha256", '$L3', '$L3', '$P0.region'],
			["crypt.hmac.sha256", '$L3', '$L3', "s3"],
			["crypt.hmac.sha256", '$L3', '$L3', "aws4_request"],
			["crypt.hmac.sha256", '$L3', '$L3', '$L0'],
			["callFunc", "arrayToHex", '$P0', '$L4', '$L3'],
			["string.substring", '$L1', '$P1.requestHeaders.x-amz-date', 0, 8],
			["string.concat", '$P1.requestHeaders.Authorization', "AWS4-HMAC-SHA256 Credential=", '$P0.accessKeyId', "/", '$L1', "/", '$P0.region', "/s3/aws4_request,SignedHeaders=", '$L11', ",Signature=", '$L4']
		],
		'listFilesChunk' => [
			["create", '$L0', "Object"],
			["set", '$L0.method', "GET"],
			["string.concat", '$L0.url', "https://", '$P2', ".", '$P0.baseUrl', "?list-type=2"],
			["if!=than", '$P3', NULL, 3],
			["string.urlEncode", '$L1', '$P3'],
			["string.concat", '$L2', '$L0.url', "&continuation-token=", '$L1'],
			["string.concat", '$L0.url', '$L2'],
			["if!=than", '$P4', NULL, 3],
			["string.urlEncode", '$L1', '$P4'],
			["string.concat", '$L3', '$L0.url', "&prefix=", '$L1'],
			["string.concat", '$L0.url', '$L3'],
			["create", '$L1', "Object"],
			["set", '$L0.requestHeaders', '$L1'],
			["string.concat", '$L1.host', '$P2', ".", '$P0.baseUrl'],
			["callFunc", "getCurrentDate", '$P0', '$L1.x-amz-date'],
			["set", '$L1.x-amz-content-sha256', '$P0.empty_body_hash'],
			["create", '$L10', "Array"],
			["push", '$L10', "host"],
			["push", '$L10', "x-amz-content-sha256"],
			["push", '$L10', "x-amz-date"],
			["create", '$L11', "Object"],
			["set", '$L11.list-type', "2"],
			["create", '$L12', "Array"],
			["if!=than", '$P3', NULL, 2],
			["push", '$L12', "continuation-token"],
			["set", '$L11.continuation-token', '$P3'],
			["push", '$L12', "list-type"],
			["if!=than", '$P4', NULL, 2],
			["push", '$L12', "prefix"],
			["set", '$L11.prefix', '$P4'],
			["callFunc", "signRequest", '$P0', '$L0', '$L11', '$L12', '$L10', '$L1.x-amz-content-sha256'],
			["http.requestCall", '$L2', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L2', 200],
			["xml.parse", '$P1', '$L2.responseBody']
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
		'getCurrentDate' => [
			["create", '$L0', "Date"],
			["set", '$L1', '$L0.rfcTime1123'],
			["create", '$P1', "String", ""],
			["string.substring", '$L2', '$L1', 12, 16],
			["string.concat", '$P1', '$P1', '$L2'],
			["string.substring", '$L2', '$L1', 8, 11],
			["callFunc", "getMonthNumber", '$P0', '$L3', '$L2'],
			["string.concat", '$P1', '$P1', '$L3'],
			["string.substring", '$L2', '$L1', 5, 7],
			["string.concat", '$P1', '$P1', '$L2', "T"],
			["string.substring", '$L2', '$L1', 17, 19],
			["string.concat", '$P1', '$P1', '$L2'],
			["string.substring", '$L2', '$L1', 20, 22],
			["string.concat", '$P1', '$P1', '$L2'],
			["string.substring", '$L2', '$L1', 23, 25],
			["string.concat", '$P1', '$P1', '$L2', "Z"]
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
	 * @param string $accessKeyId
	 * @param string $secretAccessKey
	 * @param string $region
	 */
	public function __construct(string $accessKeyId, string $secretAccessKey, string $region)
	{
		$this->interpreterStorage = array();
		$this->instanceDependencyStorage = [];
		$this->persistentStorage = array(array());
		InitSelfTest::initTest('AmazonS3');
		
		$this->interpreterStorage['accessKeyId'] = $accessKeyId;
		$this->interpreterStorage['secretAccessKey'] = $secretAccessKey;
		$this->interpreterStorage['region'] = $region;
		

		$ip = new Interpreter(new Sandbox(AmazonS3::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		if (array_key_exists("init",AmazonS3::$SERVICE_CODE)) {
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
		Statistics::addCall("AmazonS3", "createBucket");
		$ip = new Interpreter(new Sandbox(AmazonS3::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("AmazonS3", "listBuckets");
		$ip = new Interpreter(new Sandbox(AmazonS3::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("AmazonS3", "deleteBucket");
		$ip = new Interpreter(new Sandbox(AmazonS3::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("AmazonS3", "deleteFile");
		$ip = new Interpreter(new Sandbox(AmazonS3::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("AmazonS3", "getFileMetadata");
		$ip = new Interpreter(new Sandbox(AmazonS3::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("AmazonS3", "listFiles");
		$ip = new Interpreter(new Sandbox(AmazonS3::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("AmazonS3", "listFilesWithPrefix");
		$ip = new Interpreter(new Sandbox(AmazonS3::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("AmazonS3", "uploadFile");
		$ip = new Interpreter(new Sandbox(AmazonS3::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("AmazonS3", "downloadFile");
		$ip = new Interpreter(new Sandbox(AmazonS3::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("AmazonS3", "advancedRequest");
		$ip = new Interpreter(new Sandbox(AmazonS3::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		$ip = new Interpreter(new Sandbox(AmazonS3::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		return $ip->saveAsString();
	}

	/**
	 * @param string $savedState
	 */
	public function loadAsString($savedState) {
		$sandbox = new Sandbox(AmazonS3::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage);
		$ip = new Interpreter($sandbox);
		$ip->loadAsString($savedState);
		$this->persistentStorage = $sandbox->persistentStorage;
	}
}
