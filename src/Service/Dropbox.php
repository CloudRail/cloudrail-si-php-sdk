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

class Dropbox implements CloudStorage, AdvancedRequestSupporter
{
	private static $SERVICE_CODE = [
		'init' => [
			["create", '$P0.paginationCache', "Object"],
			["create", '$P0.paginationCache.offset', "Number", 0],
			["create", '$P0.paginationCache.path', "String", "grgerfefrgerhggerger"],
			["create", '$P0.paginationCache.metaCache', "Array"]
		],
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
			["string.concat", '$L0.url', "https://api.dropboxapi.com/2/users/get_current_account"],
			["create", '$L0.requestHeaders', "Object"],
			["string.concat", '$L0.requestHeaders.Authorization', "Bearer ", '$S0.access_token'],
			["set", '$L0.method', "POST"],
			["http.requestCall", '$L2', '$L0'],
			["json.parse", '$L3', '$L2.responseBody'],
			["callFunc", "validateResponse", '$P0', '$L2'],
			["create", '$P0.userInfo', "Object"],
			["create", '$L4', "Date"],
			["set", '$P0.userInfo.lastUpdate', '$L4.Time'],
			["set", '$P0.userInfo.emailAddress', '$L3.email'],
			["set", '$P0.userInfo.displayName', '$L3.name.display_name']
		],
		'CloudStorage:download' => [
			["callFunc", "validatePath", '$P0', '$P2'],
			["callFunc", "checkAuthentication", '$P0'],
			["create", '$L0', "Object"],
			["set", '$L0.url', "https://content.dropboxapi.com/2/files/download"],
			["set", '$L0.method', "POST"],
			["create", '$L1', "Object"],
			["string.concat", '$L2', "Bearer ", '$S0.access_token'],
			["set", '$L1.Authorization', '$L2'],
			["create", '$L3', "Object"],
			["set", '$L3.path', '$P2'],
			["json.stringify", '$L4', '$L3'],
			["set", '$L1.Dropbox-API-Arg', '$L4'],
			["set", '$L0.requestHeaders', '$L1'],
			["http.requestCall", '$L5', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L5'],
			["set", '$P1', '$L5.responseBody']
		],
		'CloudStorage:upload' => [
			["callFunc", "validatePath", '$P0', '$P1'],
			["callFunc", "checkNull", '$P0', '$P2'],
			["callFunc", "checkPositive", '$P0', '$P3'],
			["callFunc", "checkAuthentication", '$P0'],
			["if==than", '$P4', 0, 1],
			["callFunc", "checkFileExists", '$P0', '$P1'],
			["callFunc", "checkParentPathExists", '$P0', '$P1'],
			["if<=than", '$P3', 10000000, 2],
			["callFunc", "simpleUpload", '$P0', '$P2', '$P1', '$P4', '$P5'],
			["jumpRel", 1],
			["callFunc", "chunkedUpload", '$P0', '$P2', '$P1', '$P3', '$P4', '$P5']
		],
		'CloudStorage:move' => [
			["callFunc", "validatePath", '$P0', '$P1'],
			["callFunc", "validatePath", '$P0', '$P2'],
			["callFunc", "checkAuthentication", '$P0'],
			["callFunc", "checkParentPathExists", '$P0', '$P2'],
			["create", '$L0', "Object"],
			["set", '$L0.from_path', '$P1'],
			["set", '$L0.to_path', '$P2'],
			["callFunc", "standardJSONRequest", '$P0', '$L1', '$L0', "https://api.dropboxapi.com/2/files/move"]
		],
		'CloudStorage:delete' => [
			["callFunc", "validatePath", '$P0', '$P1'],
			["callFunc", "checkAuthentication", '$P0'],
			["create", '$L0', "Object"],
			["set", '$L0.path', '$P1'],
			["callFunc", "standardJSONRequest", '$P0', '$L1', '$L0', "https://api.dropboxapi.com/2/files/delete"]
		],
		'CloudStorage:copy' => [
			["callFunc", "validatePath", '$P0', '$P1'],
			["callFunc", "validatePath", '$P0', '$P2'],
			["callFunc", "checkAuthentication", '$P0'],
			["callFunc", "checkParentPathExists", '$P0', '$P2'],
			["create", '$L0', "Object"],
			["set", '$L0.from_path', '$P1'],
			["set", '$L0.to_path', '$P2'],
			["callFunc", "standardJSONRequest", '$P0', '$L1', '$L0', "https://api.dropboxapi.com/2/files/copy"]
		],
		'CloudStorage:createFolder' => [
			["callFunc", "validatePath", '$P0', '$P1'],
			["callFunc", "checkAuthentication", '$P0'],
			["callFunc", "checkParentPathExists", '$P0', '$P1'],
			["create", '$L0', "Object"],
			["set", '$L0.path', '$P1'],
			["callFunc", "standardJSONRequest", '$P0', '$L1', '$L0', "https://api.dropboxapi.com/2/files/create_folder"]
		],
		'CloudStorage:getMetadata' => [
			["callFunc", "validatePath", '$P0', '$P2'],
			["if==than", '$P2', "/", 2],
			["create", '$L2', "Error", "Root does not have MetaData", "IllegalArgument"],
			["throwError", '$L2'],
			["callFunc", "checkAuthentication", '$P0'],
			["create", '$L0', "Object"],
			["set", '$L0.path', '$P2'],
			["set", '$L0.include_media_info', TRUE],
			["callFunc", "standardJSONRequest", '$P0', '$L1', '$L0', "https://api.dropboxapi.com/2/files/get_metadata"],
			["callFunc", "makeMeta", '$P0', '$P1', '$L1']
		],
		'CloudStorage:getChildren' => [
			["callFunc", "validatePath", '$P0', '$P2'],
			["if==than", '$P2', "/", 1],
			["set", '$P2', ""],
			["callFunc", "checkAuthentication", '$P0'],
			["create", '$P1', "Array"],
			["create", '$L0', "Object"],
			["set", '$L0.path', '$P2'],
			["callFunc", "standardJSONRequest", '$P0', '$L1', '$L0', "https://api.dropboxapi.com/2/files/list_folder"],
			["callFunc", "processRawMeta", '$P0', '$P1', '$L1'],
			["if==than", '$L1.has_more', 1, 5],
			["create", '$L2', "Object"],
			["set", '$L2.cursor', '$L1.cursor'],
			["callFunc", "standardJSONRequest", '$P0', '$L1', '$L2', "https://api.dropboxapi.com/2/files/list_folder/continue"],
			["callFunc", "processRawMeta", '$P0', '$P1', '$L1'],
			["jumpRel", -6]
		],
		'getChildrenPage' => [
			["callFunc", "validatePath", '$P0', '$P2'],
			["if==than", '$P2', "/", 1],
			["set", '$P2', ""],
			["callFunc", "checkAuthentication", '$P0'],
			["create", '$P1', "Array"],
			["if!=than", '$P0.paginationCache.path', '$P2', 1],
			["jumpRel", 1],
			["if<than", '$P3', '$P0.paginationCache.offset', 10],
			["set", '$P0.paginationCache.path', '$P2'],
			["set", '$P0.paginationCache.offset', 0],
			["create", '$P0.paginationCache.metaCache', "Array"],
			["create", '$L0', "Object"],
			["set", '$L0.path', '$P2'],
			["set", '$L0.limit', '$P4'],
			["callFunc", "standardJSONRequest", '$P0', '$L1', '$L0', "https://api.dropboxapi.com/2/files/list_folder"],
			["callFunc", "processRawMeta", '$P0', '$P0.paginationCache.metaCache', '$L1'],
			["set", '$P0.paginationCache.cursor', '$L1.cursor'],
			["jumpRel", -13],
			["create", '$L0', "Number"],
			["size", '$L0', '$P0.paginationCache.metaCache'],
			["math.add", '$L0', '$L0', '$P0.paginationCache.offset'],
			["if<than", '$P3', '$L0', 13],
			["math.multiply", '$L1', '$P0.paginationCache.offset', -1],
			["math.add", '$L1', '$L1', '$P3'],
			["size", '$L0', '$P1'],
			["if<than", '$L0', '$P4', 9],
			["get", '$L2', '$P0.paginationCache.metaCache', '$L1'],
			["push", '$P1', '$L2'],
			["math.add", '$L1', '$L1', 1],
			["size", '$L3', '$P0.paginationCache.metaCache'],
			["if==than", '$L3', '$L1', 3],
			["size", '$L4', '$P0.paginationCache.metaCache'],
			["math.add", '$P3', '$L4', '$P0.paginationCache.offset'],
			["jumpRel", 2],
			["jumpRel", -11],
			["return"],
			["if==than", '$P0.paginationCache.cursor', NULL, 1],
			["return"],
			["create", '$L0', "Object"],
			["set", '$L0.cursor', '$P0.paginationCache.cursor'],
			["callFunc", "standardJSONRequest", '$P0', '$L1', '$L0', "https://api.dropboxapi.com/2/files/list_folder/continue"],
			["size", '$L2', '$P0.paginationCache.metaCache'],
			["math.add", '$P0.paginationCache.offset', '$P0.paginationCache.offset', '$L2'],
			["create", '$P0.paginationCache.metaCache', "Array"],
			["callFunc", "processRawMeta", '$P0', '$P0.paginationCache.metaCache', '$L1'],
			["set", '$P0.paginationCache.cursor', '$L1.cursor'],
			["jumpRel", -42]
		],
		'CloudStorage:exists' => [
			["callFunc", "validatePath", '$P0', '$P2'],
			["callFunc", "checkAuthentication", '$P0'],
			["create", '$L2', "Object"],
			["set", '$L2.url', "https://api.dropboxapi.com/2/files/get_metadata"],
			["set", '$L2.method', "POST"],
			["create", '$L2.requestHeaders', "Object"],
			["string.concat", '$L2.requestHeaders.Authorization', "Bearer ", '$S0.access_token'],
			["set", '$L2.requestHeaders.Content-Type', "application/json"],
			["create", '$L3', "Object"],
			["set", '$L3.path', '$P2'],
			["json.stringify", '$L3', '$L3'],
			["stream.stringToStream", '$L2.requestBody', '$L3'],
			["http.requestCall", '$L4', '$L2'],
			["if==than", '$L4.code', 200, 2],
			["set", '$P1', 1],
			["return"],
			["if<than", '$L4.code', 429, 5],
			["json.parse", '$L0', '$L4.responseBody'],
			["string.indexOf", '$L1', '$L0.error_summary', "not_found"],
			["if!=than", '$L1', -1, 2],
			["set", '$P1', 0],
			["return"],
			["callFunc", "validateResponse", '$P0', '$L4']
		],
		'Authenticating:login' => [
			["callFunc", "checkAuthentication", '$P0']
		],
		'Authenticating:logout' => [
			["if==than", '$S0.access_token', NULL, 1],
			["return"],
			["create", '$L0', "Object"],
			["set", '$L0.url', "https://api.dropboxapi.com/2/auth/token/revoke"],
			["set", '$L0.method', "POST"],
			["create", '$L0.requestHeaders', "Object"],
			["string.concat", '$L0.requestHeaders.Authorization', "Bearer ", '$S0.access_token'],
			["http.requestCall", '$L1', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L1'],
			["set", '$S0.access_token', NULL]
		],
		'getAllocation' => [
			["callFunc", "checkAuthentication", '$P0'],
			["create", '$L0', "Object"],
			["create", '$L1', "String"],
			["set", '$L1', "https://api.dropboxapi.com/2/users/get_space_usage"],
			["set", '$L0.url', '$L1'],
			["set", '$L0.method', "POST"],
			["create", '$L0.requestHeaders', "Object"],
			["string.concat", '$L0.requestHeaders.Authorization', "Bearer ", '$S0.access_token'],
			["create", '$L4', "Object"],
			["http.requestCall", '$L4', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L4'],
			["create", '$L5', "Object"],
			["json.parse", '$L5', '$L4.responseBody'],
			["create", '$L6', "SpaceAllocation"],
			["set", '$L6.total', '$L5.allocation.allocated'],
			["set", '$L6.used', '$L5.used'],
			["set", '$P1', '$L6']
		],
		'createShareLink' => [
			["callFunc", "validatePath", '$P0', '$P2'],
			["if==than", '$P2', "/", 2],
			["create", '$L2', "Error", "Cannot share root", "IllegalArgument"],
			["throwError", '$L2'],
			["callFunc", "checkAuthentication", '$P0'],
			["callFunc", "checkParentPathExists", '$P0', '$P2'],
			["create", '$L0', "Object"],
			["set", '$L0.path', '$P2'],
			["set", '$L0.direct_only', TRUE],
			["callFunc", "standardJSONRequest", '$P0', '$L1', '$L0', "https://api.dropboxapi.com/2/sharing/list_shared_links"],
			["size", '$L2', '$L1.links'],
			["if==than", '$L2', 0, 5],
			["create", '$L0', "Object"],
			["set", '$L0.path', '$P2'],
			["callFunc", "standardJSONRequest", '$P0', '$L1', '$L0', "https://api.dropboxapi.com/2/sharing/create_shared_link_with_settings"],
			["set", '$P1', '$L1.url'],
			["return"],
			["get", '$P1', '$L1', "links", 0, "url"]
		],
		'CloudStorage:getThumbnail' => [
			["callFunc", "validatePath", '$P0', '$P2'],
			["callFunc", "checkAuthentication", '$P0'],
			["callFunc", "CloudStorage:exists", '$P0', '$L0', '$P2'],
			["if==than", '$L0', 0, 2],
			["create", '$L0', "Error", "File does not exist.", "NotFound"],
			["throwError", '$L0'],
			["create", '$L0', "Object"],
			["set", '$L0.url', "https://content.dropboxapi.com/2/files/get_thumbnail"],
			["set", '$L0.method', "POST"],
			["create", '$L1', "Object"],
			["set", '$L1.path', '$P2'],
			["set", '$L1.format', "jpeg"],
			["set", '$L1.size', "w128h128"],
			["json.stringify", '$L1', '$L1'],
			["create", '$L2', "Object"],
			["string.concat", '$L2.Authorization', "Bearer ", '$S0.access_token'],
			["set", '$L2', '$L1', "Dropbox-API-Arg"],
			["set", '$L0.requestHeaders', '$L2'],
			["create", '$L3', "Object"],
			["http.requestCall", '$L3', '$L0'],
			["if==than", '$L3.code', 409, 1],
			["return"],
			["callFunc", "validateResponse", '$P0', '$L3'],
			["set", '$P1', '$L3.responseBody']
		],
		'CloudStorage:searchFiles' => [
			["callFunc", "checkNull", '$P0', '$P2'],
			["if==than", '$P2', "", 2],
			["create", '$L0', "Error", "The query is not allowed to be empty.", "IllegalArgument"],
			["throwError", '$L0'],
			["callFunc", "checkAuthentication", '$P0'],
			["create", '$L0', "Object"],
			["set", '$L0.method', "POST"],
			["set", '$L0.url', "https://api.dropboxapi.com/2/files/search"],
			["create", '$L1', "Object"],
			["set", '$L0.requestHeaders', '$L1'],
			["set", '$L1.Content-Type', "application/json"],
			["string.concat", '$L1.Authorization', "Bearer ", '$S0.access_token'],
			["create", '$L2', "Object"],
			["set", '$L2.path', ""],
			["set", '$L2.query', '$P2'],
			["set", '$L2.start', 0],
			["set", '$L2.max_results', 100],
			["set", '$L2.mode', "filename"],
			["json.stringify", '$L3', '$L2'],
			["stream.stringToStream", '$L0.requestBody', '$L3'],
			["http.requestCall", '$L1', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L1'],
			["json.parse", '$L2', '$L1.responseBody'],
			["create", '$P1', "Array"],
			["create", '$L3', "Number"],
			["size", '$L4', '$L2.matches'],
			["if<than", '$L3', '$L4', 5],
			["get", '$L5', '$L2.matches', '$L3'],
			["callFunc", "makeMeta", '$P0', '$L6', '$L5.metadata'],
			["push", '$P1', '$L6'],
			["math.add", '$L3', '$L3', 1],
			["jumpRel", -6]
		],
		'AdvancedRequestSupporter:advancedRequest' => [
			["create", '$L0', "Object"],
			["create", '$L0.url', "String"],
			["if!=than", '$P2.appendBaseUrl', 0, 1],
			["set", '$L0.url', "https://api.dropboxapi.com/2"],
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
			["string.concat", '$L0', "https://www.dropbox.com/oauth2/authorize?response_type=code&force_reauthentication=true&redirect_uri=", '$P0.redirectUri', "&client_id=", '$P0.clientId', "&state=", '$P0.state'],
			["awaitCodeRedirect", '$L1', '$L0', NULL, '$P0.redirectUri'],
			["create", '$L2', "Object"],
			["set", '$L2.url', "https://api.dropboxapi.com/oauth2/token"],
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
		'standardJSONRequest' => [
			["create", '$L0', "Object"],
			["set", '$L0.url', '$P3'],
			["set", '$L0.method', "POST"],
			["create", '$L1', "Object"],
			["string.concat", '$L2', "Bearer ", '$S0.access_token'],
			["set", '$L1.Authorization', '$L2'],
			["set", '$L1.Content-Type', "application/json"],
			["set", '$L0.requestHeaders', '$L1'],
			["json.stringify", '$L3', '$P2'],
			["stream.stringToStream", '$L4', '$L3'],
			["set", '$L0.requestBody', '$L4'],
			["http.requestCall", '$L5', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L5'],
			["json.parse", '$L6', '$L5.responseBody'],
			["set", '$P1', '$L6']
		],
		'makeMeta' => [
			["create", '$P1', "CloudMetaData"],
			["set", '$P1.name', '$P2.name'],
			["if!=than", '$P2.server_modified', NULL, 2],
			["create", '$L1', "Date", '$P2.server_modified'],
			["set", '$P1.modifiedAt', '$L1.time'],
			["if!=than", '$P2.client_modified', NULL, 2],
			["create", '$L6', "Date", '$P2.client_modified'],
			["set", '$P1.contentModifiedAt', '$L6.time'],
			["get", '$L0', '$P2', ".tag"],
			["if==than", '$L0', "folder", 2],
			["set", '$P1.folder', 1],
			["jumpRel", 2],
			["set", '$P1.folder', 0],
			["set", '$P1.size', '$P2.size'],
			["set", '$P1.path', '$P2.path_display'],
			["if!=than", '$P2.media_info', NULL, 6],
			["if!=than", '$P2.media_info.metadata.dimensions', NULL, 5],
			["get", '$L2', '$P2.media_info.metadata.dimensions'],
			["get", '$L3', '$L2.width'],
			["get", '$L4', '$L2.height'],
			["create", '$L5', "ImageMetaData", '$L4', '$L3'],
			["set", '$P1.imageMetaData', '$L5']
		],
		'processRawMeta' => [
			["set", '$L0', '$P2.entries'],
			["size", '$L1', '$L0'],
			["math.add", '$L2', '$L1', -1],
			["if>=than", '$L2', 0, 5],
			["get", '$L3', '$L0', '$L2'],
			["callFunc", "makeMeta", '$P0', '$L4', '$L3'],
			["push", '$P1', '$L4'],
			["math.add", '$L2', '$L2', -1],
			["jumpRel", -6]
		],
		'simpleUpload' => [
			["create", '$L0', "Object"],
			["set", '$L0.url', "https://content.dropboxapi.com/2/files/upload"],
			["set", '$L0.method', "POST"],
			["create", '$L1', "Object"],
			["string.concat", '$L2', "Bearer ", '$S0.access_token'],
			["set", '$L1.Authorization', '$L2'],
			["set", '$L1.Content-Type', "application/octet-stream"],
			["create", '$L3', "Object"],
			["set", '$L3.path', '$P2'],
			["if!=than", '$P3', 0, 1],
			["set", '$L3.mode', "overwrite"],
			["if!=than", '$P4', NULL, 1],
			["set", '$L3.client_modified', '$P4'],
			["json.stringify", '$L4', '$L3'],
			["set", '$L1.Dropbox-API-Arg', '$L4'],
			["set", '$L0.requestHeaders', '$L1'],
			["set", '$L0.requestBody', '$P1'],
			["http.requestCall", '$L5', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L5']
		],
		'chunkedUpload' => [
			["stream.makeLimitedStream", '$L0', '$P1', 10000000],
			["callFunc", "chunkedStart", '$P0', '$L1', '$L0'],
			["math.add", '$L2', '$P3', -10000000],
			["set", '$L5', 10000000],
			["if>than", '$L2', 10000000, 7],
			["stream.makeLimitedStream", '$L3', '$P1', 10000000],
			["callFunc", "chunkedAppend", '$P0', '$L3', '$L1', '$L5'],
			["math.add", '$L4', '$L2', -10000000],
			["set", '$L2', '$L4'],
			["math.add", '$L6', '$L5', 10000000],
			["set", '$L5', '$L6'],
			["jumpRel", -8],
			["callFunc", "chunkedFinish", '$P0', '$P1', '$P2', '$L1', '$L5', '$P4', '$P5']
		],
		'chunkedStart' => [
			["create", '$L0', "Object"],
			["set", '$L0.url', "https://content.dropboxapi.com/2/files/upload_session/start"],
			["set", '$L0.method', "POST"],
			["create", '$L1', "Object"],
			["string.concat", '$L2', "Bearer ", '$S0.access_token'],
			["set", '$L1.Authorization', '$L2'],
			["set", '$L1.Content-Type', "application/octet-stream"],
			["set", '$L0.requestHeaders', '$L1'],
			["set", '$L0.requestBody', '$P2'],
			["http.requestCall", '$L3', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L3'],
			["json.parse", '$L4', '$L3.responseBody'],
			["set", '$P1', '$L4.session_id']
		],
		'chunkedAppend' => [
			["create", '$L0', "Object"],
			["set", '$L0.url', "https://content.dropboxapi.com/2/files/upload_session/append_v2"],
			["set", '$L0.method', "POST"],
			["create", '$L1', "Object"],
			["string.concat", '$L2', "Bearer ", '$S0.access_token'],
			["set", '$L1.Authorization', '$L2'],
			["set", '$L1.Content-Type', "application/octet-stream"],
			["create", '$L3', "Object"],
			["set", '$L3.session_id', '$P2'],
			["set", '$L3.offset', '$P3'],
			["create", '$L7', "Object"],
			["set", '$L7.cursor', '$L3'],
			["json.stringify", '$L4', '$L7'],
			["set", '$L1.Dropbox-API-Arg', '$L4'],
			["set", '$L0.requestHeaders', '$L1'],
			["set", '$L0.requestBody', '$P1'],
			["http.requestCall", '$L5', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L5']
		],
		'chunkedFinish' => [
			["create", '$L0', "Object"],
			["set", '$L0.url', "https://content.dropboxapi.com/2/files/upload_session/finish"],
			["set", '$L0.method', "POST"],
			["create", '$L1', "Object"],
			["string.concat", '$L2', "Bearer ", '$S0.access_token'],
			["set", '$L1.Authorization', '$L2'],
			["set", '$L1.Content-Type', "application/octet-stream"],
			["create", '$L3', "Object"],
			["set", '$L3.session_id', '$P3'],
			["set", '$L3.offset', '$P4'],
			["create", '$L8', "Object"],
			["if!=than", '$P5', 0, 1],
			["set", '$L8.mode', "overwrite"],
			["if!=than", '$P6', NULL, 1],
			["set", '$L8.client_modified', '$P6'],
			["set", '$L8.path', '$P2'],
			["create", '$L7', "Object"],
			["set", '$L7.cursor', '$L3'],
			["set", '$L7.commit', '$L8'],
			["json.stringify", '$L4', '$L7'],
			["set", '$L1.Dropbox-API-Arg', '$L4'],
			["set", '$L0.requestHeaders', '$L1'],
			["set", '$L0.requestBody', '$P1'],
			["http.requestCall", '$L5', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L5']
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
			["if>=than", '$P1.code', 400, 26],
			["if==than", '$P1.code', 400, 3],
			["stream.streamToString", '$L2', '$P1.responseBody'],
			["create", '$L3', "Error", '$L2', "Http"],
			["throwError", '$L3'],
			["if==than", '$P1.code', 401, 3],
			["stream.streamToString", '$L2', '$P1.responseBody'],
			["create", '$L3', "Error", '$L2', "Authentication"],
			["throwError", '$L3'],
			["if>=than", '$P1.code', 500, 6],
			["stream.streamToString", '$L2', '$P1.responseBody'],
			["if==than", '$P1.code', 503, 2],
			["create", '$L3', "Error", '$L2', "ServiceUnavailable"],
			["throwError", '$L3'],
			["create", '$L3', "Error", '$L2', "Authentication"],
			["throwError", '$L3'],
			["stream.streamToString", '$L2', '$P1.responseBody'],
			["string.indexOf", '$L4', '$L2', "not_found"],
			["if>=than", '$P1.code', 402, 5],
			["if<=than", '$P1.code', 509, 4],
			["if!=than", '$P1.code', 503, 3],
			["if==than", '$L4', -1, 2],
			["create", '$L3', "Error", '$L2', "Http"],
			["throwError", '$L3'],
			["if!=than", '$L4', -1, 2],
			["create", '$L3', "Error", '$L2', "NotFound"],
			["throwError", '$L3']
		],
		'checkParentPathExists' => [
			["string.lastIndexOf", '$L0', '$P1', "/"],
			["string.substring", '$L1', '$P1', 0, '$L0'],
			["if==than", '$L1', "", 1],
			["return"],
			["create", '$L2', "Object"],
			["set", '$L2.url', "https://api.dropboxapi.com/2/files/get_metadata"],
			["set", '$L2.method', "POST"],
			["create", '$L2.requestHeaders', "Object"],
			["string.concat", '$L2.requestHeaders.Authorization', "Bearer ", '$S0.access_token'],
			["set", '$L2.requestHeaders.Content-Type', "application/json"],
			["create", '$L3', "Object"],
			["set", '$L3.path', '$L1'],
			["json.stringify", '$L3', '$L3'],
			["stream.stringToStream", '$L2.requestBody', '$L3'],
			["http.requestCall", '$L4', '$L2'],
			["if!=than", '$L4.code', 200, 2],
			["create", '$L5', "Error", "Target folder not found.", "NotFound"],
			["throwError", '$L5']
		],
		'checkFileExists' => [
			["create", '$L2', "Object"],
			["set", '$L2.url', "https://api.dropboxapi.com/2/files/get_metadata"],
			["set", '$L2.method', "POST"],
			["create", '$L2.requestHeaders', "Object"],
			["string.concat", '$L2.requestHeaders.Authorization', "Bearer ", '$S0.access_token'],
			["set", '$L2.requestHeaders.Content-Type', "application/json"],
			["create", '$L3', "Object"],
			["set", '$L3.path', '$P1'],
			["json.stringify", '$L3', '$L3'],
			["stream.stringToStream", '$L2.requestBody', '$L3'],
			["http.requestCall", '$L4', '$L2'],
			["if==than", '$L4.code', 200, 2],
			["create", '$L5', "Error", "File already exists.", "Http"],
			["throwError", '$L5']
		],
		'CloudStorage:uploadWithContentModifiedDate' => [
			["callFunc", "checkNull", '$P0', '$P2', '$P5'],
			["create", '$L0', "Date"],
			["set", '$L0.time', '$P5'],
			["set", '$L1', '$L0.rfcTime'],
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
		InitSelfTest::initTest('Dropbox');
		
		$this->interpreterStorage['clientId'] = $clientId;
		$this->interpreterStorage['clientSecret'] = $clientSecret;
		$this->interpreterStorage['redirectUri'] = $redirectUri;
		$this->interpreterStorage['state'] = $state;
		

		$ip = new Interpreter(new Sandbox(Dropbox::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		if (array_key_exists("init",Dropbox::$SERVICE_CODE)) {
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
		Statistics::addCall("Dropbox", "download");
		$ip = new Interpreter(new Sandbox(Dropbox::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Dropbox", "upload");
		$ip = new Interpreter(new Sandbox(Dropbox::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Dropbox", "move");
		$ip = new Interpreter(new Sandbox(Dropbox::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Dropbox", "delete");
		$ip = new Interpreter(new Sandbox(Dropbox::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Dropbox", "copy");
		$ip = new Interpreter(new Sandbox(Dropbox::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Dropbox", "createFolder");
		$ip = new Interpreter(new Sandbox(Dropbox::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Dropbox", "getMetadata");
		$ip = new Interpreter(new Sandbox(Dropbox::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Dropbox", "getChildren");
		$ip = new Interpreter(new Sandbox(Dropbox::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Dropbox", "getChildrenPage");
		$ip = new Interpreter(new Sandbox(Dropbox::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Dropbox", "getUserLogin");
		$ip = new Interpreter(new Sandbox(Dropbox::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Dropbox", "getUserName");
		$ip = new Interpreter(new Sandbox(Dropbox::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Dropbox", "createShareLink");
		$ip = new Interpreter(new Sandbox(Dropbox::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Dropbox", "getAllocation");
		$ip = new Interpreter(new Sandbox(Dropbox::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Dropbox", "exists");
		$ip = new Interpreter(new Sandbox(Dropbox::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Dropbox", "getThumbnail");
		$ip = new Interpreter(new Sandbox(Dropbox::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Dropbox", "search");
		$ip = new Interpreter(new Sandbox(Dropbox::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Dropbox", "login");
		$ip = new Interpreter(new Sandbox(Dropbox::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Dropbox", "logout");
		$ip = new Interpreter(new Sandbox(Dropbox::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Dropbox", "uploadWithContentModifiedDate");
		$ip = new Interpreter(new Sandbox(Dropbox::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Dropbox", "advancedRequest");
		$ip = new Interpreter(new Sandbox(Dropbox::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		$ip = new Interpreter(new Sandbox(Dropbox::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		return $ip->saveAsString();
	}

	/**
	 * @param string $savedState
	 */
	public function loadAsString($savedState) {
		$sandbox = new Sandbox(Dropbox::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage);
		$ip = new Interpreter($sandbox);
		$ip->loadAsString($savedState);
		$this->persistentStorage = $sandbox->persistentStorage;
	}
}
