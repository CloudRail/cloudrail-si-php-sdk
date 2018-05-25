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

class MicrosoftAzure implements BusinessCloudStorage, AdvancedRequestSupporter
{
	private static $SERVICE_CODE = [
		'Storage:createBucket' => [
			["callFunc", "checkNull", '$P0', '$P2'],
			["create", '$L1', "Date"],
			["create", '$L4', "Object"],
			["set", '$L4', '$L1.rfcTime1123', "x-ms-date"],
			["set", '$L4', "2015-12-11", "x-ms-version"],
			["create", '$L3', "String"],
			["string.concat", '$L3', "/", '$P0.accountName', "/", '$P2', "\nrestype:share"],
			["create", '$L2', "String"],
			["callFunc", "signedString", '$L2', "PUT", "", '$L4', '$L3', '$P0'],
			["string.concat", '$L2', "SharedKey ", '$P0.accountName', ":", '$L2'],
			["set", '$L4', '$L2', "Authorization"],
			["create", '$L5', "Object"],
			["string.concat", '$L0', "https://", '$P0.accountName', ".file.core.windows.net/", '$P2', "?restype=share"],
			["set", '$L5.url', '$L0'],
			["set", '$L5.method', "PUT"],
			["set", '$L5.requestHeaders', '$L4'],
			["http.requestCall", '$L6', '$L5'],
			["callFunc", "checkHttpErrors", '$P0', '$L6', "authentication", 201],
			["set", '$L12', '$L6.responseHeaders'],
			["create", '$L10', "Bucket"],
			["set", '$L10.name', '$P2'],
			["if!=than", '$L12.Etag', NULL, 2],
			["set", '$L10.identifier', '$L12.Etag'],
			["jumpRel", 1],
			["set", '$L10.identifier', '$L12.ETag'],
			["set", '$P1', '$L10']
		],
		'Storage:deleteBucket' => [
			["callFunc", "checkBucket", '$P0', '$P1'],
			["create", '$L1', "Date"],
			["create", '$L4', "Object"],
			["set", '$L4', '$L1.rfcTime1123', "x-ms-date"],
			["set", '$L4', "2015-12-11", "x-ms-version"],
			["create", '$L3', "String"],
			["string.concat", '$L3', "/", '$P0.accountName', "/", '$P1.name', "\nrestype:share"],
			["create", '$L2', "String"],
			["callFunc", "signedString", '$L2', "DELETE", "", '$L4', '$L3', '$P0'],
			["string.concat", '$L2', "SharedKey ", '$P0.accountName', ":", '$L2'],
			["set", '$L4', '$L2', "Authorization"],
			["create", '$L5', "Object"],
			["string.concat", '$L0', "https://", '$P0.accountName', ".file.core.windows.net/", '$P1.name', "?restype=share"],
			["set", '$L5.url', '$L0'],
			["set", '$L5.method', "DELETE"],
			["set", '$L5.requestHeaders', '$L4'],
			["http.requestCall", '$L6', '$L5'],
			["callFunc", "checkHttpErrors", '$P0', '$L6', "authentication", 202]
		],
		'Storage:listBuckets' => [
			["create", '$L1', "Date"],
			["create", '$L4', "Object"],
			["set", '$L4', '$L1.rfcTime1123', "x-ms-date"],
			["set", '$L4', "2015-12-11", "x-ms-version"],
			["create", '$L3', "String"],
			["string.concat", '$L3', "/", '$P0.accountName', "/\ncomp:list"],
			["create", '$L2', "String"],
			["callFunc", "signedString", '$L2', "GET", "", '$L4', '$L3', '$P0'],
			["string.concat", '$L2', "SharedKey ", '$P0.accountName', ":", '$L2'],
			["set", '$L4', '$L2', "Authorization"],
			["create", '$L5', "Object"],
			["string.concat", '$L0', "https://", '$P0.accountName', ".file.core.windows.net/?comp=list"],
			["set", '$L5.url', '$L0'],
			["set", '$L5.method', "GET"],
			["set", '$L5.requestHeaders', '$L4'],
			["http.requestCall", '$L6', '$L5'],
			["callFunc", "checkHttpErrors", '$P0', '$L6', "authentication", 200],
			["create", '$L14', "Array"],
			["stream.streamToString", '$L12', '$L6.responseBody'],
			["xml.parse", '$L12', '$L12'],
			["size", '$L10', '$L12.children.0.children'],
			["if!=than", '$L10', 0, 7],
			["math.add", '$L10', '$L10', -1],
			["create", '$L13', "Bucket"],
			["get", '$L15', '$L12.children.0.children', '$L10'],
			["set", '$L13.identifier', '$L15.children.1.children.1.text'],
			["set", '$L13.name', '$L15.children.0.text'],
			["push", '$L14', '$L13'],
			["jumpRel", -8],
			["set", '$P1', '$L14']
		],
		'Storage:upload' => [
			["callFunc", "checkBucket", '$P0', '$P1'],
			["callFunc", "checkNull", '$P0', '$P2'],
			["callFunc", "checkNull", '$P0', '$P3'],
			["callFunc", "checkNull", '$P0', '$P4'],
			["callFunc", "checkSize", '$P0', '$P4'],
			["create", '$L1', "Date"],
			["create", '$L4', "Object"],
			["set", '$L4', '$L1.rfcTime1123', "x-ms-date"],
			["set", '$L4', "2015-12-11", "x-ms-version"],
			["string.concat", '$L4.x-ms-content-length', '$P4', ""],
			["set", '$L4', "file", "x-ms-type"],
			["create", '$L3', "String"],
			["string.concat", '$L3', "/", '$P0.accountName', "/", '$P1.name', "/", '$P2'],
			["create", '$L2', "String"],
			["callFunc", "signedString", '$L2', "PUT", "", '$L4', '$L3', '$P0'],
			["string.concat", '$L2', "SharedKey ", '$P0.accountName', ":", '$L2'],
			["set", '$L4', '$L2', "Authorization"],
			["create", '$L5', "Object"],
			["string.concat", '$L0', "https://", '$P0.accountName', ".file.core.windows.net/", '$P1.name', "/", '$P2'],
			["set", '$L5.url', '$L0'],
			["set", '$L5.method', "PUT"],
			["set", '$L5.requestHeaders', '$L4'],
			["http.requestCall", '$L6', '$L5'],
			["callFunc", "checkHttpErrors", '$P0', '$L6', "authentication", 201],
			["set", '$L12', '$L6.responseHeaders'],
			["create", '$L1', "Date"],
			["create", '$L4', "Object"],
			["set", '$L4', '$L1.rfcTime1123', "x-ms-date"],
			["set", '$L4', "2015-12-11", "x-ms-version"],
			["set", '$L4', "update", "x-ms-write"],
			["set", '$L10', 3999999],
			["set", '$L13', 4000000],
			["set", '$L14', '$P4'],
			["set", '$L15', 0],
			["if!=than", '$L15', '$P4', 30],
			["set", '$L16', 0],
			["math.add", '$L16', '$L15', '$L10'],
			["math.add", '$L17', '$L13', 1],
			["set", '$L4', '$L13', "Content-Length"],
			["string.concat", '$L3', "bytes=", '$L15', "-", '$L16'],
			["if<=than", '$L14', '$L13', 3],
			["set", '$L4', '$L14', "Content-Length"],
			["math.add", '$L17', '$P4', -1],
			["string.concat", '$L3', "bytes=", '$L15', "-", '$L17'],
			["set", '$L4', '$L3', "x-ms-range"],
			["math.add", '$L15', '$L15', '$L4.Content-Length'],
			["math.multiply", '$L16', '$L4.Content-Length', -1],
			["math.add", '$L14', '$L14', '$L16'],
			["create", '$L3', "String"],
			["string.concat", '$L3', "/", '$P0.accountName', "/", '$P1.name', "/", '$P2', "\ncomp:range"],
			["create", '$L2', "String"],
			["callFunc", "signedString", '$L2', "PUT", "", '$L4', '$L3', '$P0'],
			["string.concat", '$L2', "SharedKey ", '$P0.accountName', ":", '$L2'],
			["set", '$L4', '$L2', "Authorization"],
			["create", '$L5', "Object"],
			["string.concat", '$L0', "https://", '$P0.accountName', ".file.core.windows.net/", '$P1.name', "/", '$P2', "?comp=range"],
			["set", '$L5.url', '$L0'],
			["set", '$L5.method', "PUT"],
			["set", '$L5.requestHeaders', '$L4'],
			["stream.makeLimitedStream", '$L0', '$P3', '$L4.Content-Length'],
			["set", '$L5.requestBody', '$L0'],
			["string.concat", '$L4.Content-Length', '$L4.Content-Length', ""],
			["http.requestCall", '$L6', '$L5'],
			["callFunc", "checkHttpErrors", '$P0', '$L6', "authentication", 201],
			["jumpRel", -31]
		],
		'Storage:download' => [
			["callFunc", "checkNull", '$P0', '$P2'],
			["callFunc", "checkBucket", '$P0', '$P3'],
			["create", '$L1', "Date"],
			["create", '$L4', "Object"],
			["set", '$L4', '$L1.rfcTime1123', "x-ms-date"],
			["set", '$L4', "2015-12-11", "x-ms-version"],
			["create", '$L3', "String"],
			["string.concat", '$L3', "/", '$P0.accountName', "/", '$P3.name', "/", '$P2'],
			["create", '$L2', "String"],
			["callFunc", "signedString", '$L2', "GET", "", '$L4', '$L3', '$P0'],
			["string.concat", '$L2', "SharedKey ", '$P0.accountName', ":", '$L2'],
			["set", '$L4', '$L2', "Authorization"],
			["create", '$L5', "Object"],
			["string.concat", '$L0', "https://", '$P0.accountName', ".file.core.windows.net/", '$P3.name', "/", '$P2'],
			["set", '$L5.url', '$L0'],
			["set", '$L5.method', "GET"],
			["set", '$L5.requestHeaders', '$L4'],
			["http.requestCall", '$L6', '$L5'],
			["callFunc", "checkHttpErrors", '$P0', '$L6', "authentication", 200],
			["set", '$P1', '$L6.responseBody']
		],
		'Storage:listFiles' => [
			["callFunc", "checkBucket", '$P0', '$P2'],
			["create", '$L1', "Date"],
			["create", '$L4', "Object"],
			["set", '$L4', '$L1.rfcTime1123', "x-ms-date"],
			["set", '$L4', "2015-12-11", "x-ms-version"],
			["create", '$L3', "String"],
			["string.concat", '$L3', "/", '$P0.accountName', "/", '$P2.name', "\ncomp:list", "\nrestype:directory"],
			["create", '$L2', "String"],
			["callFunc", "signedString", '$L2', "GET", "", '$L4', '$L3', '$P0'],
			["string.concat", '$L2', "SharedKey ", '$P0.accountName', ":", '$L2'],
			["set", '$L4', '$L2', "Authorization"],
			["create", '$L5', "Object"],
			["string.concat", '$L0', "https://", '$P0.accountName', ".file.core.windows.net/", '$P2.name', "?restype=directory&comp=list"],
			["set", '$L5.url', '$L0'],
			["set", '$L5.method', "GET"],
			["set", '$L5.requestHeaders', '$L4'],
			["http.requestCall", '$L6', '$L5'],
			["callFunc", "checkHttpErrors", '$P0', '$L6', "authentication", 200],
			["create", '$L14', "Array"],
			["stream.streamToString", '$L12', '$L6.responseBody'],
			["xml.parse", '$L12', '$L12'],
			["size", '$L10', '$L12.children.0.children'],
			["if!=than", '$L10', 0, 19],
			["math.add", '$L10', '$L10', -1],
			["create", '$L13', "BusinessFileMetaData"],
			["get", '$L15', '$L12.children.0.children', '$L10'],
			["if==than", '$L15.name', "Directory", 8],
			["callFunc", "getSubDirectories", '$P0', '$L30', '$P2', '$L15.children.0.text'],
			["size", '$L50', '$L30'],
			["if!=than", '$L50', 0, 4],
			["math.add", '$L50', '$L50', -1],
			["get", '$L51', '$L30', '$L50'],
			["push", '$L14', '$L51'],
			["jumpRel", -5],
			["jumpRel", 6],
			["set", '$L13.fileName', '$L15.children.0.text'],
			["size", '$L16', '$L15.children.1.children'],
			["if!=than", '$L16', 0, 2],
			["math.add", '$L11', '$L15.children.1.children.0.text', 0],
			["set", '$L13.size', '$L11'],
			["push", '$L14', '$L13'],
			["jumpRel", -20],
			["set", '$P1', '$L14']
		],
		'Storage:listFilesWithPrefix' => [
			["callFunc", "checkBucket", '$P0', '$P2'],
			["callFunc", "checkPrefix", '$P0', '$P3'],
			["create", '$L1', "Date"],
			["create", '$L4', "Object"],
			["set", '$L4', '$L1.rfcTime1123', "x-ms-date"],
			["set", '$L4', "2016-05-31", "x-ms-version"],
			["create", '$L3', "String"],
			["string.concat", '$L3', "/", '$P0.accountName', "/", '$P2.name', "\ncomp:list", "\nprefix:", '$P3', "\nrestype:directory"],
			["create", '$L2', "String"],
			["callFunc", "signedString", '$L2', "GET", "", '$L4', '$L3', '$P0'],
			["string.concat", '$L2', "SharedKey ", '$P0.accountName', ":", '$L2'],
			["set", '$L4', '$L2', "Authorization"],
			["create", '$L5', "Object"],
			["string.concat", '$L0', "https://", '$P0.accountName', ".file.core.windows.net/", '$P2.name', "?comp=list&prefix=", '$P3', "&restype=directory"],
			["set", '$L5.url', '$L0'],
			["set", '$L5.method', "GET"],
			["set", '$L5.requestHeaders', '$L4'],
			["http.requestCall", '$L6', '$L5'],
			["callFunc", "checkHttpErrors", '$P0', '$L6', "authentication", 200],
			["create", '$L14', "Array"],
			["stream.streamToString", '$L12', '$L6.responseBody'],
			["xml.parse", '$L12', '$L12'],
			["size", '$L10', '$L12.children.1.children'],
			["if!=than", '$L10', 0, 20],
			["math.add", '$L10', '$L10', -1],
			["create", '$L13', "BusinessFileMetaData"],
			["get", '$L15', '$L12.children.1.children', '$L10'],
			["if==than", '$L15.name', "Directory", 8],
			["create", '$L20', "String"],
			["string.concat", '$L20', '$L15.children.0.text', "/"],
			["set", '$L13.fileName', '$L20'],
			["size", '$L16', '$L15.children.1.children'],
			["if!=than", '$L16', 0, 2],
			["math.add", '$L11', '$L15.children.1.children.0.text', 0],
			["set", '$L13.size', '$L11'],
			["push", '$L14', '$L13'],
			["if==than", '$L15.name', "File", 6],
			["set", '$L13.fileName', '$L15.children.0.text'],
			["size", '$L16', '$L15.children.1.children'],
			["if!=than", '$L16', 0, 2],
			["math.add", '$L11', '$L15.children.1.children.0.text', 0],
			["set", '$L13.size', '$L11'],
			["push", '$L14', '$L13'],
			["jumpRel", -21],
			["set", '$P1', '$L14']
		],
		'checkPrefix' => [
			["if==than", '$P1', NULL, 2],
			["create", '$L1', "Error", "Prefix supplied is null", "IllegalArgument"],
			["throwError", '$L1']
		],
		'getSubDirectories' => [
			["create", '$L1', "Date"],
			["create", '$L4', "Object"],
			["set", '$L4', '$L1.rfcTime1123', "x-ms-date"],
			["set", '$L4', "2015-12-11", "x-ms-version"],
			["create", '$L3', "String"],
			["string.concat", '$L3', "/", '$P0.accountName', "/", '$P2.name', "/", '$P3', "\ncomp:list", "\nrestype:directory"],
			["create", '$L2', "String"],
			["callFunc", "signedString", '$L2', "GET", "", '$L4', '$L3', '$P0'],
			["string.concat", '$L2', "SharedKey ", '$P0.accountName', ":", '$L2'],
			["set", '$L4', '$L2', "Authorization"],
			["create", '$L5', "Object"],
			["string.concat", '$L0', "https://", '$P0.accountName', ".file.core.windows.net/", '$P2.name', "/", '$P3', "?restype=directory&comp=list"],
			["set", '$L5.url', '$L0'],
			["set", '$L5.method', "GET"],
			["set", '$L5.requestHeaders', '$L4'],
			["http.requestCall", '$L6', '$L5'],
			["callFunc", "checkHttpErrors", '$P0', '$L6', "authentication", 200],
			["create", '$L14', "Array"],
			["stream.streamToString", '$L12', '$L6.responseBody'],
			["xml.parse", '$L12', '$L12'],
			["size", '$L10', '$L12.children.0.children'],
			["if==than", '$L10', 0, 6],
			["create", '$L40', "BusinessFileMetaData"],
			["string.concat", '$L44', '$P3', "/"],
			["set", '$L40.fileName', '$L44'],
			["push", '$L14', '$L40'],
			["set", '$P1', '$L14'],
			["return"],
			["if!=than", '$L10', 0, 21],
			["math.add", '$L10', '$L10', -1],
			["create", '$L13', "BusinessFileMetaData"],
			["get", '$L15', '$L12.children.0.children', '$L10'],
			["if==than", '$L15.name', "Directory", 9],
			["string.concat", '$L41', '$P3', "/", '$L15.children.0.text'],
			["callFunc", "getSubDirectories", '$P0', '$L42', '$P2', '$L41'],
			["size", '$L50', '$L42'],
			["if!=than", '$L50', 0, 4],
			["math.add", '$L50', '$L50', -1],
			["get", '$L51', '$L42', '$L50'],
			["push", '$L14', '$L51'],
			["jumpRel", -5],
			["jumpRel", 7],
			["string.concat", '$L43', '$P3', "/", '$L15.children.0.text'],
			["set", '$L13.fileName', '$L43'],
			["size", '$L16', '$L15.children.1.children'],
			["if!=than", '$L16', 0, 2],
			["math.add", '$L11', '$L15.children.1.children.0.text', 0],
			["set", '$L13.size', '$L11'],
			["push", '$L14', '$L13'],
			["jumpRel", -22],
			["set", '$P1', '$L14']
		],
		'Storage:getFileMetadata' => [
			["callFunc", "checkNull", '$P0', '$P3'],
			["callFunc", "checkBucket", '$P0', '$P2'],
			["create", '$L1', "Date"],
			["create", '$L4', "Object"],
			["set", '$L4', '$L1.rfcTime1123', "x-ms-date"],
			["set", '$L4', "2015-12-11", "x-ms-version"],
			["create", '$L3', "String"],
			["string.concat", '$L3', "/", '$P0.accountName', "/", '$P2.name', "/", '$P3'],
			["create", '$L2', "String"],
			["callFunc", "signedString", '$L2', "HEAD", "", '$L4', '$L3', '$P0'],
			["string.concat", '$L2', "SharedKey ", '$P0.accountName', ":", '$L2'],
			["set", '$L4', '$L2', "Authorization"],
			["create", '$L5', "Object"],
			["string.concat", '$L0', "https://", '$P0.accountName', ".file.core.windows.net/", '$P2.name', "/", '$P3'],
			["set", '$L5.url', '$L0'],
			["set", '$L5.method', "HEAD"],
			["set", '$L5.requestHeaders', '$L4'],
			["http.requestCall", '$L6', '$L5'],
			["callFunc", "checkHttpErrors", '$P0', '$L6', "get metadata", 200],
			["set", '$L6', '$L6.responseHeaders'],
			["create", '$L7', "BusinessFileMetaData"],
			["set", '$L7.fileName', '$P3'],
			["set", '$L7.fileID', '$L6.ETag'],
			["math.add", '$L7.size', '$L6.Content-Length', 0],
			["callFunc", "parseDate", '$P0', '$L7.lastModified', '$L6.Last-Modified'],
			["set", '$P1', '$L7']
		],
		'Storage:deleteFile' => [
			["callFunc", "checkNull", '$P0', '$P1'],
			["callFunc", "checkBucket", '$P0', '$P2'],
			["create", '$L1', "Date"],
			["create", '$L4', "Object"],
			["set", '$L4', '$L1.rfcTime1123', "x-ms-date"],
			["set", '$L4', "2015-12-11", "x-ms-version"],
			["create", '$L3', "String"],
			["string.concat", '$L3', "/", '$P0.accountName', "/", '$P2.name', "/", '$P1'],
			["create", '$L2', "String"],
			["callFunc", "signedString", '$L2', "DELETE", "", '$L4', '$L3', '$P0'],
			["string.concat", '$L2', "SharedKey ", '$P0.accountName', ":", '$L2'],
			["set", '$L4', '$L2', "Authorization"],
			["create", '$L5', "Object"],
			["string.concat", '$L0', "https://", '$P0.accountName', ".file.core.windows.net/", '$P2.name', "/", '$P1'],
			["set", '$L5.url', '$L0'],
			["set", '$L5.method', "DELETE"],
			["set", '$L5.requestHeaders', '$L4'],
			["http.requestCall", '$L6', '$L5'],
			["callFunc", "checkHttpErrors", '$P0', '$L6', "authentication", 202]
		],
		'AdvancedRequestSupporter:advancedRequest' => [
			["create", '$L0', "Object"],
			["if!=than", '$P2.appendBaseUrl', 0, 2],
			["string.concat", '$L0.url', "https://", '$P0.accountName', ".file.core.windows.net", '$P2.url'],
			["jumpRel", 1],
			["set", '$L0.url', '$P2.url'],
			["set", '$L0.requestHeaders', '$P2.headers'],
			["set", '$L0.method', '$P2.method'],
			["if!=than", '$P2.body', NULL, 1],
			["set", '$L0.requestBody', '$P2.body'],
			["if==than", '$L0.requestHeaders', NULL, 1],
			["create", '$L0.requestHeaders', "Object"],
			["if!=than", '$P2.appendAuthorization', 0, 2],
			["if==than", '$L0.requestHeaders.x-ms-date', NULL, 2],
			["create", '$L1', "Date"],
			["set", '$L0.requestHeaders.x-ms-date', '$L1.rfcTime1123'],
			["callFunc", "extractCanonicalResources", '$P0', '$L1', '$L0.url'],
			["callFunc", "signedString", '$L10', '$L0.method', "", '$L0.requestHeaders', '$L1', '$P0'],
			["string.concat", '$L0.requestHeaders.Authorization', "SharedKey ", '$P0.accountName', ":", '$L10'],
			["http.requestCall", '$L11', '$L0'],
			["if!=than", '$P2.checkErrors', 0, 1],
			["callFunc", "checkHttpErrors", '$P0', '$L11', "advancedRequest"],
			["create", '$P1', "AdvancedRequestResponse"],
			["set", '$P1.status', '$L11.code'],
			["set", '$P1.headers', '$L11.responseHeaders'],
			["set", '$P1.body', '$L11.responseBody']
		],
		'extractCanonicalResources' => [
			["string.concat", '$P1', "/", '$P0.accountName'],
			["string.indexOf", '$L0', '$P2', ".net"],
			["string.indexOf", '$L0', '$P2', "/", '$L0'],
			["if!=than", '$L0', -1, 11],
			["size", '$L1', '$P2'],
			["math.add", '$L1', '$L1', -1],
			["if!=than", '$L0', '$L1', 8],
			["string.indexOf", '$L2', '$P2', "/", '$L1'],
			["if==than", '$L2', -1, 6],
			["string.substring", '$L3', '$P2', '$L0'],
			["string.split", '$L20', '$L3', "\\?", 2],
			["string.concat", '$P1', '$P1', '$L20.0'],
			["jumpRel", 2],
			["string.substring", '$L3', '$P2', '$L0', '$L2'],
			["string.concat", '$P1', '$P1', '$L3'],
			["string.split", '$L0', '$P2', "\\?", 2],
			["size", '$L1', '$L0'],
			["if==than", '$L1', 1, 1],
			["return"],
			["string.split", '$L1', '$L0.1', "&"],
			["size", '$L2', '$L1'],
			["create", '$L3', "Number", 0],
			["if<than", '$L3', '$L2', 10],
			["get", '$L4', '$L1', '$L3'],
			["string.split", '$L5', '$L4', "="],
			["size", '$L10', '$L5'],
			["if>than", '$L10', 1, 3],
			["get", '$L11', '$L5', 0],
			["get", '$L12', '$L5', 1],
			["string.concat", '$L6', '$L11', ":", '$L12'],
			["string.concat", '$P1', '$P1', "\n", '$L6'],
			["math.add", '$L3', '$L3', 1],
			["jumpRel", -11]
		],
		'signedString' => [
			["object.getKeyArray", '$L0', '$P3'],
			["array.sort", '$L0', '$L0'],
			["size", '$L1', '$L0'],
			["create", '$L2', "String"],
			["create", '$L3', "Number"],
			["if<than", '$L3', '$L1', 7],
			["get", '$L4', '$L0', '$L3'],
			["string.indexOf", '$L5', '$L4', "x-ms"],
			["if!=than", '$L5', -1, 2],
			["get", '$L6', '$P3', '$L4'],
			["string.concat", '$L2', '$L2', '$L4', ":", '$L6', "\n"],
			["math.add", '$L3', '$L3', 1],
			["jumpRel", -8],
			["set", '$L6', ""],
			["set", '$L7', ""],
			["size", '$L5', '$P2'],
			["if!=than", '$L5', 0, 2],
			["callFunc", "generateMD5", '$L6', '$P2'],
			["getMimeType", '$L7', '$P2'],
			["string.concat", '$L1', '$P1', "\n"],
			["string.concat", '$L1', '$L1', "", "\n"],
			["string.concat", '$L1', '$L1', "", "\n"],
			["if!=than", '$P3.Content-Length', NULL, 1],
			["string.concat", '$L1', '$L1', '$P3.Content-Length'],
			["string.concat", '$L1', '$L1', "\n", '$L6', "\n"],
			["string.concat", '$L1', '$L1', '$L7', "\n"],
			["string.concat", '$L1', '$L1', "", "\n"],
			["string.concat", '$L1', '$L1', "", "\n"],
			["string.concat", '$L1', '$L1', "", "\n"],
			["string.concat", '$L1', '$L1', "", "\n"],
			["string.concat", '$L1', '$L1', "", "\n"],
			["string.concat", '$L1', '$L1', "", "\n"],
			["string.concat", '$L1', '$L1', '$L2'],
			["string.concat", '$L1', '$L1', '$P4', ""],
			["string.base64decode", '$L8', '$P5.accessKey'],
			["callFunc", "generateSHA256", '$L10', '$L1', '$L8'],
			["set", '$P0', '$L10']
		],
		'generateMD5' => [
			["hash.md5", '$L0', '$P1'],
			["size", '$L1', '$L0'],
			["set", '$L2', 0],
			["set", '$P0', ""],
			["get", '$L3', '$L0', '$L2'],
			["string.format", '$L4', "%02x", '$L3'],
			["string.concat", '$P0', '$P0', '$L4'],
			["math.add", '$L2', '$L2', 1],
			["if>=than", '$L2', '$L1', -5]
		],
		'generateSHA256' => [
			["crypt.hmac.sha256", '$L0', '$P2', '$P1'],
			["array.arrayToData", '$L1', '$L0'],
			["string.base64encode", '$P0', '$L1']
		],
		'checkHttpErrors' => [
			["if==than", '$P3', NULL, 2],
			["if>=than", '$P1.code', 400, 24],
			["jumpRel", 1],
			["if!=than", '$P1.code', '$P3', 20],
			["set", '$L0', '$P1'],
			["set", '$L2', '$L0.message'],
			["string.indexOf", '$L3', '$L2', "The specified share already exists"],
			["if!=than", '$L3', -1, 2],
			["create", '$L3', "Error", "The bucket already exists.", "IllegalArgument"],
			["throwError", '$L3'],
			["string.indexOf", '$L3', '$L2', "The specified resource does not exist"],
			["if!=than", '$L3', -1, 2],
			["create", '$L3', "Error", "The file does not exists.", "NotFound"],
			["throwError", '$L3'],
			["if==than", '$P1.code', 503, 2],
			["create", '$L3', "Error", '$L2', "ServiceUnavailable"],
			["throwError", '$L3'],
			["if==than", '$P1.code', 404, 2],
			["create", '$L3', "Error", '$L2', "NotFound"],
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
		'parseDate' => [
			["string.substr", '$L0', '$P2', 5, 2],
			["string.substr", '$L1', '$P2', 8, 3],
			["set", '$L6', ""],
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
	 * @param string $accountName
	 * @param string $accessKey
	 */
	public function __construct(string $accountName, string $accessKey)
	{
		$this->interpreterStorage = array();
		$this->instanceDependencyStorage = [];
		$this->persistentStorage = array(array());
		InitSelfTest::initTest('MicrosoftAzure');
		
		$this->interpreterStorage['accountName'] = $accountName;
		$this->interpreterStorage['accessKey'] = $accessKey;
		

		$ip = new Interpreter(new Sandbox(MicrosoftAzure::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		if (array_key_exists("init",MicrosoftAzure::$SERVICE_CODE)) {
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
		Statistics::addCall("MicrosoftAzure", "createBucket");
		$ip = new Interpreter(new Sandbox(MicrosoftAzure::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("MicrosoftAzure", "listBuckets");
		$ip = new Interpreter(new Sandbox(MicrosoftAzure::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("MicrosoftAzure", "deleteBucket");
		$ip = new Interpreter(new Sandbox(MicrosoftAzure::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("MicrosoftAzure", "deleteFile");
		$ip = new Interpreter(new Sandbox(MicrosoftAzure::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("MicrosoftAzure", "getFileMetadata");
		$ip = new Interpreter(new Sandbox(MicrosoftAzure::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("MicrosoftAzure", "listFiles");
		$ip = new Interpreter(new Sandbox(MicrosoftAzure::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("MicrosoftAzure", "listFilesWithPrefix");
		$ip = new Interpreter(new Sandbox(MicrosoftAzure::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("MicrosoftAzure", "uploadFile");
		$ip = new Interpreter(new Sandbox(MicrosoftAzure::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("MicrosoftAzure", "downloadFile");
		$ip = new Interpreter(new Sandbox(MicrosoftAzure::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("MicrosoftAzure", "advancedRequest");
		$ip = new Interpreter(new Sandbox(MicrosoftAzure::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		$ip = new Interpreter(new Sandbox(MicrosoftAzure::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		return $ip->saveAsString();
	}

	/**
	 * @param string $savedState
	 */
	public function loadAsString($savedState) {
		$sandbox = new Sandbox(MicrosoftAzure::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage);
		$ip = new Interpreter($sandbox);
		$ip->loadAsString($savedState);
		$this->persistentStorage = $sandbox->persistentStorage;
	}
}
