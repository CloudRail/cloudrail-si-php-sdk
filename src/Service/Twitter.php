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

use CloudRail\Interfaces\Profile;
use CloudRail\Type\DateOfBirth;
use CloudRail\Interfaces\Social;

use CloudRail\Interfaces\AdvancedRequestSupporter;
use CloudRail\Type\AdvancedRequestSpecification;
use CloudRail\Type\AdvancedRequestResponse;
use CloudRail\Type\CloudRailError;

class Twitter implements Profile, Social, AdvancedRequestSupporter
{
	private static $SERVICE_CODE = [
		'Authenticating:login' => [
			["callFunc", "checkAuthentication", '$P0']
		],
		'Authenticating:logout' => [
			["delete", '$S0.oauthToken'],
			["delete", '$S0.oauthTokenSecret'],
			["set", '$P0.userInfo', NULL]
		],
		'Social:postUpdate' => [
			["if==than", '$P1', NULL, 2],
			["create", '$L0', "Error", "The status is not allowed to be null.", "IllegalArgument"],
			["throwError", '$L0'],
			["size", '$L0', '$P1'],
			["if>than", '$L0', 140, 2],
			["create", '$L0', "Error", "The status is not allowed to contain more than 140 characters.", "IllegalArgument"],
			["throwError", '$L0'],
			["callFunc", "checkAuthentication", '$P0'],
			["callFunc", "post", '$P0', '$P1']
		],
		'Social:postImage' => [
			["if==than", '$P1', NULL, 2],
			["create", '$L0', "Error", "The message is not allowed to be null", "IllegalArgument"],
			["throwError", '$L0'],
			["if==than", '$P2', NULL, 2],
			["create", '$L0', "Error", "The image is not allowed to be null", "IllegalArgument"],
			["throwError", '$L0'],
			["callFunc", "checkAuthentication", '$P0'],
			["create", '$L0', "Object"],
			["set", '$L0.method', "POST"],
			["set", '$L0.url', "https://upload.twitter.com/1.1/media/upload.json"],
			["create", '$L1', "Array"],
			["push", '$L1', "oauth_consumer_key"],
			["push", '$L1', "oauth_nonce"],
			["push", '$L1', "oauth_signature_method"],
			["push", '$L1', "oauth_timestamp"],
			["push", '$L1', "oauth_token"],
			["push", '$L1', "oauth_version"],
			["callFunc", "oAuth1:signRequest", '$P0', '$L0', '$L1'],
			["create", '$L2', "String", "AaB03xh3u2hfiugiuhuo34gwhoughugheruighui34giuwrgh"],
			["string.concat", '$L0.requestHeaders.Content-Type', "multipart/form-data; boundary=", '$L2'],
			["string.concat", '$L3', "--", '$L2', "\r\n"],
			["string.concat", '$L3', '$L3', "Content-Disposition: form-data; name=\"media\"\r\n"],
			["string.concat", '$L3', '$L3', "Content-Type: application/octet-stream\r\n\r\n"],
			["stream.stringToStream", '$L3', '$L3'],
			["string.concat", '$L4', "\r\n--", '$L2', "--"],
			["stream.stringToStream", '$L4', '$L4'],
			["stream.makeJoinedStream", '$L0.requestBody', '$L3', '$P2', '$L4'],
			["http.requestCall", '$L5', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L5'],
			["json.parse", '$L6', '$L5.responseBody'],
			["set", '$L7', '$L6.media_id_string'],
			["callFunc", "post", '$P0', '$P1', '$L7']
		],
		'Social:postVideo' => [
			["if==than", '$P1', NULL, 2],
			["create", '$L0', "Error", "The message is not allowed to be null", "IllegalArgument"],
			["throwError", '$L0'],
			["if==than", '$P2', NULL, 2],
			["create", '$L0', "Error", "The image is not allowed to be null", "IllegalArgument"],
			["throwError", '$L0'],
			["callFunc", "checkAuthentication", '$P0'],
			["callFunc", "initUpload", '$P0', '$L0', '$P3', '$P4'],
			["callFunc", "performUpload", '$P0', '$L0', '$P2', '$P3'],
			["callFunc", "finishUpload", '$P0', '$L0'],
			["callFunc", "post", '$P0', '$P1', '$L0']
		],
		'Social:getConnections' => [
			["callFunc", "checkAuthentication", '$P0'],
			["create", '$L0', "Object"],
			["set", '$L0.method', "GET"],
			["set", '$L0.url', "https://api.twitter.com/1.1/friends/ids.json"],
			["create", '$L1', "Array"],
			["push", '$L1', "count"],
			["push", '$L1', "oauth_consumer_key"],
			["push", '$L1', "oauth_nonce"],
			["push", '$L1', "oauth_signature_method"],
			["push", '$L1', "oauth_timestamp"],
			["push", '$L1', "oauth_token"],
			["push", '$L1', "oauth_version"],
			["push", '$L1', "stringify_ids"],
			["create", '$L3', "Object"],
			["set", '$L3.count', "5000"],
			["set", '$L3.stringify_ids', "true"],
			["callFunc", "oAuth1:signRequest", '$P0', '$L0', '$L1', '$L3'],
			["string.concat", '$L0.url', '$L0.url', "?count=5000&stringify_ids=true"],
			["http.requestCall", '$L1', '$L0'],
			["json.parse", '$L2', '$L1.responseBody'],
			["callFunc", "validateResponse", '$P0', '$L1'],
			["set", '$L3', '$L2.ids'],
			["create", '$P1', "Array"],
			["create", '$L4', "Number", 0],
			["size", '$L5', '$L3'],
			["if<than", '$L4', '$L5', 5],
			["get", '$L6', '$L3', '$L4'],
			["string.concat", '$L6', "twitter-", '$L6'],
			["push", '$P1', '$L6'],
			["math.add", '$L4', '$L4', 1],
			["jumpRel", -6]
		],
		'Profile:getIdentifier' => [
			["callFunc", "User:getInfo", '$P0'],
			["string.concat", '$P1', "twitter-", '$P0.userInfo.id']
		],
		'Profile:getFullName' => [
			["callFunc", "User:getInfo", '$P0'],
			["set", '$P1', '$P0.userInfo.name']
		],
		'Profile:getEmail' => [
			["callFunc", "User:getInfo", '$P0'],
			["set", '$P1', '$P0.userInfo.email']
		],
		'Profile:getGender' => [
			["callFunc", "User:getInfo", '$P0'],
			["set", '$P1', '$P0.userInfo.gender']
		],
		'Profile:getDescription' => [
			["callFunc", "User:getInfo", '$P0'],
			["set", '$P1', '$P0.userInfo.description']
		],
		'Profile:getDateOfBirth' => [
			["callFunc", "User:getInfo", '$P0'],
			["set", '$P1', '$P0.userInfo.dateOfBirth']
		],
		'Profile:getLocale' => [
			["callFunc", "User:getInfo", '$P0'],
			["set", '$P1', '$P0.userInfo.locale']
		],
		'Profile:getPictureURL' => [
			["callFunc", "User:getInfo", '$P0'],
			["set", '$P1', '$P0.userInfo.pictureURL']
		],
		'User:getInfo' => [
			["if!=than", '$P0.userInfo', NULL, 4],
			["create", '$L0', "Date"],
			["math.add", '$L0', '$L0.Time', -10000],
			["if>than", '$P0.userInfo.lastUpdate', '$L0', 1],
			["return"],
			["callFunc", "User:sendInfoRequest", '$P0']
		],
		'User:sendInfoRequest' => [
			["callFunc", "checkAuthentication", '$P0'],
			["create", '$L0', "Object"],
			["set", '$L0.method', "GET"],
			["set", '$L0.url', "https://api.twitter.com/1.1/account/verify_credentials.json"],
			["create", '$L1', "Array"],
			["push", '$L1', "oauth_consumer_key"],
			["push", '$L1', "oauth_nonce"],
			["push", '$L1', "oauth_signature_method"],
			["push", '$L1', "oauth_timestamp"],
			["push", '$L1', "oauth_token"],
			["push", '$L1', "oauth_version"],
			["callFunc", "oAuth1:signRequest", '$P0', '$L0', '$L1'],
			["http.requestCall", '$L1', '$L0'],
			["json.parse", '$L2', '$L1.responseBody'],
			["callFunc", "validateResponse", '$P0', '$L1'],
			["create", '$P0.userInfo', "Object"],
			["create", '$L3', "Date"],
			["set", '$P0.userInfo.lastUpdate', '$L3.Time'],
			["set", '$P0.userInfo.id', '$L2.id_str'],
			["set", '$P0.userInfo.name', '$L2.name'],
			["set", '$P0.userInfo.email', NULL],
			["set", '$P0.userInfo.gender', NULL],
			["set", '$P0.userInfo.description', '$L2.description'],
			["create", '$P0.userInfo.dateOfBirth', "DateOfBirth"],
			["set", '$P0.userInfo.locale', '$L2.lang'],
			["set", '$P0.userInfo.pictureURL', '$L2.profile_image_url']
		],
		'initUpload' => [
			["create", '$L0', "Object"],
			["set", '$L0.method', "POST"],
			["string.concat", '$L0.url', "https://upload.twitter.com/1.1/media/upload.json"],
			["create", '$L1', "Array"],
			["push", '$L1', "command"],
			["push", '$L1', "media_type"],
			["push", '$L1', "oauth_consumer_key"],
			["push", '$L1', "oauth_nonce"],
			["push", '$L1', "oauth_signature_method"],
			["push", '$L1', "oauth_timestamp"],
			["push", '$L1', "oauth_token"],
			["push", '$L1', "oauth_version"],
			["push", '$L1', "total_bytes"],
			["create", '$L2', "Object"],
			["set", '$L2.command', "INIT"],
			["set", '$L2.media_type', '$P3'],
			["string.concat", '$L2.total_bytes', '$P2', ""],
			["callFunc", "oAuth1:signRequest", '$P0', '$L0', '$L1', '$L2'],
			["string.urlEncode", '$L3', '$P3'],
			["string.concat", '$L3', "command=INIT&media_type=", '$L3', "&total_bytes=", '$P2'],
			["stream.stringToStream", '$L0.requestBody', '$L3'],
			["set", '$L0.requestHeaders.Content-Type', "application/x-www-form-urlencoded"],
			["http.requestCall", '$L4', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L4'],
			["json.parse", '$L5', '$L4.responseBody'],
			["set", '$P1', '$L5.media_id_string']
		],
		'performUpload' => [
			["create", '$L20', "Number", 2097152],
			["create", '$L21', "Number", 0],
			["create", '$L22', "Number", 1],
			["if<than", '$L22', '$P3', 35],
			["create", '$L0', "Object"],
			["set", '$L0.method', "POST"],
			["string.concat", '$L0.url', "https://upload.twitter.com/1.1/media/upload.json"],
			["create", '$L1', "Array"],
			["push", '$L1', "oauth_consumer_key"],
			["push", '$L1', "oauth_nonce"],
			["push", '$L1', "oauth_signature_method"],
			["push", '$L1', "oauth_timestamp"],
			["push", '$L1', "oauth_token"],
			["push", '$L1', "oauth_version"],
			["callFunc", "oAuth1:signRequest", '$P0', '$L0', '$L1'],
			["create", '$L2', "String", "AaB03xh3u2hfiugiuhuo34gwhoughugheruighui34giuwrgh"],
			["string.concat", '$L3', "--", '$L2', "\r\n"],
			["string.concat", '$L3', '$L3', "Content-Disposition: form-data; name=\"command\"\r\n\r\n"],
			["string.concat", '$L3', '$L3', "APPEND\r\n"],
			["string.concat", '$L3', '$L3', "--", '$L2', "\r\n"],
			["string.concat", '$L3', '$L3', "Content-Disposition: form-data; name=\"media_id\"\r\n\r\n"],
			["string.concat", '$L3', '$L3', '$P1', "\r\n"],
			["string.concat", '$L3', '$L3', "--", '$L2', "\r\n"],
			["string.concat", '$L3', '$L3', "Content-Disposition: form-data; name=\"segment_index\"\r\n\r\n"],
			["string.concat", '$L3', '$L3', '$L21', "\r\n"],
			["string.concat", '$L3', '$L3', "--", '$L2', "\r\n"],
			["string.concat", '$L3', '$L3', "Content-Disposition: form-data; name=\"media\"\r\n"],
			["string.concat", '$L3', '$L3', "Content-Type: application/octet-stream\r\n\r\n"],
			["stream.stringToStream", '$L3', '$L3'],
			["stream.makeLimitedStream", '$L23', '$P2', '$L20'],
			["string.concat", '$L4', "\r\n--", '$L2', "--"],
			["stream.stringToStream", '$L4', '$L4'],
			["stream.makeJoinedStream", '$L0.requestBody', '$L3', '$L23', '$L4'],
			["string.concat", '$L0.requestHeaders.Content-Type', "multipart/form-data; boundary=", '$L2'],
			["http.requestCall", '$L5', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L5'],
			["math.add", '$L22', '$L22', '$L20'],
			["math.add", '$L21', '$L21', 1],
			["jumpRel", -36]
		],
		'finishUpload' => [
			["create", '$L0', "Object"],
			["set", '$L0.method', "POST"],
			["string.concat", '$L0.url', "https://upload.twitter.com/1.1/media/upload.json"],
			["create", '$L1', "Array"],
			["push", '$L1', "command"],
			["push", '$L1', "media_id"],
			["push", '$L1', "oauth_consumer_key"],
			["push", '$L1', "oauth_nonce"],
			["push", '$L1', "oauth_signature_method"],
			["push", '$L1', "oauth_timestamp"],
			["push", '$L1', "oauth_token"],
			["push", '$L1', "oauth_version"],
			["create", '$L2', "Object"],
			["set", '$L2.command', "FINALIZE"],
			["set", '$L2.media_id', '$P1'],
			["callFunc", "oAuth1:signRequest", '$P0', '$L0', '$L1', '$L2'],
			["string.concat", '$L3', "command=FINALIZE&media_id=", '$P1'],
			["stream.stringToStream", '$L0.requestBody', '$L3'],
			["set", '$L0.requestHeaders.Content-Type', "application/x-www-form-urlencoded"],
			["http.requestCall", '$L4', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L4']
		],
		'post' => [
			["string.urlEncode", '$L3', '$P1'],
			["callFunc", "urlEncode", '$P0', '$L5', '$L3'],
			["create", '$L0', "Object"],
			["set", '$L0.method', "POST"],
			["string.concat", '$L0.url', "https://api.twitter.com/1.1/statuses/update.json"],
			["create", '$L1', "Array"],
			["if!=than", '$P2', NULL, 1],
			["push", '$L1', "media_ids"],
			["push", '$L1', "oauth_consumer_key"],
			["push", '$L1', "oauth_nonce"],
			["push", '$L1', "oauth_signature_method"],
			["push", '$L1', "oauth_timestamp"],
			["push", '$L1', "oauth_token"],
			["push", '$L1', "oauth_version"],
			["push", '$L1', "status"],
			["create", '$L4', "Object"],
			["set", '$L4.status', '$P1'],
			["if!=than", '$P2', NULL, 1],
			["set", '$L4.media_ids', '$P2'],
			["callFunc", "oAuth1:signRequest", '$P0', '$L0', '$L1', '$L4'],
			["string.concat", '$L21', "status=", '$L5'],
			["if!=than", '$P2', NULL, 1],
			["string.concat", '$L21', '$L21', "&media_ids=", '$P2'],
			["stream.stringToStream", '$L0.requestBody', '$L21'],
			["set", '$L0.requestHeaders.Content-Type', "application/x-www-form-urlencoded"],
			["http.requestCall", '$L1', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L1']
		],
		'AdvancedRequestSupporter:advancedRequest' => [
			["create", '$L0', "Object"],
			["create", '$L0.url', "String"],
			["if!=than", '$P2.appendBaseUrl', 0, 1],
			["set", '$L0.url', "https://api.twitter.com/1.1"],
			["string.concat", '$L0.url', '$L0.url', '$P2.url'],
			["set", '$L0.requestHeaders', '$P2.headers'],
			["set", '$L0.method', '$P2.method'],
			["if==than", '$P2.headers.Content-Type', "application/x-www-form-urlencoded", 3],
			["stream.streamToString", '$L10', '$P2.body'],
			["stream.stringToStream", '$L0.requestBody', '$L10'],
			["jumpRel", 1],
			["set", '$L0.requestBody', '$P2.body'],
			["if!=than", '$P2.appendAuthorization', 0, 21],
			["callFunc", "checkAuthentication", '$P0'],
			["string.split", '$L2', '$P2.url', "\\?", 2],
			["size", '$L3', '$L2'],
			["create", '$L4', "String"],
			["if==than", '$L3', 2, 1],
			["get", '$L4', '$L2', 1],
			["if==than", '$P2.headers.Content-Type', "application/x-www-form-urlencoded", 4],
			["size", '$L6', '$L4'],
			["if>than", '$L6', 0, 1],
			["string.concat", '$L4', '$L4', "&"],
			["string.concat", '$L4', '$L4', '$L10'],
			["callFunc", "extractParameters", '$P0', '$L6', '$L4'],
			["object.getKeyArray", '$L5', '$L6'],
			["push", '$L5', "oauth_consumer_key"],
			["push", '$L5', "oauth_nonce"],
			["push", '$L5', "oauth_signature_method"],
			["push", '$L5', "oauth_timestamp"],
			["push", '$L5', "oauth_token"],
			["push", '$L5', "oauth_version"],
			["array.sort", '$L5', '$L5'],
			["callFunc", "oAuth1:signRequest", '$P0', '$L0', '$L5', '$L6'],
			["http.requestCall", '$L1', '$L0'],
			["if!=than", '$P2.checkErrors', 0, 1],
			["callFunc", "validateResponse", '$P0', '$L1'],
			["create", '$P1', "AdvancedRequestResponse"],
			["set", '$P1.status', '$L1.code'],
			["set", '$P1.headers', '$L1.responseHeaders'],
			["set", '$P1.body', '$L1.responseBody']
		],
		'extractParameters' => [
			["create", '$P1', "Object"],
			["size", '$L0', '$P2'],
			["if==than", '$L0', 0, 1],
			["return"],
			["string.split", '$L1', '$P2', "&"],
			["size", '$L2', '$L1'],
			["set", '$L3', 0],
			["if<than", '$L3', '$L2', 7],
			["get", '$L4', '$L1', '$L3'],
			["string.split", '$L5', '$L4', "=", 2],
			["get", '$L6', '$L5', 0],
			["get", '$L7', '$L5', 1],
			["set", '$P1', '$L7', '$L6'],
			["math.add", '$L3', '$L3', 1],
			["jumpRel", -8]
		],
		'oAuth1:signRequest' => [
			["if==than", '$P1.requestHeaders', NULL, 1],
			["create", '$P1.requestHeaders', "Object"],
			["create", '$L0', "Object"],
			["set", '$L0.oauth_consumer_key', '$P0.clientId'],
			["callFunc", "oAuth1:generateNonce", '$L0.oauth_nonce'],
			["set", '$L0.oauth_signature_method', "HMAC-SHA1"],
			["create", '$L1', "Date"],
			["math.multiply", '$L1', '$L1.Time', 0.001],
			["math.floor", '$L1', '$L1'],
			["string.format", '$L0.oauth_timestamp', "%d", '$L1'],
			["set", '$L0.oauth_token', '$S0.oauthToken'],
			["set", '$L0.oauth_version', "1.0"],
			["string.split", '$L2', '$P1.url', "\\?", 2],
			["get", '$L2', '$L2', 0],
			["string.urlEncode", '$L2', '$L2'],
			["string.concat", '$L1', '$P1.method', "&", '$L2', "&"],
			["set", '$L2', ""],
			["set", '$L3', 0],
			["size", '$L4', '$P2'],
			["if<than", '$L3', '$L4', 15],
			["get", '$L5', '$P2', '$L3'],
			["if==than", '$L5', "oauth_callback", 1],
			["set", '$L0.oauth_callback', '$P0.redirectUri'],
			["get", '$L6', '$L0', '$L5'],
			["if>than", '$L3', 0, 1],
			["string.concat", '$L2', '$L2', "&"],
			["if==than", '$L6', NULL, 1],
			["get", '$L6', '$P3', '$L5'],
			["string.urlEncode", '$L6', '$L6'],
			["if==than", '$L5', "status", 2],
			["callFunc", "urlEncode", '$P0', '$L20', '$L6'],
			["set", '$L6', '$L20'],
			["string.concat", '$L2', '$L2', '$L5', "=", '$L6'],
			["math.add", '$L3', '$L3', 1],
			["jumpRel", -16],
			["string.urlEncode", '$L2', '$L2'],
			["string.concat", '$L1', '$L1', '$L2'],
			["set", '$L2', '$S0.oauthTokenSecret'],
			["if==than", '$L2', NULL, 1],
			["set", '$L2', ""],
			["string.concat", '$L2', '$P0.clientSecret', "&", '$L2'],
			["crypt.hmac.sha1", '$L2', '$L2', '$L1'],
			["array.uint8ToBase64", '$L2', '$L2'],
			["string.urlEncode", '$L2', '$L2'],
			["set", '$L0.oauth_signature', '$L2'],
			["set", '$L2', "OAuth "],
			["if!=than", '$L0.oauth_callback', NULL, 2],
			["string.urlEncode", '$L3', '$L0.oauth_callback'],
			["string.concat", '$L2', '$L2', "oauth_callback", "=\"", '$L3', "\"", ", "],
			["string.concat", '$L2', '$L2', "oauth_consumer_key", "=\"", '$L0.oauth_consumer_key', "\""],
			["string.concat", '$L2', '$L2', ", ", "oauth_nonce", "=\"", '$L0.oauth_nonce', "\""],
			["string.concat", '$L2', '$L2', ", ", "oauth_signature", "=\"", '$L0.oauth_signature', "\""],
			["string.concat", '$L2', '$L2', ", ", "oauth_signature_method", "=\"", '$L0.oauth_signature_method', "\""],
			["string.concat", '$L2', '$L2', ", ", "oauth_timestamp", "=\"", '$L0.oauth_timestamp', "\""],
			["if!=than", '$L0.oauth_token', NULL, 1],
			["string.concat", '$L2', '$L2', ", ", "oauth_token", "=\"", '$L0.oauth_token', "\""],
			["string.concat", '$L2', '$L2', ", ", "oauth_version", "=\"", '$L0.oauth_version', "\""],
			["set", '$P1.requestHeaders.Authorization', '$L2']
		],
		'oAuth1:generateNonce' => [
			["create", '$L0', "Date"],
			["string.format", '$L0', "%d", '$L0.Time'],
			["hash.md5", '$L0', '$L0'],
			["size", '$L1', '$L0'],
			["set", '$L2', 0],
			["set", '$P0', ""],
			["get", '$L3', '$L0', '$L2'],
			["string.format", '$L4', "%02x", '$L3'],
			["string.concat", '$P0', '$P0', '$L4'],
			["math.add", '$L2', '$L2', 1],
			["if>=than", '$L2', '$L1', -5]
		],
		'urlEncode' => [
			["string.split", '$L0', '$P2', "\\+"],
			["size", '$L1', '$L0'],
			["create", '$L2', "Number"],
			["set", '$L2', 0],
			["create", '$L4', "String"],
			["if<than", '$L2', '$L1', 7],
			["get", '$L5', '$L0', '$L2'],
			["if==than", '$L2', 0, 2],
			["set", '$L4', '$L5'],
			["jumpRel", 1],
			["string.concat", '$L4', '$L4', "%20", '$L5'],
			["math.add", '$L2', '$L2', 1],
			["jumpRel", -8],
			["set", '$P1', '$L4']
		],
		'checkAuthentication' => [
			["if!=than", '$S0.oauthToken', NULL, 2],
			["if!=than", '$S0.oauthTokenSecret', NULL, 1],
			["return"],
			["callFunc", "authenticate", '$P0']
		],
		'authenticate' => [
			["create", '$L0', "Object"],
			["set", '$L0.method', "POST"],
			["set", '$L0.url', "https://api.twitter.com/oauth/request_token"],
			["create", '$L1', "Array"],
			["push", '$L1', "oauth_callback"],
			["push", '$L1', "oauth_consumer_key"],
			["push", '$L1', "oauth_nonce"],
			["push", '$L1', "oauth_signature_method"],
			["push", '$L1', "oauth_timestamp"],
			["push", '$L1', "oauth_version"],
			["callFunc", "oAuth1:signRequest", '$P0', '$L0', '$L1'],
			["http.requestCall", '$L1', '$L0'],
			["if!=than", '$L1.code', 200, 3],
			["stream.streamToString", '$L2', '$L1.responseBody'],
			["create", '$L3', "Error", '$L2', "Authentication"],
			["throwError", '$L3'],
			["stream.streamToString", '$L2', '$L1.responseBody'],
			["string.split", '$L2', '$L2', "&"],
			["set", '$L3', 0],
			["size", '$L4', '$L2'],
			["create", '$L0', "Object"],
			["if<than", '$L3', '$L4', 8],
			["get", '$L5', '$L2', '$L3'],
			["string.split", '$L6', '$L5', "=", 2],
			["get", '$L7', '$L6', 0],
			["get", '$L8', '$L6', 1],
			["string.urlDecode", '$L8', '$L8'],
			["set", '$L0', '$L8', '$L7'],
			["math.add", '$L3', '$L3', 1],
			["jumpRel", -9],
			["string.concat", '$L3', "https://api.twitter.com/oauth/authenticate?oauth_token=", '$L0.oauth_token'],
			["create", '$L4', "Array"],
			["push", '$L4', "oauth_token"],
			["push", '$L4', "oauth_verifier"],
			["awaitCodeRedirect", '$L3', '$L3', '$L4', '$P0.redirectUri'],
			["set", '$S0.oauthToken', '$L3.oauth_token'],
			["create", '$L4', "Object"],
			["set", '$L4.method', "POST"],
			["set", '$L4.url', "https://api.twitter.com/oauth/access_token"],
			["create", '$L4.requestHeaders', "Object"],
			["set", '$L4.requestHeaders.Content-Type', "application/x-www-form-urlencoded"],
			["string.urlEncode", '$L5', '$L3.oauth_verifier'],
			["string.concat", '$L5', "oauth_verifier=", '$L5'],
			["stream.stringToStream", '$L4.requestBody', '$L5'],
			["create", '$L6', "Object"],
			["set", '$L6.oauth_verifier', '$L3.oauth_verifier'],
			["create", '$L7', "Array"],
			["push", '$L7', "oauth_callback"],
			["push", '$L7', "oauth_consumer_key"],
			["push", '$L7', "oauth_nonce"],
			["push", '$L7', "oauth_signature_method"],
			["push", '$L7', "oauth_timestamp"],
			["push", '$L7', "oauth_verifier"],
			["push", '$L7', "oauth_version"],
			["callFunc", "oAuth1:signRequest", '$P0', '$L4', '$L7', '$L6'],
			["http.requestCall", '$L9', '$L4'],
			["if!=than", '$L9.code', 200, 3],
			["stream.streamToString", '$L10', '$L9.responseBody'],
			["create", '$L11', "Error", '$L10', "Authentication"],
			["throwError", '$L11'],
			["stream.streamToString", '$L10', '$L9.responseBody'],
			["string.split", '$L10', '$L10', "&"],
			["set", '$L11', 0],
			["size", '$L12', '$L10'],
			["create", '$L13', "Object"],
			["if<than", '$L11', '$L12', 8],
			["get", '$L14', '$L10', '$L11'],
			["string.split", '$L15', '$L14', "=", 2],
			["get", '$L16', '$L15', 0],
			["get", '$L17', '$L15', 1],
			["string.urlDecode", '$L17', '$L17'],
			["set", '$L13', '$L17', '$L16'],
			["math.add", '$L11', '$L11', 1],
			["jumpRel", -9],
			["string.urlDecode", '$S0.oauthToken', '$L13.oauth_token'],
			["string.urlDecode", '$S0.oauthTokenSecret', '$L13.oauth_token_secret']
		],
		'validateResponse' => [
			["if>=than", '$P1.code', 400, 3],
			["stream.streamToString", '$L2', '$P1.responseBody'],
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
	 */
	public function __construct(callable $redirectReceiver, string $clientId, string $clientSecret, string $redirectUri)
	{
		$this->interpreterStorage = array();
		$this->instanceDependencyStorage = ["redirectReceiver" => $redirectReceiver];
		$this->persistentStorage = array(array());
		InitSelfTest::initTest('Twitter');
		
		$this->interpreterStorage['clientId'] = $clientId;
		$this->interpreterStorage['clientSecret'] = $clientSecret;
		$this->interpreterStorage['redirectUri'] = $redirectUri;
		

		$ip = new Interpreter(new Sandbox(Twitter::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		if (array_key_exists("init",Twitter::$SERVICE_CODE)) {
			$parameters = [&$this->interpreterStorage];
		  $ip->callFunctionSync("init",$parameters );
		}
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
	public function getIdentifier():string {
		Statistics::addCall("Twitter", "getIdentifier");
		$ip = new Interpreter(new Sandbox(Twitter::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null];
		$ip->callFunctionSync('Profile:getIdentifier', $auxArray);
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
	public function getFullName():string {
		Statistics::addCall("Twitter", "getFullName");
		$ip = new Interpreter(new Sandbox(Twitter::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null];
		$ip->callFunctionSync('Profile:getFullName', $auxArray);
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
	public function getEmail():string {
		Statistics::addCall("Twitter", "getEmail");
		$ip = new Interpreter(new Sandbox(Twitter::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null];
		$ip->callFunctionSync('Profile:getEmail', $auxArray);
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
	public function getGender():string {
		Statistics::addCall("Twitter", "getGender");
		$ip = new Interpreter(new Sandbox(Twitter::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null];
		$ip->callFunctionSync('Profile:getGender', $auxArray);
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
	public function getDescription():string {
		Statistics::addCall("Twitter", "getDescription");
		$ip = new Interpreter(new Sandbox(Twitter::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null];
		$ip->callFunctionSync('Profile:getDescription', $auxArray);
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
	 * @return DateOfBirth
	 */
	public function getDateOfBirth():DateOfBirth {
		Statistics::addCall("Twitter", "getDateOfBirth");
		$ip = new Interpreter(new Sandbox(Twitter::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null];
		$ip->callFunctionSync('Profile:getDateOfBirth', $auxArray);
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
	public function getLocale():string {
		Statistics::addCall("Twitter", "getLocale");
		$ip = new Interpreter(new Sandbox(Twitter::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null];
		$ip->callFunctionSync('Profile:getLocale', $auxArray);
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
	public function getPictureURL():string {
		Statistics::addCall("Twitter", "getPictureURL");
		$ip = new Interpreter(new Sandbox(Twitter::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null];
		$ip->callFunctionSync('Profile:getPictureURL', $auxArray);
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
		Statistics::addCall("Twitter", "login");
		$ip = new Interpreter(new Sandbox(Twitter::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Twitter", "logout");
		$ip = new Interpreter(new Sandbox(Twitter::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
	 * @param string $content
	 * @throws IllegalArgumentError
	 * @throws AuthenticationError
	 * @throws NotFoundError
	 * @throws HttpError
	 * @throws ServiceUnavailableError
	 * @throws \Exception
	 */
	public function postUpdate(string $content):void {
		Statistics::addCall("Twitter", "postUpdate");
		$ip = new Interpreter(new Sandbox(Twitter::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, $content];
		$ip->callFunctionSync('Social:postUpdate', $auxArray);
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
	 * @param string $message
	 * @param resource $image
	 * @throws IllegalArgumentError
	 * @throws AuthenticationError
	 * @throws NotFoundError
	 * @throws HttpError
	 * @throws ServiceUnavailableError
	 * @throws \Exception
	 */
	public function postImage(string $message,  $image):void {
		Statistics::addCall("Twitter", "postImage");
		$ip = new Interpreter(new Sandbox(Twitter::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, $message, $image];
		$ip->callFunctionSync('Social:postImage', $auxArray);
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
	 * @param string $message
	 * @param resource $video
	 * @param int $size
	 * @param string $mimeType
	 * @throws IllegalArgumentError
	 * @throws AuthenticationError
	 * @throws NotFoundError
	 * @throws HttpError
	 * @throws ServiceUnavailableError
	 * @throws \Exception
	 */
	public function postVideo(string $message,  $video, int $size, string $mimeType):void {
		Statistics::addCall("Twitter", "postVideo");
		$ip = new Interpreter(new Sandbox(Twitter::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, $message, $video, $size, $mimeType];
		$ip->callFunctionSync('Social:postVideo', $auxArray);
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
	 * @return array
	 */
	public function getConnections():array {
		Statistics::addCall("Twitter", "getConnections");
		$ip = new Interpreter(new Sandbox(Twitter::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null];
		$ip->callFunctionSync('Social:getConnections', $auxArray);
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
		Statistics::addCall("Twitter", "advancedRequest");
		$ip = new Interpreter(new Sandbox(Twitter::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		$ip = new Interpreter(new Sandbox(Twitter::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		return $ip->saveAsString();
	}

	/**
	 * @param string $savedState
	 */
	public function loadAsString($savedState) {
		$sandbox = new Sandbox(Twitter::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage);
		$ip = new Interpreter($sandbox);
		$ip->loadAsString($savedState);
		$this->persistentStorage = $sandbox->persistentStorage;
	}
}
