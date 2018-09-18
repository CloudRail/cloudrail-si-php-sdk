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

class PCloud implements CloudStorage, AdvancedRequestSupporter
{
	private static $SERVICE_CODE = [
		'init' => [
			["set", '$P0.baseUrl', "https://api.pcloud.com/"]
		],
		'CloudStorage:getUserLogin' => [
			["callFunc", "setCurrentUser", '$P0'],
			["string.concat", '$P0.userInfo.userId', '$P0.userInfo.userId'],
			["set", '$P1', '$P0.userInfo.userId']
		],
		'CloudStorage:getUserName' => [
			["callFunc", "setCurrentUser", '$P0'],
			["set", '$P1', '$P0.userInfo.emailAddress']
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
			["string.concat", '$L0.url', '$P0.baseUrl', "userinfo"],
			["create", '$L0.requestHeaders', "Object"],
			["string.concat", '$L0.requestHeaders.Authorization', "Bearer ", '$S0.access_token'],
			["set", '$L0.method', "POST"],
			["http.requestCall", '$L2', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L2'],
			["callFunc", "customJsonParse", '$P0', '$L3', '$L2.responseBody'],
			["callFunc", "validateResult", '$P0', '$L3'],
			["create", '$P0.userInfo', "Object"],
			["create", '$L4', "Date"],
			["set", '$P0.userInfo.lastUpdate', '$L4.Time'],
			["set", '$P0.userInfo.emailAddress', '$L3.email'],
			["string.concat", '$P0.userInfo.userId', '$L3.userid', ""],
			["string.concat", '$P0.userInfo.isPremium', '$L3.premium', ""],
			["set", '$P0.userInfo.quotaTotal', '$L3.quota'],
			["set", '$P0.userInfo.quotaUsed', '$L3.usedquota']
		],
		'CloudStorage:download' => [
			["callFunc", "setCurrentUser", '$P0'],
			["callFunc", "validatePath", '$P0', '$P2'],
			["callFunc", "checkAuthentication", '$P0'],
			["callFunc", "getFileLink", '$P0', '$L0', '$P2'],
			["callFunc", "downloadFile", '$P0', '$P1', '$L0']
		],
		'getFileLink' => [
			["string.urlEncode", '$L4', '$P2'],
			["create", '$L0', "Object"],
			["string.concat", '$L0.url', '$P0.baseUrl', "getfilelink", "?path=", '$L4'],
			["create", '$L0.requestHeaders', "Object"],
			["string.concat", '$L0.requestHeaders.Authorization', "Bearer ", '$S0.access_token'],
			["set", '$L0.method', "POST"],
			["http.requestCall", '$L2', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L2'],
			["callFunc", "customJsonParse", '$P0', '$L3', '$L2.responseBody'],
			["callFunc", "validateResult", '$P0', '$L3'],
			["string.concat", '$P1', "https://", '$L3.hosts.0', '$L3.path']
		],
		'downloadFile' => [
			["create", '$L0', "Object"],
			["string.concat", '$L0.url', '$P2'],
			["create", '$L0.requestHeaders', "Object"],
			["string.concat", '$L0.requestHeaders.Authorization', "Bearer ", '$S0.access_token'],
			["set", '$L0.method', "GET"],
			["http.requestCall", '$L2', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L2'],
			["set", '$P1', '$L2.responseBody']
		],
		'CloudStorage:upload' => [
			["callFunc", "setCurrentUser", '$P0'],
			["callFunc", "validatePath", '$P0', '$P1'],
			["callFunc", "checkAuthentication", '$P0'],
			["callFunc", "getParentFolder", '$P0', '$L0', '$P1'],
			["callFunc", "getFilename", '$P0', '$L1', '$P1'],
			["callFunc", "CloudStorage:exists", '$P0', '$L7', '$L0'],
			["if==than", '$L7', 0, 2],
			["create", '$L8', "Error", "Directory does not exist.", "NotFound"],
			["throwError", '$L8'],
			["callFunc", "getRawFolderMetaData", '$P0', '$L5', '$L0', 0],
			["if==than", '$L5', NULL, 2],
			["create", '$L6', "Error", "Folder does not exist.", "NotFound"],
			["throwError", '$L6'],
			["string.urlEncode", '$L0', '$L0'],
			["string.urlEncode", '$L1', '$L1'],
			["callFunc", "getFileMetadata", '$P0', '$L11', '$P1'],
			["if!=than", '$L11', NULL, 7],
			["if==than", '$P4', TRUE, 2],
			["callFunc", "CloudStorage:delete", '$P0', '$P1'],
			["jumpRel", 4],
			["callFunc", "CloudStorage:exists", '$P0', '$L9', '$P1'],
			["if==than", '$L9', 1, 2],
			["create", '$L10', "Error", "File already exists", "Http"],
			["throwError", '$L10'],
			["create", '$L2', "Object"],
			["string.concat", '$L2.url', '$P0.baseUrl', "uploadfile", "?path=", '$L0', "&nopartial=1&filename=", '$L1'],
			["create", '$L2.requestHeaders', "Object"],
			["string.concat", '$L2.requestHeaders.Authorization', "Bearer ", '$S0.access_token'],
			["string.concat", '$L2.requestHeaders.Content-Length', '$P3', ""],
			["set", '$L2.requestBody', '$P2'],
			["set", '$L2.method', "POST"],
			["http.requestCall", '$L3', '$L2'],
			["callFunc", "validateResponse", '$P0', '$L3'],
			["callFunc", "customJsonParse", '$P0', '$L4', '$L3.responseBody'],
			["callFunc", "validateResult", '$P0', '$L4']
		],
		'getParentFolder' => [
			["callFunc", "validatePath", '$P0', '$P2'],
			["string.lastIndexOf", '$L0', '$P2', "/"],
			["if==than", '$L0', 0, 2],
			["set", '$P1', "/"],
			["return"],
			["string.substring", '$P1', '$P2', 0, '$L0']
		],
		'getFilename' => [
			["callFunc", "validatePath", '$P0', '$P2'],
			["string.lastIndexOf", '$L0', '$P2', "/"],
			["math.add", '$L0', '$L0', 1],
			["string.substring", '$P1', '$P2', '$L0']
		],
		'CloudStorage:move' => [
			["callFunc", "setCurrentUser", '$P0'],
			["callFunc", "validatePath", '$P0', '$P1'],
			["callFunc", "validatePath", '$P0', '$P2'],
			["callFunc", "checkAuthentication", '$P0'],
			["set", '$L0', "renamefile"],
			["callFunc", "isFolder", '$P0', '$L1', '$P1'],
			["if==than", '$L1', 1, 1],
			["set", '$L0', "renamefolder"],
			["string.urlEncode", '$L3', '$P1'],
			["string.urlEncode", '$L4', '$P2'],
			["create", '$L2', "Object"],
			["string.concat", '$L2.url', '$P0.baseUrl', '$L0', "?path=", '$L3', "&topath=", '$L4'],
			["create", '$L2.requestHeaders', "Object"],
			["string.concat", '$L2.requestHeaders.Authorization', "Bearer ", '$S0.access_token'],
			["set", '$L2.method', "POST"],
			["http.requestCall", '$L5', '$L2'],
			["callFunc", "validateResponse", '$P0', '$L5'],
			["callFunc", "customJsonParse", '$P0', '$L6', '$L5.responseBody'],
			["callFunc", "validateResult", '$P0', '$L6']
		],
		'CloudStorage:delete' => [
			["callFunc", "setCurrentUser", '$P0'],
			["callFunc", "validatePath", '$P0', '$P1'],
			["callFunc", "checkAuthentication", '$P0'],
			["string.urlEncode", '$L5', '$P1'],
			["set", '$L0', "deletefile"],
			["callFunc", "isFolder", '$P0', '$L1', '$P1'],
			["if==than", '$L1', 1, 1],
			["set", '$L0', "deletefolderrecursive"],
			["create", '$L2', "Object"],
			["string.concat", '$L2.url', '$P0.baseUrl', '$L0', "?path=", '$L5'],
			["create", '$L2.requestHeaders', "Object"],
			["string.concat", '$L2.requestHeaders.Authorization', "Bearer ", '$S0.access_token'],
			["set", '$L2.method', "POST"],
			["http.requestCall", '$L3', '$L2'],
			["callFunc", "validateResponse", '$P0', '$L3'],
			["callFunc", "customJsonParse", '$P0', '$L4', '$L3.responseBody'],
			["callFunc", "validateResult", '$P0', '$L4']
		],
		'CloudStorage:copy' => [
			["callFunc", "setCurrentUser", '$P0'],
			["callFunc", "validatePath", '$P0', '$P1'],
			["callFunc", "validatePath", '$P0', '$P2'],
			["callFunc", "CloudStorage:exists", '$P0', '$L0', '$P1'],
			["if==than", '$L0', 0, 2],
			["create", '$L2', "Error", "File or Folder does not exist!", "NotFound"],
			["throwError", '$L2'],
			["callFunc", "isFolder", '$P0', '$L0', '$P1'],
			["callFunc", "isFolder", '$P0', '$L1', '$P2'],
			["if==than", '$L0', 1, 1],
			["jumpRel", 1],
			["if==than", '$L1', 1, 2],
			["create", '$L2', "Error", "Cannot copy folders.", "IllegalArgument"],
			["throwError", '$L2'],
			["string.urlEncode", '$L4', '$P1'],
			["string.urlEncode", '$L5', '$P2'],
			["create", '$L3', "Object"],
			["string.concat", '$L3.url', '$P0.baseUrl', "copyfile", "?path=", '$L4', "&topath=", '$L5'],
			["create", '$L3.requestHeaders', "Object"],
			["string.concat", '$L3.requestHeaders.Authorization', "Bearer ", '$S0.access_token'],
			["set", '$L3.method', "POST"],
			["http.requestCall", '$L6', '$L3'],
			["callFunc", "validateResponse", '$P0', '$L6'],
			["callFunc", "customJsonParse", '$P0', '$L7', '$L6.responseBody'],
			["callFunc", "validateResult", '$P0', '$L7']
		],
		'CloudStorage:createFolder' => [
			["callFunc", "setCurrentUser", '$P0'],
			["callFunc", "validatePath", '$P0', '$P1'],
			["callFunc", "checkAuthentication", '$P0'],
			["string.urlEncode", '$L5', '$P1'],
			["create", '$L0', "Object"],
			["string.concat", '$L0.url', '$P0.baseUrl', "createfolder", "?path=", '$L5'],
			["create", '$L0.requestHeaders', "Object"],
			["string.concat", '$L0.requestHeaders.Authorization', "Bearer ", '$S0.access_token'],
			["set", '$L0.method', "POST"],
			["http.requestCall", '$L3', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L3'],
			["callFunc", "customJsonParse", '$P0', '$L4', '$L3.responseBody'],
			["callFunc", "validateResult", '$P0', '$L4']
		],
		'CloudStorage:getMetadata' => [
			["callFunc", "setCurrentUser", '$P0'],
			["if==than", '$P2', "/", 2],
			["create", '$L0', "Error", "Cannot get metadata for the root folder.", "IllegalArgument"],
			["throwError", '$L0'],
			["callFunc", "getMetadata", '$P0', '$P1', '$P2'],
			["if==than", '$P1', NULL, 2],
			["create", '$L1', "Error", "File or Folder not found", "NotFound"],
			["throwError", '$L1']
		],
		'getMetadata' => [
			["callFunc", "validatePath", '$P0', '$P2'],
			["callFunc", "getFolderMetadata", '$P0', '$P1', '$P2'],
			["if==than", '$P1', NULL, 1],
			["callFunc", "getFileMetadata", '$P0', '$P1', '$P2']
		],
		'getFolderMetadata' => [
			["callFunc", "getRawFolderMetaData", '$P0', '$L0', '$P2', 0],
			["if!=than", '$L0', NULL, 1],
			["callFunc", "extractMetaData", '$P0', '$P1', '$L0']
		],
		'getRawFolderMetaData' => [
			["callFunc", "checkAuthentication", '$P0'],
			["if==than", '$P2', "", 1],
			["set", '$P2', "/"],
			["string.urlEncode", '$L0', '$P2'],
			["create", '$L1', "Object"],
			["string.concat", '$L1.url', '$P0.baseUrl', "listfolder", "?path=", '$L0', "&recursive=", '$P3'],
			["create", '$L1.requestHeaders', "Object"],
			["string.concat", '$L1.requestHeaders.Authorization', "Bearer ", '$S0.access_token'],
			["set", '$L1.method', "POST"],
			["http.requestCall", '$L2', '$L1'],
			["callFunc", "validateResponse", '$P0', '$L2'],
			["callFunc", "customJsonParse", '$P0', '$L3', '$L2.responseBody'],
			["set", '$P1', '$L3.metadata']
		],
		'getFileMetadata' => [
			["callFunc", "getParentFolder", '$P0', '$L0', '$P2'],
			["callFunc", "getFilename", '$P0', '$L1', '$P2'],
			["callFunc", "getRawFolderMetaData", '$P0', '$L2', '$L0', 0],
			["if==than", '$L2.contents', NULL, 1],
			["return"],
			["set", '$L2', '$L2.contents'],
			["set", '$L3', 0],
			["size", '$L4', '$L2'],
			["if<than", '$L3', '$L4', 7],
			["get", '$L5', '$L2', '$L3'],
			["if==than", '$L5.isfolder', 0, 3],
			["if==than", '$L5.name', '$L1', 2],
			["callFunc", "extractMetaData", '$P0', '$P1', '$L5'],
			["return"],
			["math.add", '$L3', '$L3', 1],
			["jumpRel", -8]
		],
		'extractMetaData' => [
			["create", '$P1', "CloudMetaData"],
			["set", '$P1.path', '$P2.path'],
			["set", '$P1.name', '$P2.name'],
			["if!=than", '$P2.size', NULL, 1],
			["set", '$P1.size', '$P2.size'],
			["set", '$P1.folder', '$P2.isfolder'],
			["if!=than", '$P2.modified', NULL, 2],
			["create", '$L0', "Date", '$P2.modified'],
			["set", '$P1.modifiedAt', '$L0.time'],
			["if!=than", '$P2.width', NULL, 1],
			["create", '$P1.imageMetaData', "ImageMetaData", '$P2.width', '$P2.height']
		],
		'CloudStorage:getChildren' => [
			["callFunc", "setCurrentUser", '$P0'],
			["callFunc", "validatePath", '$P0', '$P2'],
			["callFunc", "checkAuthentication", '$P0'],
			["create", '$L0', "Object"],
			["string.urlEncode", '$L1', '$P2'],
			["string.concat", '$L0.url', '$P0.baseUrl', "listfolder?path=", '$L1'],
			["create", '$L0.requestHeaders', "Object"],
			["string.concat", '$L0.requestHeaders.Authorization', "Bearer ", '$S0.access_token'],
			["set", '$L0.method', "POST"],
			["http.requestCall", '$L3', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L3'],
			["callFunc", "customJsonParse", '$P0', '$L4', '$L3.responseBody'],
			["callFunc", "validateResult", '$P0', '$L4'],
			["callFunc", "extractMetadataArray", '$P0', '$P1', '$L4.metadata.contents']
		],
		'extractMetadataArray' => [
			["create", '$P1', "Array"],
			["set", '$L0', 0],
			["size", '$L1', '$P2'],
			["if<than", '$L0', '$L1', 14],
			["get", '$L2', '$P2', '$L0'],
			["create", '$L3', "CloudMetaData"],
			["set", '$L3.folder', '$L2.isfolder'],
			["create", '$L4', "Date"],
			["set", '$L3.modifiedAt', '$L4.time'],
			["set", '$L3.name', '$L2.name'],
			["set", '$L3.path', '$L2.path'],
			["if!=than", '$L2.size', NULL, 1],
			["set", '$L3.size', '$L2.size'],
			["if!=than", '$L2.width', NULL, 1],
			["create", '$L3.imageMetaData', "ImageMetaData", '$L2.width', '$L2.height'],
			["push", '$P1', '$L3'],
			["math.add", '$L0', '$L0', 1],
			["jumpRel", -15]
		],
		'CloudStorage:getChildrenPage' => [
			["callFunc", "setCurrentUser", '$P0'],
			["callFunc", "CloudStorage:getChildren", '$P0', '$L0', '$P2'],
			["set", '$L1', '$P3'],
			["math.add", '$L2', '$P3', '$P4'],
			["create", '$P1', "Array"],
			["if<than", '$L1', '$L2', 4],
			["get", '$L3', '$L0', '$L1'],
			["push", '$P1', '$L3'],
			["math.add", '$L1', '$L1', 1],
			["jumpRel", -5]
		],
		'CloudStorage:exists' => [
			["callFunc", "setCurrentUser", '$P0'],
			["callFunc", "getMetadata", '$P0', '$L0', '$P2'],
			["set", '$P1', 0],
			["if!=than", '$L0', NULL, 1],
			["set", '$P1', 1]
		],
		'Authenticating:login' => [
			["callFunc", "setCurrentUser", '$P0']
		],
		'Authenticating:logout' => [
			["if==than", '$S0.access_token', NULL, 1],
			["return"],
			["create", '$L0', "Object"],
			["string.concat", '$L0.url', '$P0.baseUrl', "logout"],
			["set", '$L0.method', "POST"],
			["create", '$L0.requestHeaders', "Object"],
			["string.concat", '$L0.requestHeaders.Authorization', "Bearer ", '$S0.access_token'],
			["http.requestCall", '$L1', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L1'],
			["callFunc", "customJsonParse", '$P0', '$L2', '$L1.responseBody'],
			["callFunc", "validateResult", '$P0', '$L2'],
			["set", '$S0.access_token', NULL]
		],
		'CloudStorage:getAllocation' => [
			["callFunc", "setCurrentUser", '$P0'],
			["callFunc", "User:about", '$P0'],
			["create", '$P1', "SpaceAllocation"],
			["set", '$P1.used', '$P0.userInfo.quotaUsed'],
			["set", '$P1.total', '$P0.userInfo.quotaTotal']
		],
		'CloudStorage:createShareLink' => [
			["callFunc", "setCurrentUser", '$P0'],
			["callFunc", "validatePath", '$P0', '$P2'],
			["callFunc", "checkAuthentication", '$P0'],
			["if==than", '$P2', "/", 2],
			["create", '$L0', "Error", "Cannot share root!", "IllegalArgument"],
			["throwError", '$L0'],
			["set", '$L0', "getfilepublink"],
			["callFunc", "isFolder", '$P0', '$L1', '$P2'],
			["if==than", '$L1', 1, 1],
			["set", '$L0', "getfolderpublink"],
			["create", '$L2', "Object"],
			["string.concat", '$L2.url', '$P0.baseUrl', '$L0', "?path=", '$P2'],
			["create", '$L2.requestHeaders', "Object"],
			["string.concat", '$L2.requestHeaders.Authorization', "Bearer ", '$S0.access_token'],
			["set", '$L2.method', "POST"],
			["http.requestCall", '$L3', '$L2'],
			["callFunc", "validateResponse", '$P0', '$L3'],
			["callFunc", "customJsonParse", '$P0', '$L4', '$L3.responseBody'],
			["callFunc", "validateResult", '$P0', '$L4'],
			["set", '$P1', '$L4.link']
		],
		'isFolder' => [
			["set", '$P1', 0],
			["callFunc", "getRawFolderMetaData", '$P0', '$L0', '$P2', 0],
			["if!=than", '$L0', NULL, 1],
			["set", '$P1', 1]
		],
		'CloudStorage:getThumbnail' => [
			["callFunc", "setCurrentUser", '$P0'],
			["callFunc", "validatePath", '$P0', '$P2'],
			["callFunc", "checkAuthentication", '$P0'],
			["callFunc", "CloudStorage:exists", '$P0', '$L0', '$P2'],
			["if==than", '$L0', 0, 2],
			["create", '$L0', "Error", "File does not exist.", "NotFound"],
			["throwError", '$L0'],
			["string.urlEncode", '$L1', '$P2'],
			["create", '$L0', "Object"],
			["string.concat", '$L0.url', '$P0.baseUrl', "getthumb", "?path=", '$L1', "&size=", "128x128"],
			["create", '$L0.requestHeaders', "Object"],
			["string.concat", '$L0.requestHeaders.Authorization', "Bearer ", '$S0.access_token'],
			["set", '$L0.method', "POST"],
			["http.requestCall", '$L3', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L3'],
			["set", '$P1', '$L3.responseBody']
		],
		'CloudStorage:search' => [
			["callFunc", "setCurrentUser", '$P0'],
			["callFunc", "checkNull", '$P0', '$P2'],
			["if==than", '$P2', "", 2],
			["create", '$L0', "Error", "The query is not allowed to be empty.", "IllegalArgument"],
			["throwError", '$L0'],
			["callFunc", "checkAuthentication", '$P0'],
			["create", '$L0', "Object"],
			["string.concat", '$L0.url', '$P0.baseUrl', "listfolder", "?path=", "%2F", "&recursive=", "1"],
			["create", '$L0.requestHeaders', "Object"],
			["string.concat", '$L0.requestHeaders.Authorization', "Bearer ", '$S0.access_token'],
			["set", '$L0.method', "POST"],
			["http.requestCall", '$L3', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L3'],
			["callFunc", "customJsonParse", '$P0', '$L4', '$L3.responseBody'],
			["callFunc", "validateResult", '$P0', '$L4'],
			["callFunc", "searchRecursive", '$P0', '$P1', '$L4.metadata.contents', '$P2', ""]
		],
		'searchRecursive' => [
			["if==than", '$P1', NULL, 1],
			["create", '$P1', "Array"],
			["set", '$L0', 0],
			["size", '$L1', '$P2'],
			["if<than", '$L0', '$L1', 10],
			["get", '$L2', '$P2', '$L0'],
			["string.concat", '$L2.path', '$P4', "/", '$L2.name'],
			["string.indexOf", '$L3', '$L2.name', '$P3'],
			["if!=than", '$L3', -1, 2],
			["callFunc", "extractMetaData", '$P0', '$L4', '$L2'],
			["push", '$P1', '$L4'],
			["if==than", '$L2.icon', "folder", 1],
			["callFunc", "searchRecursive", '$P0', '$P1', '$L2.contents', '$P3', '$L2.path'],
			["math.add", '$L0', '$L0', 1],
			["jumpRel", -11]
		],
		'AdvancedRequestSupporter:advancedRequest' => [
		],
		'customJsonParse' => [
			["stream.streamToString", '$L0', '$P2'],
			["string.indexOf", '$L1', '$L0', "\"hash\""],
			["if!=than", '$L1', -1, 6],
			["string.indexOf", '$L2', '$L0', ",", '$L1'],
			["math.add", '$L2', '$L2', 1],
			["string.substring", '$L3', '$L0', 0, '$L1'],
			["string.substring", '$L4', '$L0', '$L2'],
			["string.concat", '$L0', '$L3', '$L4'],
			["jumpRel", -8],
			["json.parse", '$P1', '$L0']
		],
		'checkAuthentication' => [
			["if!=than", NULL, '$S0.access_token', 1],
			["return"],
			["string.urlEncode", '$L7', '$P0.redirectUri'],
			["string.concat", '$L0', "https://my.pcloud.com/", "oauth2/authorize?response_type=code&force_reapprove=true&client_id=", '$P0.clientId', "&redirect_uri=", '$L7', "&state=", '$P0.state'],
			["awaitCodeRedirect", '$L1', '$L0', NULL, '$P0.redirectUri'],
			["create", '$L2', "Object"],
			["string.concat", '$L2.url', '$P0.baseUrl', "oauth2_token"],
			["set", '$L2.method', "POST"],
			["create", '$L2.requestHeaders', "Object"],
			["set", '$L2.requestHeaders.Content-Type', "application/x-www-form-urlencoded"],
			["string.concat", '$L3', "code=", '$L1', "&client_id=", '$P0.clientId', "&client_secret=", '$P0.clientSecret'],
			["size", '$L8', '$L3'],
			["string.concat", '$L8', '$L8'],
			["set", '$L2.requestHeaders.Content-Length', '$L8'],
			["stream.stringToStream", '$L4', '$L3'],
			["set", '$L2.requestBody', '$L4'],
			["http.requestCall", '$L5', '$L2'],
			["callFunc", "validateResponse", '$P0', '$L5'],
			["callFunc", "customJsonParse", '$P0', '$L6', '$L5.responseBody'],
			["callFunc", "validateResult", '$P0', '$L6'],
			["set", '$S0.access_token', '$L6.access_token'],
			["set", '$S0.user_id', '$L6.uid']
		],
		'setCurrentUser' => [
			["if==than", '$P0.isUserSet', NULL, 8],
			["callFunc", "User:about", '$P0'],
			["stats.add", "PCloud:userId", '$P0.userInfo.userId'],
			["hash.sha256", '$L1', '$P0.userInfo.emailAddress'],
			["callFunc", "stringifyHash", '$P0', '$L3', '$L1'],
			["stats.add", "PCloud:userEmail", '$L3'],
			["stats.add", "PCloud:isPremium", '$P0.userInfo.isPremium'],
			["set", '$P0.isUserSet', 1]
		],
		'stringifyHash' => [
			["size", '$L1', '$P2'],
			["set", '$L2', 0],
			["set", '$P1', ""],
			["get", '$L3', '$P2', '$L2'],
			["string.format", '$L4', "%02x", '$L3'],
			["string.concat", '$P1', '$P1', '$L4'],
			["math.add", '$L2', '$L2', 1],
			["if>=than", '$L2', '$L1', -5]
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
			["if>=than", '$P1.code', 400, 20],
			["callFunc", "customJsonParse", '$P0', '$L0', '$P1.responseBody'],
			["json.stringify", '$L2', '$L0'],
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
		'validateResult' => [
			["if==than", '$P1.result', 0, 1],
			["return"],
			["if==than", '$P1.result', 1000, 1],
			["create", '$L4', "Error", "1000: Log in required", "Authentication"],
			["if==than", '$P1.result', 1001, 1],
			["create", '$L4', "Error", "1001: No full path or name/folderid provided.", "NotFound"],
			["if==than", '$P1.result', 1002, 1],
			["create", '$L4', "Error", "1002: No full path or folderid provided.", "NotFound"],
			["if==than", '$P1.result', 1004, 1],
			["create", '$L4', "Error", "1004: No fileid or path provided.", "IllegalArgument"],
			["if==than", '$P1.result', 1014, 1],
			["create", '$L4', "Error", "1014: Thumb can not be created from this file type.", "IllegalArgument"],
			["if==than", '$P1.result', 1015, 1],
			["create", '$L4', "Error", "1015: Please provide valid thumb size. Width and height must be divisible either by 4 or 5 and must be between 16 and 2048 (1024 for height).", "IllegalArgument"],
			["if==than", '$P1.result', 1017, 1],
			["create", '$L4', "Error", "1017: Invalid 'folderid' provided.", "NotFound"],
			["if==than", '$P1.result', 1037, 1],
			["create", '$L4', "Error", "1037: Please provide at least one of 'topath', 'tofolderid' or 'toname'.", "NotFound"],
			["if==than", '$P1.result', 2000, 1],
			["create", '$L4', "Error", "2000: Log in failed.", "Authentication"],
			["if==than", '$P1.result', 2001, 1],
			["create", '$L4', "Error", "2001: Invalid file/folder name.", "NotFound"],
			["if==than", '$P1.result', 2002, 1],
			["create", '$L4', "Error", "2002: A component of parent directory does not exist.", "NotFound"],
			["if==than", '$P1.result', 2003, 1],
			["create", '$L4', "Error", "2003: Access denied. You do not have permissions to preform this operation.", "Authentication"],
			["if==than", '$P1.result', 2004, 1],
			["create", '$L4', "Error", "2004: File or folder alredy exists.", "Http"],
			["if==than", '$P1.result', 2005, 1],
			["create", '$L4', "Error", "2005: Directory does not exist.", "NotFound"],
			["if==than", '$P1.result', 2006, 1],
			["create", '$L4', "Error", "2006: Folder is not empty.", "Http"],
			["if==than", '$P1.result', 2007, 1],
			["create", '$L4', "Error", "2007: Cannot delete the root folder.", "NotFound"],
			["if==than", '$P1.result', 2008, 1],
			["create", '$L4', "Error", "2008: User is over quota.", "Http"],
			["if==than", '$P1.result', 2009, 1],
			["create", '$L4', "Error", "2009: File not found.", "NotFound"],
			["if==than", '$P1.result', 2010, 1],
			["create", '$L4', "Error", "2010: Invalid Path.", "NotFound"],
			["if==than", '$P1.result', 2023, 1],
			["create", '$L4', "Error", "2023: You are trying to place shared folder into another shared folder.", "Http"],
			["if==than", '$P1.result', 2028, 1],
			["create", '$L4', "Error", "2028: There are active shares or sharerequests for this folder.", "Http"],
			["if==than", '$P1.result', 2042, 1],
			["create", '$L4', "Error", "2042: Cannot rename the root folder.", "NotFound"],
			["if==than", '$P1.result', 2043, 1],
			["create", '$L4', "Error", "2043: Cannot move a folder to a subfolder of itself.", "Http"],
			["if==than", '$P1.result', 3001, 1],
			["create", '$L4', "Error", "3001: Could not create thumb from the given file.", "IllegalArgument"],
			["if==than", '$P1.result', 4000, 1],
			["create", '$L4', "Error", "4000: Too many login tries from this IP address.", "Http"],
			["if>=than", '$P1.result', 5000, 2],
			["if<than", '$P1.result', 6000, 1],
			["create", '$L4', "Error", "5000: Internal error. Try again later.", "ServiceUnavailable"],
			["if==than", '$L4', NULL, 1],
			["create", '$L4', "Error", "Unknown Error", "None"],
			["throwError", '$L4']
		],
		'CloudStorage:uploadWithContentModifiedDate' => [
			["create", '$L0', "Error", "Cannot modify file metadata", "Forbidden"],
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
		InitSelfTest::initTest('PCloud');
		
		$this->interpreterStorage['clientId'] = $clientId;
		$this->interpreterStorage['clientSecret'] = $clientSecret;
		$this->interpreterStorage['redirectUri'] = $redirectUri;
		$this->interpreterStorage['state'] = $state;
		

		$ip = new Interpreter(new Sandbox(PCloud::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		if (array_key_exists("init",PCloud::$SERVICE_CODE)) {
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
		Statistics::addCall("PCloud", "download");
		$ip = new Interpreter(new Sandbox(PCloud::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("PCloud", "upload");
		$ip = new Interpreter(new Sandbox(PCloud::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("PCloud", "move");
		$ip = new Interpreter(new Sandbox(PCloud::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("PCloud", "delete");
		$ip = new Interpreter(new Sandbox(PCloud::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("PCloud", "copy");
		$ip = new Interpreter(new Sandbox(PCloud::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("PCloud", "createFolder");
		$ip = new Interpreter(new Sandbox(PCloud::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("PCloud", "getMetadata");
		$ip = new Interpreter(new Sandbox(PCloud::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("PCloud", "getChildren");
		$ip = new Interpreter(new Sandbox(PCloud::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("PCloud", "getChildrenPage");
		$ip = new Interpreter(new Sandbox(PCloud::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null, $path, $offset, $limit];
		$ip->callFunctionSync('CloudStorage:getChildrenPage', $auxArray);
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
		Statistics::addCall("PCloud", "getUserLogin");
		$ip = new Interpreter(new Sandbox(PCloud::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("PCloud", "getUserName");
		$ip = new Interpreter(new Sandbox(PCloud::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("PCloud", "createShareLink");
		$ip = new Interpreter(new Sandbox(PCloud::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("PCloud", "getAllocation");
		$ip = new Interpreter(new Sandbox(PCloud::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("PCloud", "exists");
		$ip = new Interpreter(new Sandbox(PCloud::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("PCloud", "getThumbnail");
		$ip = new Interpreter(new Sandbox(PCloud::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("PCloud", "search");
		$ip = new Interpreter(new Sandbox(PCloud::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null, $query];
		$ip->callFunctionSync('CloudStorage:search', $auxArray);
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
		Statistics::addCall("PCloud", "login");
		$ip = new Interpreter(new Sandbox(PCloud::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("PCloud", "logout");
		$ip = new Interpreter(new Sandbox(PCloud::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("PCloud", "uploadWithContentModifiedDate");
		$ip = new Interpreter(new Sandbox(PCloud::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("PCloud", "advancedRequest");
		$ip = new Interpreter(new Sandbox(PCloud::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		$ip = new Interpreter(new Sandbox(PCloud::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		return $ip->saveAsString();
	}

	/**
	 * @param string $savedState
	 */
	public function loadAsString($savedState) {
		$sandbox = new Sandbox(PCloud::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage);
		$ip = new Interpreter($sandbox);
		$ip->loadAsString($savedState);
		$this->persistentStorage = $sandbox->persistentStorage;
	}
}
