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

class Box implements CloudStorage, AdvancedRequestSupporter
{
	private static $SERVICE_CODE = [
		'CloudStorage:getUserLogin' => [
			["callFunc", "User:about", '$P0'],
			["set", '$P1', '$P0.userInfo.emailAddress']
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
			["string.concat", '$L0.url', "https://api.box.com/2.0/users/me"],
			["create", '$L0.requestHeaders', "Object"],
			["string.concat", '$L0.requestHeaders.Authorization', "Bearer ", '$S0.access_token'],
			["set", '$L0.method', "GET"],
			["http.requestCall", '$L2', '$L0'],
			["callFunc", "checkHttpErrors", '$P0', '$L2', "user about", 200],
			["json.parse", '$L3', '$L2.responseBody'],
			["create", '$P0.userInfo', "Object"],
			["create", '$L4', "Date"],
			["set", '$P0.userInfo.lastUpdate', '$L4.Time'],
			["set", '$P0.userInfo.emailAddress', '$L3.login'],
			["set", '$P0.userInfo.displayName', '$L3.name']
		],
		'CloudStorage:download' => [
			["callFunc", "validatePath", '$P0', '$P2'],
			["callFunc", "checkAuthentication", '$P0'],
			["callFunc", "resolvePath", '$P0', '$L0', '$P2'],
			["create", '$L2', "Object"],
			["string.concat", '$L3', "https://api.box.com/2.0/files/", '$L0.id', "/content"],
			["set", '$L2.url', '$L3'],
			["set", '$L2.method', "GET"],
			["create", '$L2.requestHeaders', "Object"],
			["string.concat", '$L2.requestHeaders.Authorization', "Bearer ", '$S0.access_token'],
			["http.requestCall", '$L5', '$L2'],
			["callFunc", "checkHttpErrors", '$P0', '$L5', "download request", 200],
			["set", '$P1', '$L5.responseBody']
		],
		'CloudStorage:upload' => [
			["callFunc", "validatePath", '$P0', '$P1'],
			["callFunc", "checkNull", '$P0', '$P2'],
			["callFunc", "checkPositive", '$P0', '$P3'],
			["callFunc", "checkAuthentication", '$P0'],
			["if!=than", '$P4', 0, 7],
			["callFunc", "resolvePath", '$P0', '$L20', '$P1', 1],
			["if!=than", '$L20', NULL, 5],
			["if!=than", '$P5', NULL, 2],
			["callFunc", "uploadOverwrite", '$P0', '$P1', '$P2', '$L20', '$P5'],
			["return"],
			["callFunc", "uploadOverwrite", '$P0', '$P1', '$P2', '$L20'],
			["return"],
			["string.lastIndexOf", '$L0', '$P1', "/"],
			["math.add", '$L1', '$L0', 1],
			["string.substring", '$L2', '$P1', '$L1'],
			["string.substring", '$L3', '$P1', 0, '$L0'],
			["if==than", '$L3', "", 1],
			["set", '$L3', "/"],
			["callFunc", "resolvePath", '$P0', '$L4', '$L3'],
			["create", '$L5', "Object"],
			["set", '$L5.name', '$L2'],
			["create", '$L5.parent', "Object"],
			["set", '$L5.parent.id', '$L4.id'],
			["if!=than", '$P5', NULL, 1],
			["set", '$L5.content_modified_at', '$P5'],
			["json.stringify", '$L6', '$L5'],
			["string.lastIndexOf", '$L7', '$L2', "."],
			["math.add", '$L7', '$L7', 1],
			["string.substring", '$L7', '$L2', '$L7'],
			["set", '$L14', '$L7'],
			["getMimeType", '$L7', '$L7'],
			["if==than", '$L7', NULL, 2],
			["debug.out", "No MIME type could be guessed for file extension '", '$L14', "', using 'application/octet-stream' as default"],
			["set", '$L7', "application/octet-stream"],
			["set", '$L8', "72365asfa72138jas5685oksco05"],
			["string.concat", '$L9', "--", '$L8', "\r\n", "Content-Disposition: form-data; name=\"attributes\"", "\r\n\r\n", '$L6', "\r\n", "--", '$L8', "\r\n", "Content-Disposition: form-data; name=\"file\"; filename=\"someName\"", "\r\n", "Content-Type: ", '$L7', "\r\n\r\n"],
			["string.concat", '$L10', "\r\n--", '$L8', "--\r\n"],
			["stream.stringToStream", '$L9', '$L9'],
			["stream.stringToStream", '$L10', '$L10'],
			["stream.makeJoinedStream", '$L11', '$L9', '$P2', '$L10'],
			["create", '$L12', "Object"],
			["set", '$L12.url', "https://upload.box.com/api/2.0/files/content"],
			["set", '$L12.method', "POST"],
			["set", '$L12.requestBody', '$L11'],
			["create", '$L12.requestHeaders', "Object"],
			["string.concat", '$L12.requestHeaders.Authorization', "Bearer ", '$S0.access_token'],
			["string.concat", '$L12.requestHeaders.Content-Type', "multipart/form-data; boundary=", '$L8'],
			["http.requestCall", '$L13', '$L12'],
			["callFunc", "checkHttpErrors", '$P0', '$L13', "upload request", 201]
		],
		'uploadOverwrite' => [
			["string.lastIndexOf", '$L0', '$P1', "/"],
			["math.add", '$L1', '$L0', 1],
			["string.substring", '$L2', '$P1', '$L1'],
			["create", '$L5', "Object"],
			["if!=than", '$P4', NULL, 1],
			["set", '$L5.content_modified_at', '$P4'],
			["json.stringify", '$L6', '$L5'],
			["string.lastIndexOf", '$L7', '$L2', "."],
			["math.add", '$L7', '$L7', 1],
			["string.substring", '$L7', '$L2', '$L7'],
			["set", '$L14', '$L7'],
			["getMimeType", '$L7', '$L7'],
			["if==than", '$L7', NULL, 2],
			["debug.out", "No MIME type could be guessed for file extension '", '$L14', "', using 'application/octet-stream' as default"],
			["set", '$L7', "application/octet-stream"],
			["set", '$L8', "72365asfa72138jas5685oksco05"],
			["string.concat", '$L9', "--", '$L8', "\r\n", "Content-Disposition: form-data; name=\"attributes\"", "\r\n\r\n", '$L6', "\r\n", "--", '$L8', "\r\n", "Content-Disposition: form-data; name=\"file\"; filename=\"someName\"", "\r\n", "Content-Type: ", '$L7', "\r\n\r\n"],
			["string.concat", '$L10', "\r\n--", '$L8', "--\r\n"],
			["stream.stringToStream", '$L9', '$L9'],
			["stream.stringToStream", '$L10', '$L10'],
			["stream.makeJoinedStream", '$L11', '$L9', '$P2', '$L10'],
			["create", '$L12', "Object"],
			["string.concat", '$L12.url', "https://upload.box.com/api/2.0/files/", '$P3.id', "/content"],
			["set", '$L12.method', "POST"],
			["set", '$L12.requestBody', '$L11'],
			["create", '$L12.requestHeaders', "Object"],
			["string.concat", '$L12.requestHeaders.Authorization', "Bearer ", '$S0.access_token'],
			["string.concat", '$L12.requestHeaders.Content-Type', "multipart/form-data; boundary=", '$L8'],
			["http.requestCall", '$L13', '$L12'],
			["callFunc", "checkHttpErrors", '$P0', '$L13', "upload request", 200]
		],
		'CloudStorage:move' => [
			["callFunc", "validatePath", '$P0', '$P1'],
			["callFunc", "validatePath", '$P0', '$P2'],
			["callFunc", "checkAuthentication", '$P0'],
			["callFunc", "resolvePath", '$P0', '$L0', '$P2', 1],
			["if!=than", '$L0', NULL, 2],
			["create", '$L0', "Error", "Destination already exists.", "Http"],
			["throwError", '$L0'],
			["callFunc", "resolvePath", '$P0', '$L0', '$P1'],
			["string.lastIndexOf", '$L1', '$P2', "/"],
			["math.add", '$L2', '$L1', 1],
			["string.substring", '$L3', '$P2', '$L2'],
			["string.substring", '$L4', '$P2', 0, '$L1'],
			["if==than", '$L4', "", 1],
			["set", '$L4', "/"],
			["callFunc", "resolvePath", '$P0', '$L5', '$L4'],
			["create", '$L6', "Object"],
			["string.concat", '$L6.url', "https://api.box.com/2.0/", '$L0.type', "s/", '$L0.id'],
			["set", '$L6.method', "PUT"],
			["create", '$L6.requestHeaders', "Object"],
			["string.concat", '$L6.requestHeaders.Authorization', "Bearer ", '$S0.access_token'],
			["create", '$L7', "Object"],
			["set", '$L7.name', '$L3'],
			["create", '$L7.parent', "Object"],
			["set", '$L7.parent.id', '$L5.id'],
			["json.stringify", '$L7', '$L7'],
			["stream.stringToStream", '$L6.requestBody', '$L7'],
			["http.requestCall", '$L8', '$L6'],
			["callFunc", "checkHttpErrors", '$P0', '$L8', "move request", 204]
		],
		'CloudStorage:delete' => [
			["callFunc", "validatePath", '$P0', '$P1'],
			["callFunc", "checkAuthentication", '$P0'],
			["callFunc", "resolvePath", '$P0', '$L0', '$P1'],
			["if==than", '$L0.type', "file", 2],
			["string.concat", '$L1', "https://api.box.com/2.0/files/", '$L0.id'],
			["jumpRel", 1],
			["string.concat", '$L1', "https://api.box.com/2.0/folders/", '$L0.id', "?recursive=true"],
			["create", '$L2', "Object"],
			["set", '$L2.url', '$L1'],
			["set", '$L2.method', "DELETE"],
			["create", '$L2.requestHeaders', "Object"],
			["string.concat", '$L2.requestHeaders.Authorization', "Bearer ", '$S0.access_token'],
			["http.requestCall", '$L3', '$L2'],
			["callFunc", "checkHttpErrors", '$P0', '$L3', "delete request", 204]
		],
		'CloudStorage:copy' => [
			["callFunc", "validatePath", '$P0', '$P1'],
			["callFunc", "validatePath", '$P0', '$P2'],
			["callFunc", "checkAuthentication", '$P0'],
			["callFunc", "resolvePath", '$P0', '$L0', '$P1'],
			["string.lastIndexOf", '$L1', '$P2', "/"],
			["math.add", '$L2', '$L1', 1],
			["string.substring", '$L3', '$P2', '$L2'],
			["string.substring", '$L4', '$P2', 0, '$L1'],
			["if==than", '$L4', "", 1],
			["set", '$L4', "/"],
			["callFunc", "resolvePath", '$P0', '$L5', '$L4'],
			["create", '$L6', "Object"],
			["string.concat", '$L6.url', "https://api.box.com/2.0/", '$L0.type', "s/", '$L0.id', "/copy"],
			["set", '$L6.method', "POST"],
			["create", '$L6.requestHeaders', "Object"],
			["string.concat", '$L6.requestHeaders.Authorization', "Bearer ", '$S0.access_token'],
			["create", '$L7', "Object"],
			["set", '$L7.name', '$L3'],
			["create", '$L7.parent', "Object"],
			["set", '$L7.parent.id', '$L5.id'],
			["json.stringify", '$L7', '$L7'],
			["stream.stringToStream", '$L6.requestBody', '$L7'],
			["http.requestCall", '$L8', '$L6'],
			["callFunc", "checkHttpErrors", '$P0', '$L8', "copy request", 201]
		],
		'CloudStorage:createFolder' => [
			["callFunc", "validatePath", '$P0', '$P1'],
			["callFunc", "checkAuthentication", '$P0'],
			["string.lastIndexOf", '$L1', '$P1', "/"],
			["math.add", '$L2', '$L1', 1],
			["string.substring", '$L3', '$P1', '$L2'],
			["string.substring", '$L4', '$P1', 0, '$L1'],
			["if==than", '$L4', "", 1],
			["set", '$L4', "/"],
			["callFunc", "resolvePath", '$P0', '$L5', '$L4'],
			["create", '$L6', "Object"],
			["set", '$L6.url', "https://api.box.com/2.0/folders"],
			["set", '$L6.method', "POST"],
			["create", '$L6.requestHeaders', "Object"],
			["string.concat", '$L6.requestHeaders.Authorization', "Bearer ", '$S0.access_token'],
			["create", '$L7', "Object"],
			["set", '$L7.name', '$L3'],
			["create", '$L7.parent', "Object"],
			["set", '$L7.parent.id', '$L5.id'],
			["json.stringify", '$L7', '$L7'],
			["stream.stringToStream", '$L6.requestBody', '$L7'],
			["http.requestCall", '$L8', '$L6'],
			["callFunc", "checkHttpErrors", '$P0', '$L8', "create folder request", 201]
		],
		'CloudStorage:getMetadata' => [
			["callFunc", "validatePath", '$P0', '$P2'],
			["if==than", '$P2', "/", 2],
			["create", '$L2', "Error", "Root does not have MetaData", "IllegalArgument"],
			["throwError", '$L2'],
			["callFunc", "checkAuthentication", '$P0'],
			["callFunc", "resolvePath", '$P0', '$L0', '$P2'],
			["create", '$L2', "Object"],
			["string.concat", '$L3', "https://api.box.com/2.0/", '$L0.type', "s/", '$L0.id'],
			["set", '$L2.url', '$L3'],
			["set", '$L2.method', "GET"],
			["create", '$L2.requestHeaders', "Object"],
			["string.concat", '$L2.requestHeaders.Authorization', "Bearer ", '$S0.access_token'],
			["http.requestCall", '$L5', '$L2'],
			["callFunc", "checkHttpErrors", '$P0', '$L5', "metadata retrieval", 200],
			["json.parse", '$L4', '$L5.responseBody'],
			["string.lastIndexOf", '$L6', '$P2', "/"],
			["string.substring", '$L7', '$P2', 0, '$L6'],
			["callFunc", "makeMeta", '$P0', '$P1', '$L4', '$L7']
		],
		'CloudStorage:getChildren' => [
			["callFunc", "validatePath", '$P0', '$P2'],
			["callFunc", "checkAuthentication", '$P0'],
			["callFunc", "resolvePath", '$P0', '$L0', '$P2'],
			["create", '$L21', "Number", 500],
			["create", '$L1', "Number", 0],
			["create", '$L2', "Number", 0],
			["create", '$P1', "Array"],
			["create", '$L20', "Object"],
			["string.concat", '$L3', "https://api.box.com/2.0/folders/", '$L0.id', "/items?fields=name,size,type,modified_at&limit=", '$L21', "&offset=", '$L1'],
			["set", '$L20.url', '$L3'],
			["set", '$L20.method', "GET"],
			["create", '$L20.requestHeaders', "Object"],
			["string.concat", '$L20.requestHeaders.Authorization', "Bearer ", '$S0.access_token'],
			["http.requestCall", '$L5', '$L20'],
			["callFunc", "checkHttpErrors", '$P0', '$L5', "children metadata retrieval", 200],
			["json.parse", '$L4', '$L5.responseBody'],
			["size", '$L6', '$L4.entries'],
			["set", '$L7', 0],
			["if<than", '$L7', '$L6', 5],
			["get", '$L8', '$L4.entries', '$L7'],
			["callFunc", "makeMeta", '$P0', '$L9', '$L8', '$P2'],
			["push", '$P1', '$L9'],
			["math.add", '$L7', '$L7', 1],
			["jumpRel", -6],
			["if==than", '$L2', 0, 1],
			["set", '$L2', '$L4.total_count'],
			["math.add", '$L1', '$L1', '$L21'],
			["if<than", '$L1', '$L2', 1],
			["jumpRel", -22]
		],
		'getChildrenPage' => [
			["callFunc", "validatePath", '$P0', '$P2'],
			["callFunc", "checkAuthentication", '$P0'],
			["callFunc", "resolvePath", '$P0', '$L0', '$P2'],
			["create", '$P1', "Array"],
			["create", '$L20', "Object"],
			["string.concat", '$L3', "https://api.box.com/2.0/folders/", '$L0.id', "/items?fields=name,size,type,modified_at&limit=", '$P4', "&offset=", '$P3'],
			["set", '$L20.url', '$L3'],
			["set", '$L20.method', "GET"],
			["create", '$L20.requestHeaders', "Object"],
			["string.concat", '$L20.requestHeaders.Authorization', "Bearer ", '$S0.access_token'],
			["http.requestCall", '$L5', '$L20'],
			["callFunc", "checkHttpErrors", '$P0', '$L5', "children metadata retrieval", 200],
			["json.parse", '$L4', '$L5.responseBody'],
			["size", '$L6', '$L4.entries'],
			["set", '$L7', 0],
			["if<than", '$L7', '$L6', 5],
			["get", '$L8', '$L4.entries', '$L7'],
			["callFunc", "makeMeta", '$P0', '$L9', '$L8', '$P2'],
			["push", '$P1', '$L9'],
			["math.add", '$L7', '$L7', 1],
			["jumpRel", -6]
		],
		'Authenticating:login' => [
			["callFunc", "checkAuthentication", '$P0']
		],
		'Authenticating:logout' => [
			["if==than", '$S0.access_token', NULL, 1],
			["return"],
			["create", '$L0', "Object"],
			["set", '$L0.url', "https://api.box.com/oauth2/revoke"],
			["set", '$L0.method', "POST"],
			["string.concat", '$L1', "client_id=", '$P0.clientId', "&client_secret=", '$P0.clientSecret', "&token=", '$S0.access_token'],
			["stream.stringToStream", '$L0.requestBody', '$L1'],
			["http.requestCall", '$L2', '$L0'],
			["callFunc", "checkHttpErrors", '$P0', '$L2', "token revokation", 200],
			["set", '$S0.access_token', NULL]
		],
		'getAllocation' => [
			["callFunc", "checkAuthentication", '$P0'],
			["create", '$L0', "Object"],
			["string.concat", '$L0.url', "https://api.box.com/2.0/users/me"],
			["create", '$L0.requestHeaders', "Object"],
			["string.concat", '$L0.requestHeaders.Authorization', "Bearer ", '$S0.access_token'],
			["set", '$L0.method', "GET"],
			["http.requestCall", '$L2', '$L0'],
			["callFunc", "checkHttpErrors", '$P0', '$L2', "user about", 200],
			["json.parse", '$L3', '$L2.responseBody'],
			["create", '$L6', "SpaceAllocation"],
			["set", '$L6.total', '$L3.space_amount'],
			["set", '$L6.used', '$L3.space_used'],
			["set", '$P1', '$L6']
		],
		'createShareLink' => [
			["callFunc", "validatePath", '$P0', '$P2'],
			["if==than", '$P2', "/", 2],
			["create", '$L2', "Error", "Cannot share root", "IllegalArgument"],
			["throwError", '$L2'],
			["callFunc", "checkAuthentication", '$P0'],
			["callFunc", "resolvePath", '$P0', '$L0', '$P2'],
			["create", '$L7', "Object"],
			["create", '$L8', "Object"],
			["set", '$L7.shared_link', '$L8'],
			["json.stringify", '$L7', '$L7'],
			["stream.stringToStream", '$L7', '$L7'],
			["create", '$L2', "Object"],
			["string.concat", '$L3', "https://api.box.com/2.0/", '$L0.type', "s/", '$L0.id'],
			["set", '$L2.url', '$L3'],
			["set", '$L2.method', "PUT"],
			["set", '$L2.requestBody', '$L7'],
			["create", '$L2.requestHeaders', "Object"],
			["string.concat", '$L2.requestHeaders.Authorization', "Bearer ", '$S0.access_token'],
			["http.requestCall", '$L5', '$L2'],
			["callFunc", "checkHttpErrors", '$P0', '$L5', "metadata retrieval", 200],
			["json.parse", '$L4', '$L5.responseBody'],
			["set", '$P1', '$L4.shared_link.url']
		],
		'exists' => [
			["callFunc", "validatePath", '$P0', '$P2'],
			["if==than", '$P2', "/", 2],
			["set", '$P1', 1],
			["return"],
			["callFunc", "checkAuthentication", '$P0'],
			["callFunc", "resolvePath", '$P0', '$L0', '$P2', 1],
			["if==than", '$L0', NULL, 2],
			["set", '$P1', 0],
			["return"],
			["set", '$P1', 1]
		],
		'getThumbnail' => [
			["callFunc", "validatePath", '$P0', '$P2'],
			["callFunc", "checkAuthentication", '$P0'],
			["callFunc", "resolvePath", '$P0', '$L0', '$P2'],
			["create", '$L1', "Object"],
			["string.concat", '$L1.url', "https://api.box.com/2.0/files/", '$L0.id', "/thumbnail.jpg?min_height=128&min_width=128"],
			["set", '$L1.method', "GET"],
			["create", '$L2', "Object"],
			["string.concat", '$L2.Authorization', "Bearer ", '$S0.access_token'],
			["set", '$L1.requestHeaders', '$L2'],
			["http.requestCall", '$L3', '$L1'],
			["if==than", '$L3.code', 404, 1],
			["return"],
			["callFunc", "checkHttpErrors", '$P0', '$L3', "get thumbnail", 200],
			["set", '$P1', '$L3.responseBody']
		],
		'searchFiles' => [
			["callFunc", "checkNull", '$P0', '$P2'],
			["if==than", '$P2', "", 2],
			["create", '$L0', "Error", "The query is not allowed to be empty.", "IllegalArgument"],
			["throwError", '$L0'],
			["callFunc", "checkAuthentication", '$P0'],
			["callFunc", "replace", '$P0', '$L15', '$P2', " ", "+"],
			["create", '$L0', "Object"],
			["set", '$L0.url', "https://api.box.com/2.0/search"],
			["set", '$L0.method', "GET"],
			["create", '$L1', "Object"],
			["set", '$L0.requestHeaders', '$L1'],
			["string.concat", '$L1.Authorization', "Bearer ", '$S0.access_token'],
			["string.urlEncode", '$L1', '$L15'],
			["string.concat", '$L0.url', '$L0.url', "?query=", '$L1', "&scope=user_content&content_type=name"],
			["string.concat", '$L0.url', '$L0.url', "&offset=0&limit=200"],
			["http.requestCall", '$L1', '$L0'],
			["callFunc", "checkHttpErrors", '$P0', '$L1', "search files", 200],
			["json.parse", '$L2', '$L1.responseBody'],
			["get", '$L3', '$L2.entries'],
			["create", '$P1', "Array"],
			["create", '$L4', "Number"],
			["size", '$L5', '$L3'],
			["if<than", '$L4', '$L5', 15],
			["get", '$L6', '$L3', '$L4'],
			["create", '$L7', "Number", 1],
			["size", '$L8', '$L6.path_collection.entries'],
			["create", '$L9', "String", "/"],
			["if<than", '$L7', '$L8', 6],
			["get", '$L10', '$L6.path_collection.entries', '$L7'],
			["if!=than", '$L7', 1, 1],
			["string.concat", '$L9', '$L9', "/"],
			["string.concat", '$L9', '$L9', '$L10.name'],
			["math.add", '$L7', '$L7', 1],
			["jumpRel", -7],
			["callFunc", "makeMeta", '$P0', '$L10', '$L6', '$L9'],
			["push", '$P1', '$L10'],
			["math.add", '$L4', '$L4', 1],
			["jumpRel", -16]
		],
		'AdvancedRequestSupporter:advancedRequest' => [
			["create", '$L0', "Object"],
			["create", '$L0.url', "String"],
			["if!=than", '$P2.appendBaseUrl', 0, 1],
			["set", '$L0.url', "https://api.box.com/2.0"],
			["string.concat", '$L0.url', '$L0.url', '$P2.url'],
			["set", '$L0.requestHeaders', '$P2.headers'],
			["set", '$L0.method', '$P2.method'],
			["set", '$L0.requestBody', '$P2.body'],
			["if!=than", '$P2.appendAuthorization', 0, 2],
			["callFunc", "checkAuthentication", '$P0'],
			["string.concat", '$L0.requestHeaders.Authorization', "Bearer ", '$S0.access_token'],
			["http.requestCall", '$L1', '$L0'],
			["if!=than", '$P2.checkErrors', 0, 1],
			["callFunc", "checkHttpErrors", '$P0', '$L1', "advanced request", 200],
			["create", '$P1', "AdvancedRequestResponse"],
			["set", '$P1.status', '$L1.code'],
			["set", '$P1.headers', '$L1.responseHeaders'],
			["set", '$P1.body', '$L1.responseBody']
		],
		'checkAuthentication' => [
			["create", '$L0', "Date"],
			["if==than", '$S0.access_token', NULL, 2],
			["callFunc", "authenticate", '$P0', '$P1', "accessToken"],
			["return"],
			["create", '$L1', "Date"],
			["set", '$L1.time', '$S0.expires_in'],
			["if<than", '$L1', '$L0', 1],
			["callFunc", "authenticate", '$P0', '$P1', "refreshToken"]
		],
		'authenticate' => [
			["create", '$L2', "String"],
			["if==than", '$P2', "accessToken", 4],
			["string.concat", '$L0', "https://account.box.com/api/oauth2/authorize?response_type=code&client_id=", '$P0.clientId', "&redirect_uri=", '$P0.redirectUri', "&state=", '$P0.state'],
			["awaitCodeRedirect", '$L1', '$L0', NULL, '$P0.redirectUri'],
			["string.concat", '$L2', "client_id=", '$P0.clientId', "&redirect_uri=", '$P0.redirectUri', "&client_secret=", '$P0.clientSecret', "&code=", '$L1', "&grant_type=authorization_code"],
			["jumpRel", 1],
			["string.concat", '$L2', "client_id=", '$P0.clientId', "&redirect_uri=", '$P0.redirectUri', "&client_secret=", '$P0.clientSecret', "&refresh_token=", '$S0.refresh_token', "&grant_type=refresh_token"],
			["stream.stringToStream", '$L3', '$L2'],
			["create", '$L4', "Object"],
			["set", '$L4', "application/x-www-form-urlencoded", "Content-Type"],
			["create", '$L5', "Object"],
			["set", '$L5.url', "https://api.box.com/oauth2/token"],
			["set", '$L5.method', "POST"],
			["set", '$L5.requestBody', '$L3'],
			["set", '$L5.requestHeaders', '$L4'],
			["http.requestCall", '$L6', '$L5'],
			["if==than", '$P2', "accessToken", 2],
			["callFunc", "checkHttpErrors", '$P0', '$L6', "authentication", 200],
			["jumpRel", 3],
			["if>=than", '$L6.code', 400, 2],
			["callFunc", "authenticate", '$P0', '$P1', "accessToken"],
			["return"],
			["stream.streamToString", '$L7', '$L6.responseBody'],
			["json.parse", '$L8', '$L7'],
			["set", '$S0.access_token', '$L8.access_token'],
			["set", '$S0.refresh_token', '$L8.refresh_token'],
			["create", '$L10', "Date"],
			["math.multiply", '$L9', '$L8.expires_in', 1000],
			["math.add", '$L9', '$L9', '$L10.time', -60000],
			["set", '$S0.expires_in', '$L9']
		],
		'resolvePath' => [
			["if==than", '$P2', "/", 4],
			["create", '$P1', "Object"],
			["set", '$P1.id', "0"],
			["set", '$P1.type', "folder"],
			["return"],
			["string.substring", '$L0', '$P2', 1],
			["callFunc", "resolvePathRelative", '$P0', '$P1', '$L0', "0", '$P3']
		],
		'resolvePathRelative' => [
			["string.indexOf", '$L0', '$P2', "/"],
			["if!=than", '$L0', -1, 2],
			["string.substring", '$L1', '$P2', 0, '$L0'],
			["jumpRel", 1],
			["set", '$L1', '$P2'],
			["create", '$L12', "Number", 0],
			["create", '$L2', "Object"],
			["string.concat", '$L3', "https://api.box.com/2.0/folders/", '$P3', "/items?fields=name&limit=300&offset=", '$L12'],
			["set", '$L2.url', '$L3'],
			["set", '$L2.method', "GET"],
			["create", '$L2.requestHeaders', "Object"],
			["string.concat", '$L2.requestHeaders.Authorization', "Bearer ", '$S0.access_token'],
			["http.requestCall", '$L5', '$L2'],
			["callFunc", "checkHttpErrors", '$P0', '$L5', "path resolving", 200],
			["json.parse", '$L4', '$L5.responseBody'],
			["callFunc", "searchEntry", '$P0', '$L6', '$L4.entries', '$L1'],
			["if==than", '$L6', NULL, 10],
			["math.multiply", '$L11', '$L4.offset', -1],
			["math.add", '$L10', '$L4.total_count', '$L4.offset'],
			["if<=than", '$L4.offset', '$L4.total_count', 2],
			["math.add", '$L12', '$L12', 300],
			["jumpRel", -15],
			["if!=than", '$P4', NULL, 2],
			["set", '$P1', NULL],
			["return"],
			["create", '$L9', "Error", "Path does not point to an existing element", "NotFound"],
			["throwError", '$L9'],
			["if==than", '$L0', -1, 2],
			["set", '$P1', '$L6'],
			["return"],
			["math.add", '$L7', '$L0', 1],
			["string.substring", '$L8', '$P2', '$L7'],
			["callFunc", "resolvePathRelative", '$P0', '$P1', '$L8', '$L6.id', '$P4']
		],
		'searchEntry' => [
			["size", '$L0', '$P2'],
			["set", '$L1', 0],
			["if<than", '$L1', '$L0', 8],
			["get", '$L2', '$P2', '$L1'],
			["if==than", '$L2.name', '$P3', 4],
			["create", '$P1', "Object"],
			["set", '$P1.id', '$L2.id'],
			["set", '$P1.type', '$L2.type'],
			["return"],
			["math.add", '$L1', '$L1', 1],
			["jumpRel", -9]
		],
		'checkHttpErrors' => [
			["if!=than", '$P1.code', '$P3', 20],
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
		'makeMeta' => [
			["create", '$P1', "CloudMetaData"],
			["set", '$P1.name', '$P2.name'],
			["if!=than", '$P2.modified_at', NULL, 1],
			["callFunc", "extractTimeWithTimezone", '$P0', '$P1.modifiedAt', '$P2.modified_at'],
			["if!=than", '$P2.content_modified_at', NULL, 1],
			["callFunc", "extractTimeWithTimezone", '$P0', '$P1.contentModifiedAt', '$P2.content_modified_at'],
			["if==than", '$P2.type', "folder", 2],
			["set", '$P1.folder', 1],
			["jumpRel", 2],
			["set", '$P1.folder', 0],
			["set", '$P1.size', '$P2.size'],
			["if==than", '$P3', "/", 2],
			["string.concat", '$P1.path', '$P3', '$P2.name'],
			["return"],
			["string.concat", '$P1.path', '$P3', "/", '$P2.name']
		],
		'extractTimeWithTimezone' => [
			["set", '$L0', '$P2'],
			["string.split", '$L1', '$L0', "\\+"],
			["size", '$L2', '$L1'],
			["if==than", '$L2', 2, 10],
			["get", '$L3', '$L1', 1],
			["string.split", '$L4', '$L3', ":"],
			["get", '$L5', '$L4', 0],
			["get", '$L10', '$L1', 0],
			["string.concat", '$L10', '$L10', "Z"],
			["create", '$L11', "Date", '$L10'],
			["set", '$L12', '$L11.time'],
			["math.multiply", '$L13', '$L5', -3600000],
			["math.add", '$P1', '$L12', '$L13'],
			["return"],
			["get", '$L3', '$L1', 0],
			["string.split", '$L4', '$L3', "-"],
			["size", '$L5', '$L4'],
			["math.add", '$L6', '$L5', -1],
			["get", '$L7', '$L4', '$L6'],
			["string.split", '$L8', '$L7', ":"],
			["get", '$L9', '$L8', 0],
			["math.multiply", '$L10', '$L9', 3600000],
			["create", '$L11', "Number", 0],
			["create", '$L12', "String"],
			["math.add", '$L20', '$L6', -1],
			["if<than", '$L11', '$L6', 6],
			["get", '$L13', '$L4', '$L11'],
			["string.concat", '$L12', '$L12', '$L13'],
			["if!=than", '$L11', '$L20', 1],
			["string.concat", '$L12', '$L12', "-"],
			["math.add", '$L11', '$L11', 1],
			["jumpRel", -7],
			["string.concat", '$L12', '$L12', "Z"],
			["create", '$L14', "Date", '$L12'],
			["set", '$L15', '$L14.time'],
			["math.add", '$P1', '$L15', '$L10']
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
		'CloudStorage:uploadWithContentModifiedDate' => [
			["callFunc", "checkNull", '$P0', '$P2', '$P5'],
			["create", '$L0', "Date"],
			["set", '$L0.time', '$P5'],
			["set", '$L1', '$L0.rfcTimeUsingFormat3'],
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
		InitSelfTest::initTest('Box');
		
		$this->interpreterStorage['clientId'] = $clientId;
		$this->interpreterStorage['clientSecret'] = $clientSecret;
		$this->interpreterStorage['redirectUri'] = $redirectUri;
		$this->interpreterStorage['state'] = $state;
		

		$ip = new Interpreter(new Sandbox(Box::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		if (array_key_exists("init",Box::$SERVICE_CODE)) {
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
		Statistics::addCall("Box", "download");
		$ip = new Interpreter(new Sandbox(Box::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Box", "upload");
		$ip = new Interpreter(new Sandbox(Box::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Box", "move");
		$ip = new Interpreter(new Sandbox(Box::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Box", "delete");
		$ip = new Interpreter(new Sandbox(Box::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Box", "copy");
		$ip = new Interpreter(new Sandbox(Box::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Box", "createFolder");
		$ip = new Interpreter(new Sandbox(Box::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Box", "getMetadata");
		$ip = new Interpreter(new Sandbox(Box::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Box", "getChildren");
		$ip = new Interpreter(new Sandbox(Box::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Box", "getChildrenPage");
		$ip = new Interpreter(new Sandbox(Box::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Box", "getUserLogin");
		$ip = new Interpreter(new Sandbox(Box::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Box", "getUserName");
		$ip = new Interpreter(new Sandbox(Box::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Box", "createShareLink");
		$ip = new Interpreter(new Sandbox(Box::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null, $path];
		$ip->callFunctionSync('createShareLink', $auxArray);
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
		Statistics::addCall("Box", "getAllocation");
		$ip = new Interpreter(new Sandbox(Box::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null];
		$ip->callFunctionSync('getAllocation', $auxArray);
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
		Statistics::addCall("Box", "exists");
		$ip = new Interpreter(new Sandbox(Box::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null, $path];
		$ip->callFunctionSync('exists', $auxArray);
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
		Statistics::addCall("Box", "getThumbnail");
		$ip = new Interpreter(new Sandbox(Box::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null, $path];
		$ip->callFunctionSync('getThumbnail', $auxArray);
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
		Statistics::addCall("Box", "search");
		$ip = new Interpreter(new Sandbox(Box::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null, $query];
		$ip->callFunctionSync('searchFiles', $auxArray);
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
		Statistics::addCall("Box", "login");
		$ip = new Interpreter(new Sandbox(Box::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Box", "logout");
		$ip = new Interpreter(new Sandbox(Box::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Box", "uploadWithContentModifiedDate");
		$ip = new Interpreter(new Sandbox(Box::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Box", "advancedRequest");
		$ip = new Interpreter(new Sandbox(Box::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		$ip = new Interpreter(new Sandbox(Box::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		return $ip->saveAsString();
	}

	/**
	 * @param string $savedState
	 */
	public function loadAsString($savedState) {
		$sandbox = new Sandbox(Box::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage);
		$ip = new Interpreter($sandbox);
		$ip->loadAsString($savedState);
		$this->persistentStorage = $sandbox->persistentStorage;
	}
}
