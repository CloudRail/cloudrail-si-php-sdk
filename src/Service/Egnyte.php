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

use CloudRail\Interfaces\CloudStorage;
use CloudRail\Type\CloudMetaData;
use CloudRail\Type\SpaceAllocation;
use CloudRail\Interfaces\AdvancedRequestSupporter;
use CloudRail\Type\AdvancedRequestSpecification;
use CloudRail\Type\AdvancedRequestResponse;
use CloudRail\Type\CloudRailError;

class Egnyte implements CloudStorage, AdvancedRequestSupporter
{
	private static $SERVICE_CODE = [
		'init' => [
			["if==than", '$P0.scopes', NULL, 2],
			["set", '$P0.scope', "Egnyte.filesystem%20Egnyte.link"],
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
		'CloudStorage:getUserLogin' => [
			["callFunc", "User:about", '$P0'],
			["set", '$P1', '$P0.userInfo.email']
		],
		'CloudStorage:getUserName' => [
			["callFunc", "User:about", '$P0'],
			["set", '$P1', '$P0.userInfo.displayName']
		],
		'User:about' => [
			["if!=than", '$P0.userInfo', NULL, 4],
			["create", '$L0', "Date"],
			["math.add", '$L0', '$L0.Time', -1000],
			["if>than", '$P0.userInfo.lastUpdate', '$L0', 1],
			["return"],
			["callFunc", "User:aboutRequest", '$P0']
		],
		'User:aboutRequest' => [
			["callFunc", "checkAuthentication", '$P0'],
			["create", '$L0', "Object"],
			["string.concat", '$L0.url', "https://", '$P0.domain', ".egnyte.com/pubapi/v1/userinfo"],
			["create", '$L0.requestHeaders', "Object"],
			["string.concat", '$L0.requestHeaders.Authorization', "Bearer ", '$S0.access_token'],
			["set", '$L0.method', "GET"],
			["http.requestCall", '$L1', '$L0'],
			["json.parse", '$L2', '$L1.responseBody'],
			["callFunc", "validateResponse", '$P0', '$L1'],
			["create", '$P0.userInfo', "Object"],
			["create", '$L3', "Date"],
			["set", '$P0.userInfo.lastUpdate', '$L3.Time'],
			["set", '$P0.userInfo.displayName', '$L2.username'],
			["set", '$P0.userInfo.email', '$L2.email']
		],
		'CloudStorage:download' => [
			["callFunc", "validatePath", '$P0', '$P2'],
			["callFunc", "encodePath", '$P0', '$L12', '$P2'],
			["callFunc", "checkAuthentication", '$P0'],
			["create", '$L0', "Object"],
			["string.concat", '$L0.url', "https://", '$P0.domain', ".egnyte.com/pubapi/v1/fs-content", '$L12'],
			["set", '$L0.method', "GET"],
			["create", '$L0.requestHeaders', "Object"],
			["string.concat", '$L0.requestHeaders.Authorization', "Bearer ", '$S0.access_token'],
			["http.requestCall", '$L5', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L5'],
			["set", '$P1', '$L5.responseBody']
		],
		'CloudStorage:upload' => [
			["callFunc", "validatePath", '$P0', '$P1'],
			["callFunc", "encodePath", '$P0', '$L11', '$P1'],
			["callFunc", "checkNull", '$P0', '$P2'],
			["callFunc", "checkPositive", '$P0', '$P3'],
			["callFunc", "checkAuthentication", '$P0'],
			["if==than", '$P4', 0, 1],
			["callFunc", "checkFileNotExists", '$P0', '$P1'],
			["callFunc", "checkParentPathExists", '$P0', '$P1'],
			["create", '$L0', "Object"],
			["string.concat", '$L0.url', "https://", '$P0.domain', ".egnyte.com/pubapi/v1/fs-content", '$L11'],
			["set", '$L0.method', "POST"],
			["create", '$L0.requestHeaders', "Object"],
			["string.concat", '$L0.requestHeaders.Authorization', "Bearer ", '$S0.access_token'],
			["string.concat", '$L0.requestHeaders.Content-Length', '$P3', ""],
			["set", '$L0.requestHeaders.Content-Type', "text/plain"],
			["if!=than", '$P5', NULL, 1],
			["set", '$L0.requestHeaders.Last-Modified', '$P5'],
			["set", '$L0.requestBody', '$P2'],
			["http.requestCall", '$L5', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L5']
		],
		'CloudStorage:move' => [
			["callFunc", "validatePath", '$P0', '$P1'],
			["callFunc", "encodePath", '$P0', '$L11', '$P1'],
			["callFunc", "validatePath", '$P0', '$P2'],
			["callFunc", "checkAuthentication", '$P0'],
			["callFunc", "checkParentPathExists", '$P0', '$P2'],
			["create", '$L0', "Object"],
			["string.concat", '$L0.url', "https://", '$P0.domain', ".egnyte.com/pubapi/v1/fs", '$L11'],
			["set", '$L0.method', "POST"],
			["create", '$L0.requestHeaders', "Object"],
			["string.concat", '$L0.requestHeaders.Authorization', "Bearer ", '$S0.access_token'],
			["set", '$L0.requestHeaders.Content-Type', "application/json"],
			["create", '$L1', "Object"],
			["set", '$L1.action', "move"],
			["set", '$L1.destination', '$P2'],
			["json.stringify", '$L1', '$L1'],
			["stream.stringToStream", '$L0.requestBody', '$L1'],
			["http.requestCall", '$L5', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L5']
		],
		'CloudStorage:delete' => [
			["callFunc", "validatePath", '$P0', '$P1'],
			["callFunc", "encodePath", '$P0', '$L11', '$P1'],
			["callFunc", "checkAuthentication", '$P0'],
			["create", '$L0', "Object"],
			["string.concat", '$L0.url', "https://", '$P0.domain', ".egnyte.com/pubapi/v1/fs", '$L11'],
			["set", '$L0.method', "DELETE"],
			["create", '$L0.requestHeaders', "Object"],
			["string.concat", '$L0.requestHeaders.Authorization', "Bearer ", '$S0.access_token'],
			["http.requestCall", '$L5', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L5']
		],
		'CloudStorage:copy' => [
			["callFunc", "validatePath", '$P0', '$P1'],
			["callFunc", "encodePath", '$P0', '$L11', '$P1'],
			["callFunc", "validatePath", '$P0', '$P2'],
			["callFunc", "checkAuthentication", '$P0'],
			["callFunc", "checkParentPathExists", '$P0', '$P2'],
			["create", '$L0', "Object"],
			["string.concat", '$L0.url', "https://", '$P0.domain', ".egnyte.com/pubapi/v1/fs", '$L11'],
			["set", '$L0.method', "POST"],
			["create", '$L0.requestHeaders', "Object"],
			["string.concat", '$L0.requestHeaders.Authorization', "Bearer ", '$S0.access_token'],
			["set", '$L0.requestHeaders.Content-Type', "application/json"],
			["create", '$L1', "Object"],
			["set", '$L1.action', "copy"],
			["set", '$L1.destination', '$P2'],
			["json.stringify", '$L1', '$L1'],
			["stream.stringToStream", '$L0.requestBody', '$L1'],
			["http.requestCall", '$L5', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L5']
		],
		'CloudStorage:createFolder' => [
			["callFunc", "validatePath", '$P0', '$P1'],
			["callFunc", "encodePath", '$P0', '$L11', '$P1'],
			["callFunc", "checkAuthentication", '$P0'],
			["callFunc", "checkParentPathExists", '$P0', '$P1'],
			["create", '$L0', "Object"],
			["string.concat", '$L0.url', "https://", '$P0.domain', ".egnyte.com/pubapi/v1/fs", '$L11'],
			["set", '$L0.method', "POST"],
			["create", '$L0.requestHeaders', "Object"],
			["string.concat", '$L0.requestHeaders.Authorization', "Bearer ", '$S0.access_token'],
			["set", '$L0.requestHeaders.Content-Type', "application/json"],
			["create", '$L1', "Object"],
			["set", '$L1.action', "add_folder"],
			["json.stringify", '$L1', '$L1'],
			["stream.stringToStream", '$L0.requestBody', '$L1'],
			["http.requestCall", '$L5', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L5']
		],
		'CloudStorage:getMetadata' => [
			["callFunc", "validatePath", '$P0', '$P2'],
			["callFunc", "checkAuthentication", '$P0'],
			["callFunc", "encodePath", '$P0', '$L12', '$P2'],
			["create", '$L2', "Object"],
			["string.concat", '$L2.url', "https://", '$P0.domain', ".egnyte.com/pubapi/v1/fs", '$L12', "?list_content=false"],
			["set", '$L2.method', "GET"],
			["create", '$L2.requestHeaders', "Object"],
			["string.concat", '$L2.requestHeaders.Authorization', "Bearer ", '$S0.access_token'],
			["http.requestCall", '$L3', '$L2'],
			["callFunc", "validateResponse", '$P0', '$L3'],
			["json.parse", '$L4', '$L3.responseBody'],
			["callFunc", "extractMeta", '$P0', '$P1', '$L4']
		],
		'CloudStorage:getChildren' => [
			["callFunc", "validatePath", '$P0', '$P2'],
			["callFunc", "checkAuthentication", '$P0'],
			["callFunc", "encodePath", '$P0', '$L12', '$P2'],
			["create", '$L2', "Object"],
			["string.concat", '$L2.url', "https://", '$P0.domain', ".egnyte.com/pubapi/v1/fs", '$L12'],
			["set", '$L2.method', "GET"],
			["create", '$L2.requestHeaders', "Object"],
			["string.concat", '$L2.requestHeaders.Authorization', "Bearer ", '$S0.access_token'],
			["http.requestCall", '$L3', '$L2'],
			["callFunc", "validateResponse", '$P0', '$L3'],
			["json.parse", '$L4', '$L3.responseBody'],
			["if==than", '$L4.is_folder', 0, 2],
			["create", '$L5', "Error", "Only folders have children, the given path points to a file", "IllegalArgument"],
			["throwError", '$L5'],
			["create", '$P1', "Array"],
			["if!=than", '$L4.folders', NULL, 8],
			["size", '$L5', '$L4.folders'],
			["set", '$L6', 0],
			["if<than", '$L6', '$L5', 5],
			["get", '$L7', '$L4.folders', '$L6'],
			["callFunc", "extractMeta", '$P0', '$L8', '$L7'],
			["push", '$P1', '$L8'],
			["math.add", '$L6', '$L6', 1],
			["jumpRel", -6],
			["if!=than", '$L4.files', NULL, 8],
			["size", '$L5', '$L4.files'],
			["set", '$L6', 0],
			["if<than", '$L6', '$L5', 5],
			["get", '$L7', '$L4.files', '$L6'],
			["callFunc", "extractMeta", '$P0', '$L8', '$L7'],
			["push", '$P1', '$L8'],
			["math.add", '$L6', '$L6', 1],
			["jumpRel", -6]
		],
		'getChildrenPage' => [
			["callFunc", "validatePath", '$P0', '$P2'],
			["callFunc", "checkAuthentication", '$P0'],
			["callFunc", "encodePath", '$P0', '$L12', '$P2'],
			["create", '$L2', "Object"],
			["string.concat", '$L2.url', "https://", '$P0.domain', ".egnyte.com/pubapi/v1/fs", '$L12', "?offset=", '$P3', "&count=", '$P4'],
			["set", '$L2.method', "GET"],
			["create", '$L2.requestHeaders', "Object"],
			["string.concat", '$L2.requestHeaders.Authorization', "Bearer ", '$S0.access_token'],
			["http.requestCall", '$L3', '$L2'],
			["callFunc", "validateResponse", '$P0', '$L3'],
			["json.parse", '$L4', '$L3.responseBody'],
			["if==than", '$L4.is_folder', 0, 2],
			["create", '$L5', "Error", "Only folders have children, the given path points to a file", "IllegalArgument"],
			["throwError", '$L5'],
			["create", '$P1', "Array"],
			["if!=than", '$L4.folders', NULL, 8],
			["size", '$L5', '$L4.folders'],
			["set", '$L6', 0],
			["if<than", '$L6', '$L5', 5],
			["get", '$L7', '$L4.folders', '$L6'],
			["callFunc", "extractMeta", '$P0', '$L8', '$L7'],
			["push", '$P1', '$L8'],
			["math.add", '$L6', '$L6', 1],
			["jumpRel", -6],
			["if!=than", '$L4.files', NULL, 8],
			["size", '$L5', '$L4.files'],
			["set", '$L6', 0],
			["if<than", '$L6', '$L5', 5],
			["get", '$L7', '$L4.files', '$L6'],
			["callFunc", "extractMeta", '$P0', '$L8', '$L7'],
			["push", '$P1', '$L8'],
			["math.add", '$L6', '$L6', 1],
			["jumpRel", -6]
		],
		'CloudStorage:exists' => [
			["callFunc", "validatePath", '$P0', '$P2'],
			["callFunc", "checkAuthentication", '$P0'],
			["callFunc", "encodePath", '$P0', '$L12', '$P2'],
			["create", '$L2', "Object"],
			["string.concat", '$L2.url', "https://", '$P0.domain', ".egnyte.com/pubapi/v1/fs", '$L12', "?list_content=false&list_custom_metadata=false"],
			["set", '$L2.method', "GET"],
			["create", '$L2.requestHeaders', "Object"],
			["string.concat", '$L2.requestHeaders.Authorization', "Bearer ", '$S0.access_token'],
			["http.requestCall", '$L4', '$L2'],
			["if==than", '$L4.code', 200, 2],
			["set", '$P1', 1],
			["return"],
			["set", '$P1', 0]
		],
		'Authenticating:login' => [
			["callFunc", "checkAuthentication", '$P0']
		],
		'Authenticating:logout' => [
			["set", '$S0.access_token', NULL]
		],
		'CloudStorage:getAllocation' => [
		],
		'CloudStorage:createShareLink' => [
			["callFunc", "CloudStorage:getMetadata", '$P0', '$L0', '$P2'],
			["if==than", '$L0.folder', 0, 1],
			["set", '$L1', "file"],
			["if!=than", '$L0.folder', 0, 1],
			["set", '$L1', "folder"],
			["create", '$L2', "Object"],
			["string.concat", '$L2.url', "https://", '$P0.domain', ".egnyte.com/pubapi/v1/links"],
			["set", '$L2.method', "POST"],
			["create", '$L2.requestHeaders', "Object"],
			["string.concat", '$L2.requestHeaders.Authorization', "Bearer ", '$S0.access_token'],
			["set", '$L2.requestHeaders.Content-Type', "application/json"],
			["create", '$L3', "Object"],
			["set", '$L3.path', '$P2'],
			["set", '$L3.type', '$L1'],
			["set", '$L3.accessibility', "anyone"],
			["json.stringify", '$L3', '$L3'],
			["stream.stringToStream", '$L2.requestBody', '$L3'],
			["http.requestCall", '$L4', '$L2'],
			["callFunc", "validateResponse", '$P0', '$L4'],
			["json.parse", '$L5', '$L4.responseBody'],
			["get", '$P1', '$L5', "links", 0, "url"]
		],
		'CloudStorage:getThumbnail' => [
		],
		'CloudStorage:searchFiles' => [
			["callFunc", "checkNull", '$P0', '$P2'],
			["if==than", '$P2', "", 2],
			["create", '$L0', "Error", "The query is not allowed to be empty.", "IllegalArgument"],
			["throwError", '$L0'],
			["callFunc", "checkAuthentication", '$P0'],
			["create", '$L0', "Object"],
			["set", '$L0.method', "GET"],
			["string.concat", '$L0.url', "https://", '$P0.domain', ".egnyte.com/pubapi/v1/search"],
			["create", '$L1', "Object"],
			["set", '$L0.requestHeaders', '$L1'],
			["string.concat", '$L1.Authorization', "Bearer ", '$S0.access_token'],
			["string.urlEncode", '$L2', '$P2'],
			["string.concat", '$L0.url', '$L0.url', "?query=", '$L2', "&count=100"],
			["http.requestCall", '$L1', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L1'],
			["json.parse", '$L2', '$L1.responseBody'],
			["create", '$P1', "Array"],
			["create", '$L3', "Number"],
			["size", '$L4', '$L2.results'],
			["if<than", '$L3', '$L4', 5],
			["get", '$L5', '$L2.results', '$L3'],
			["callFunc", "extractMeta", '$P0', '$L6', '$L5'],
			["push", '$P1', '$L6'],
			["math.add", '$L3', '$L3', 1],
			["jumpRel", -6]
		],
		'AdvancedRequestSupporter:advancedRequest' => [
			["create", '$L0', "Object"],
			["create", '$L0.url', "String"],
			["if!=than", '$P2.appendBaseUrl', 0, 1],
			["string.concat", '$L0.url', "https://", '$P0.domain', ".egnyte.com"],
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
		'checkAuthentication' => [
			["if!=than", NULL, '$S0.access_token', 1],
			["return"],
			["string.concat", '$L0', "https://", '$P0.domain', ".egnyte.com/puboauth/token?response_type=code&scope=", '$P0.scope', "&redirect_uri=", '$P0.redirectUri', "&client_id=", '$P0.clientId', "&state=", '$P0.state'],
			["awaitCodeRedirect", '$L1', '$L0', NULL, '$P0.redirectUri'],
			["create", '$L2', "Object"],
			["string.concat", '$L2.url', "https://", '$P0.domain', ".egnyte.com/puboauth/token"],
			["set", '$L2.method', "POST"],
			["create", '$L7', "Object"],
			["set", '$L7.Content-Type', "application/x-www-form-urlencoded"],
			["set", '$L2.requestHeaders', '$L7'],
			["string.concat", '$L3', "code=", '$L1', "&grant_type=authorization_code", "&redirect_uri=", '$P0.redirectUri', "&client_id=", '$P0.clientId', "&client_secret=", '$P0.clientSecret'],
			["stream.stringToStream", '$L4', '$L3'],
			["set", '$L2.requestBody', '$L4'],
			["http.requestCall", '$L5', '$L2'],
			["callFunc", "validateResponse", '$P0', '$L5'],
			["json.parse", '$L6', '$L5.responseBody'],
			["set", '$S0.access_token', '$L6.access_token']
		],
		'encodePath' => [
			["string.split", '$L0', '$P2', "/"],
			["set", '$L1', 1],
			["size", '$L2', '$L0'],
			["set", '$P1', ""],
			["if<than", '$L1', '$L2', 6],
			["get", '$L4', '$L0', '$L1'],
			["string.urlEncode", '$L4', '$L4'],
			["callFunc", "replacePluses", '$P0', '$L4'],
			["string.concat", '$P1', '$P1', "/", '$L4'],
			["math.add", '$L1', '$L1', 1],
			["jumpRel", -7]
		],
		'replacePluses' => [
			["string.split", '$L0', '$P1', "\\+"],
			["set", '$L1', 1],
			["size", '$L2', '$L0'],
			["get", '$P1', '$L0', 0],
			["if<than", '$L1', '$L2', 4],
			["get", '$L3', '$L0', '$L1'],
			["string.concat", '$P1', '$P1', "%20", '$L3'],
			["math.add", '$L1', '$L1', 1],
			["jumpRel", -5]
		],
		'validatePath' => [
			["if==than", '$P1', NULL, 2],
			["create", '$L0', "Error", "Path shouldn't be null", "IllegalArgument"],
			["throwError", '$L0'],
			["if==than", '$P1', "", 2],
			["create", '$L0', "Error", "Path should start with '/'.", "IllegalArgument"],
			["throwError", '$L0'],
			["create", '$L0', "String"],
			["string.substr", '$L0', '$P1', 0, 1],
			["if!=than", '$L0', "/", 2],
			["create", '$L0', "Error", "Path should start with '/'.", "IllegalArgument"],
			["throwError", '$L0'],
			["create", '$L1', "Number"],
			["size", '$L1', '$P1'],
			["math.add", '$L1', '$L1', -1],
			["if!=than", '$L1', 0, 5],
			["create", '$L2', "String"],
			["string.substr", '$L2', '$P1', '$L1', 1],
			["if==than", '$L2', "/", 2],
			["create", '$L3', "Error", "Path should not end with '/'.", "IllegalArgument"],
			["throwError", '$L3']
		],
		'checkNull' => [
			["if==than", '$P1', NULL, 2],
			["create", '$L0', "Error", "Passed argument is null.", "IllegalArgument"],
			["throwError", '$L0']
		],
		'checkPositive' => [
			["if<than", '$P1', 0, 2],
			["create", '$L0', "Error", "Passed argument should be bigger than 0.", "IllegalArgument"],
			["throwError", '$L0']
		],
		'validateResponse' => [
			["if==than", '$P1.code', 401, 2],
			["create", '$L0', "Error", "Authentication failed", "Authentication"],
			["throwError", '$L0'],
			["if==than", '$P1.code', 403, 2],
			["create", '$L0', "Error", "Not enough permissions or rate limit exceeded", "Authentication"],
			["throwError", '$L0'],
			["if==than", '$P1.code', 404, 2],
			["create", '$L0', "Error", "Resource not found", "NotFound"],
			["throwError", '$L0'],
			["if==than", '$P1.code', 409, 2],
			["create", '$L0', "Error", "Conflicting location", "IllegalArgument"],
			["throwError", '$L0'],
			["if>=than", '$P1.code', 400, 3],
			["stream.streamToString", '$L1', '$P1.responseBody'],
			["create", '$L0', "Error", '$L1', "Http"],
			["throwError", '$L0']
		],
		'checkParentPathExists' => [
			["string.lastIndexOf", '$L0', '$P1', "/"],
			["string.substring", '$L1', '$P1', 0, '$L0'],
			["if==than", '$L1', "", 1],
			["return"],
			["callFunc", "encodePath", '$P0', '$L11', '$L1'],
			["create", '$L2', "Object"],
			["string.concat", '$L2.url', "https://", '$P0.domain', ".egnyte.com/pubapi/v1/fs", '$L11', "?list_content=false&list_custom_metadata=false"],
			["set", '$L2.method', "GET"],
			["create", '$L2.requestHeaders', "Object"],
			["string.concat", '$L2.requestHeaders.Authorization', "Bearer ", '$S0.access_token'],
			["http.requestCall", '$L4', '$L2'],
			["if!=than", '$L4.code', 200, 2],
			["create", '$L5', "Error", "Target folder not found.", "NotFound"],
			["throwError", '$L5']
		],
		'checkFileNotExists' => [
			["callFunc", "encodePath", '$P0', '$L11', '$P1'],
			["create", '$L2', "Object"],
			["string.concat", '$L2.url', "https://", '$P0.domain', ".egnyte.com/pubapi/v1/fs", '$L11', "?list_content=false&list_custom_metadata=false"],
			["set", '$L2.method', "GET"],
			["create", '$L2.requestHeaders', "Object"],
			["string.concat", '$L2.requestHeaders.Authorization', "Bearer ", '$S0.access_token'],
			["http.requestCall", '$L4', '$L2'],
			["if==than", '$L4.code', 200, 2],
			["create", '$L5', "Error", "File already exists.", "Http"],
			["throwError", '$L5']
		],
		'extractMeta' => [
			["create", '$P1', "CloudMetaData"],
			["set", '$P1.name', '$P2.name'],
			["set", '$P1.path', '$P2.path'],
			["if==than", '$P2.is_folder', 0, 5],
			["set", '$P1.size', '$P2.size'],
			["set", '$P1.folder', 0],
			["callFunc", "parseDate", '$P0', '$P1.modifiedAt', '$P2.last_modified'],
			["set", '$P1.contentModifiedAt', '$P1.modifiedAt'],
			["return"],
			["set", '$P1.folder', 1],
			["set", '$P1.modifiedAt', '$P2.lastModified']
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
		'CloudStorage:uploadWithContentModifiedDate' => [
			["callFunc", "checkNull", '$P0', '$P2', '$P5'],
			["create", '$L0', "Date"],
			["set", '$L0.time', '$P5'],
			["set", '$L1', '$L0.rfcTime1123'],
			["callFunc", "CloudStorage:upload", '$P0', '$P1', '$P2', '$P3', '$P4', '$L1']
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
	 * @param string $domain
	 * @param string $clientId
	 * @param string $clientSecret
	 * @param string $redirectUri
	 * @param string $state
	 * @param array $scopes
	 */
	public function __construct(callable $redirectReceiver, string $domain, string $clientId, string $clientSecret, string $redirectUri, string $state, array $scopes=null)
	{
		$this->interpreterStorage = array();
		$this->instanceDependencyStorage = ["redirectReceiver" => $redirectReceiver];
		$this->persistentStorage = array(array());
		InitSelfTest::initTest('Egnyte');
		
		$this->interpreterStorage['domain'] = $domain;
		$this->interpreterStorage['clientId'] = $clientId;
		$this->interpreterStorage['clientSecret'] = $clientSecret;
		$this->interpreterStorage['redirectUri'] = $redirectUri;
		$this->interpreterStorage['state'] = $state;
		$this->interpreterStorage['scopes'] = $scopes;
		

		$ip = new Interpreter(new Sandbox(Egnyte::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		if (array_key_exists("init",Egnyte::$SERVICE_CODE)) {
			$parameters = [&$this->interpreterStorage];
		  $ip->callFunctionSync("init",$parameters );
		}
	}

	
	/**
	 * @param string $filePath
	 * @throws IllegalArgumentError
	 * @throws AuthenticationError
	 * @throws NotFoundError
	 * @throws HttpError
	 * @throws ServiceUnavailableError
	 * @throws \Exception
	 * @return resource
	 */
	public function download(string $filePath) {
		Statistics::addCall("Egnyte", "download");
		$ip = new Interpreter(new Sandbox(Egnyte::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null, $filePath];
		$ip->callFunctionSync('CloudStorage:download', $auxArray);
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
	 * @param string $filePath
	 * @param resource $stream
	 * @param int $size
	 * @param bool $overwrite
	 * @throws IllegalArgumentError
	 * @throws AuthenticationError
	 * @throws NotFoundError
	 * @throws HttpError
	 * @throws ServiceUnavailableError
	 * @throws \Exception
	 */
	public function upload(string $filePath,  $stream, int $size, bool $overwrite):void {
		Statistics::addCall("Egnyte", "upload");
		$ip = new Interpreter(new Sandbox(Egnyte::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, $filePath, $stream, $size, $overwrite];
		$ip->callFunctionSync('CloudStorage:upload', $auxArray);
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
	 * @param string $sourcePath
	 * @param string $destinationPath
	 * @throws IllegalArgumentError
	 * @throws AuthenticationError
	 * @throws NotFoundError
	 * @throws HttpError
	 * @throws ServiceUnavailableError
	 * @throws \Exception
	 */
	public function move(string $sourcePath, string $destinationPath):void {
		Statistics::addCall("Egnyte", "move");
		$ip = new Interpreter(new Sandbox(Egnyte::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, $sourcePath, $destinationPath];
		$ip->callFunctionSync('CloudStorage:move', $auxArray);
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
	 * @param string $filePath
	 * @throws IllegalArgumentError
	 * @throws AuthenticationError
	 * @throws NotFoundError
	 * @throws HttpError
	 * @throws ServiceUnavailableError
	 * @throws \Exception
	 */
	public function delete(string $filePath):void {
		Statistics::addCall("Egnyte", "delete");
		$ip = new Interpreter(new Sandbox(Egnyte::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, $filePath];
		$ip->callFunctionSync('CloudStorage:delete', $auxArray);
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
	 * @param string $sourcePath
	 * @param string $destinationPath
	 * @throws IllegalArgumentError
	 * @throws AuthenticationError
	 * @throws NotFoundError
	 * @throws HttpError
	 * @throws ServiceUnavailableError
	 * @throws \Exception
	 */
	public function copy(string $sourcePath, string $destinationPath):void {
		Statistics::addCall("Egnyte", "copy");
		$ip = new Interpreter(new Sandbox(Egnyte::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, $sourcePath, $destinationPath];
		$ip->callFunctionSync('CloudStorage:copy', $auxArray);
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
	 * @param string $folderPath
	 * @throws IllegalArgumentError
	 * @throws AuthenticationError
	 * @throws NotFoundError
	 * @throws HttpError
	 * @throws ServiceUnavailableError
	 * @throws \Exception
	 */
	public function createFolder(string $folderPath):void {
		Statistics::addCall("Egnyte", "createFolder");
		$ip = new Interpreter(new Sandbox(Egnyte::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, $folderPath];
		$ip->callFunctionSync('CloudStorage:createFolder', $auxArray);
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
	 * @param string $filePath
	 * @throws IllegalArgumentError
	 * @throws AuthenticationError
	 * @throws NotFoundError
	 * @throws HttpError
	 * @throws ServiceUnavailableError
	 * @throws \Exception
	 * @return CloudMetaData
	 */
	public function getMetadata(string $filePath):CloudMetaData {
		Statistics::addCall("Egnyte", "getMetadata");
		$ip = new Interpreter(new Sandbox(Egnyte::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null, $filePath];
		$ip->callFunctionSync('CloudStorage:getMetadata', $auxArray);
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
	 * @param string $folderPath
	 * @throws IllegalArgumentError
	 * @throws AuthenticationError
	 * @throws NotFoundError
	 * @throws HttpError
	 * @throws ServiceUnavailableError
	 * @throws \Exception
	 * @return array
	 */
	public function getChildren(string $folderPath):array {
		Statistics::addCall("Egnyte", "getChildren");
		$ip = new Interpreter(new Sandbox(Egnyte::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null, $folderPath];
		$ip->callFunctionSync('CloudStorage:getChildren', $auxArray);
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
	 * @param string $path
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
	public function getChildrenPage(string $path, int $offset, int $limit):array {
		Statistics::addCall("Egnyte", "getChildrenPage");
		$ip = new Interpreter(new Sandbox(Egnyte::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null, $path, $offset, $limit];
		$ip->callFunctionSync('getChildrenPage', $auxArray);
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
	public function getUserLogin():string {
		Statistics::addCall("Egnyte", "getUserLogin");
		$ip = new Interpreter(new Sandbox(Egnyte::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null];
		$ip->callFunctionSync('CloudStorage:getUserLogin', $auxArray);
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
	public function getUserName():string {
		Statistics::addCall("Egnyte", "getUserName");
		$ip = new Interpreter(new Sandbox(Egnyte::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null];
		$ip->callFunctionSync('CloudStorage:getUserName', $auxArray);
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
	 * @param string $path
	 * @throws IllegalArgumentError
	 * @throws AuthenticationError
	 * @throws NotFoundError
	 * @throws HttpError
	 * @throws ServiceUnavailableError
	 * @throws \Exception
	 * @return string
	 */
	public function createShareLink(string $path):string {
		Statistics::addCall("Egnyte", "createShareLink");
		$ip = new Interpreter(new Sandbox(Egnyte::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null, $path];
		$ip->callFunctionSync('CloudStorage:createShareLink', $auxArray);
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
	 * @return SpaceAllocation
	 */
	public function getAllocation():SpaceAllocation {
		Statistics::addCall("Egnyte", "getAllocation");
		$ip = new Interpreter(new Sandbox(Egnyte::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null];
		$ip->callFunctionSync('CloudStorage:getAllocation', $auxArray);
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
	 * @param string $path
	 * @throws IllegalArgumentError
	 * @throws AuthenticationError
	 * @throws NotFoundError
	 * @throws HttpError
	 * @throws ServiceUnavailableError
	 * @throws \Exception
	 * @return bool
	 */
	public function exists(string $path):bool {
		Statistics::addCall("Egnyte", "exists");
		$ip = new Interpreter(new Sandbox(Egnyte::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null, $path];
		$ip->callFunctionSync('CloudStorage:exists', $auxArray);
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
	 * @param string $path
	 * @throws IllegalArgumentError
	 * @throws AuthenticationError
	 * @throws NotFoundError
	 * @throws HttpError
	 * @throws ServiceUnavailableError
	 * @throws \Exception
	 * @return resource
	 */
	public function getThumbnail(string $path) {
		Statistics::addCall("Egnyte", "getThumbnail");
		$ip = new Interpreter(new Sandbox(Egnyte::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null, $path];
		$ip->callFunctionSync('CloudStorage:getThumbnail', $auxArray);
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
	 * @throws IllegalArgumentError
	 * @throws AuthenticationError
	 * @throws NotFoundError
	 * @throws HttpError
	 * @throws ServiceUnavailableError
	 * @throws \Exception
	 * @return array
	 */
	public function search(string $query):array {
		Statistics::addCall("Egnyte", "search");
		$ip = new Interpreter(new Sandbox(Egnyte::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null, $query];
		$ip->callFunctionSync('CloudStorage:searchFiles', $auxArray);
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
		Statistics::addCall("Egnyte", "login");
		$ip = new Interpreter(new Sandbox(Egnyte::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Egnyte", "logout");
		$ip = new Interpreter(new Sandbox(Egnyte::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
	 * @param string $filePath
	 * @param resource $stream
	 * @param int $size
	 * @param bool $overwrite
	 * @param int $contentModifiedDate
	 * @throws IllegalArgumentError
	 * @throws AuthenticationError
	 * @throws NotFoundError
	 * @throws HttpError
	 * @throws ServiceUnavailableError
	 * @throws \Exception
	 */
	public function uploadWithContentModifiedDate(string $filePath,  $stream, int $size, bool $overwrite, int $contentModifiedDate):void {
		Statistics::addCall("Egnyte", "uploadWithContentModifiedDate");
		$ip = new Interpreter(new Sandbox(Egnyte::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, $filePath, $stream, $size, $overwrite, $contentModifiedDate];
		$ip->callFunctionSync('CloudStorage:uploadWithContentModifiedDate', $auxArray);
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
		Statistics::addCall("Egnyte", "advancedRequest");
		$ip = new Interpreter(new Sandbox(Egnyte::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		$ip = new Interpreter(new Sandbox(Egnyte::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		return $ip->saveAsString();
	}

	/**
	 * @param string $savedState
	 */
	public function loadAsString($savedState) {
		$sandbox = new Sandbox(Egnyte::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage);
		$ip = new Interpreter($sandbox);
		$ip->loadAsString($savedState);
		$this->persistentStorage = $sandbox->persistentStorage;
	}
}
