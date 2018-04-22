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

use CloudRail\Interfaces\Video;

use CloudRail\Interfaces\AdvancedRequestSupporter;
use CloudRail\Type\AdvancedRequestSpecification;
use CloudRail\Type\AdvancedRequestResponse;
use CloudRail\Type\CloudRailError;

class YouTube implements Video, AdvancedRequestSupporter
{
	private static $SERVICE_CODE = [
		'init' => [
			["create", '$P0.paginationCache', "Object"],
			["create", '$P0.paginationCache.offset', "Number", 0],
			["create", '$P0.paginationCache.metaCache', "Array"],
			["create", '$P0.paginationCache.query', "String"],
			["create", '$P0.paginationChannelCache', "Object"],
			["create", '$P0.paginationChannelCache.offset', "Number", 0],
			["create", '$P0.paginationChannelCache.metaCache', "Array"],
			["create", '$P0.paginationChannelCache.channelId', "String"],
			["set", '$P0.baseUrl', "https://www.googleapis.com/youtube/v3"],
			["if==than", '$P0.scopes', NULL, 2],
			["set", '$P0.scope', "https%3A%2F%2Fwww.googleapis.com%2Fauth%2Fyoutube"],
			["jumpRel", 11],
			["create", '$P0.scope', "String"],
			["size", '$L0', '$P0.scopes'],
			["create", '$L1', "Number", 0],
			["if<than", '$L1', '$L0', 7],
			["if!=than", '$L1', 0, 1],
			["string.concat", '$P0.scope', '$P0.scope', "+"],
			["get", '$L2', '$P0.scopes', '$L1'],
			["string.urlEncode", '$L2', '$L2'],
			["string.concat", '$P0.scope', '$P0.scope', '$L2'],
			["math.add", '$L1', '$L1', 1],
			["jumpRel", -8]
		],
		'Authenticating:login' => [
			["callFunc", "checkAuthentication", '$P0']
		],
		'Authenticating:logout' => [
			["create", '$L0', "Object"],
			["string.concat", '$L0.url', "https://accounts.google.com/o/oauth2/revoke?token=", '$S0.accessToken'],
			["set", '$L0.method', "GET"],
			["http.requestCall", '$L1', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L1'],
			["set", '$S0.accessToken', NULL]
		],
		'uploadVideo' => [
			["callFunc", "requiredClientIDConstructor", '$P0'],
			["callFunc", "checkNull", '$P0', '$P2', "title"],
			["callFunc", "checkNull", '$P0', '$P4', "video"],
			["callFunc", "checkNull", '$P0', '$P5', "size"],
			["callFunc", "checkNull", '$P0', '$P7', "mime type"],
			["callFunc", "checkIsEmpty", '$P0', '$P2', "title"],
			["callFunc", "checkIsEmpty", '$P0', '$P7', "mime type"],
			["callFunc", "checkGreater0", '$P0', '$P5', "size"],
			["create", '$L12', "String"],
			["string.concat", '$L12', '$P5'],
			["callFunc", "checkAuthentication", '$P0'],
			["create", '$L0', "Object"],
			["set", '$L0.method', "POST"],
			["set", '$L0.url', "https://www.googleapis.com/upload/youtube/v3/videos?uploadType=resumable&part=snippet"],
			["create", '$L3', "Object"],
			["set", '$L0.requestHeaders', '$L3'],
			["set", '$L3', "application/json", "Content-Type"],
			["create", '$L4', "String"],
			["string.concat", '$L4', "Bearer ", '$S0.accessToken'],
			["set", '$L3', '$L4', "Authorization"],
			["create", '$L28', "String"],
			["set", '$L28', '$P7'],
			["set", '$L3', '$L28', "X-Upload-Content-Type"],
			["set", '$L3', '$L12', "X-Upload-Content-Length"],
			["create", '$L5', "Object"],
			["create", '$L7', "Object"],
			["set", '$L7.title', '$P2'],
			["set", '$L7.description', '$P3'],
			["set", '$L5.snippet', '$L7'],
			["create", '$L6', "String"],
			["json.stringify", '$L6', '$L5'],
			["stream.stringToStream", '$L7', '$L6'],
			["set", '$L0.requestBody', '$L7'],
			["create", '$L8', "Object"],
			["http.requestCall", '$L8', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L8'],
			["create", '$L9', "String"],
			["get", '$L9', '$L8.responseHeaders', "Location"],
			["create", '$L2', "Object"],
			["set", '$L2.method', "PUT"],
			["set", '$L2.url', '$L9'],
			["create", '$L3', "Object"],
			["set", '$L2.requestHeaders', '$L3'],
			["set", '$L3', '$L12', "Content-Length"],
			["set", '$L3', '$L28', "Content-Type"],
			["create", '$L4', "String"],
			["string.concat", '$L4', "Bearer ", '$S0.accessToken'],
			["set", '$L3', '$L4', "Authorization"],
			["set", '$L2.requestBody', '$P4'],
			["create", '$L5', "Object"],
			["http.requestCall", '$L5', '$L2'],
			["callFunc", "validateResponse", '$P0', '$L5'],
			["json.parse", '$L9', '$L5.responseBody'],
			["callFunc", "getVideo", '$P0', '$P1', '$L9.id']
		],
		'searchVideos' => [
			["callFunc", "checkNull", '$P0', '$P2', "Query"],
			["callFunc", "checkIsEmpty", '$P0', '$P2', "Query"],
			["create", '$P1', "Array"],
			["if!=than", '$P0.paginationCache.query', '$P2', 22],
			["jumpRel", 1],
			["if<than", '$P3', '$P0.paginationCache.offset', 20],
			["set", '$P0.paginationCache.query', '$P2'],
			["set", '$P0.paginationCache.offset', 0],
			["create", '$P0.paginationCache.metaCache', "Array"],
			["string.concat", '$L1', "https://www.googleapis.com/youtube/v3/search?part=id&type=video&maxResults=50"],
			["string.urlEncode", '$L2', '$P2'],
			["string.concat", '$L1', '$L1', "&q=", '$L2'],
			["callFunc", "getRawMetaData", '$P0', '$L6', '$L1'],
			["create", '$L7', "Number"],
			["size", '$L8', '$L6.items'],
			["create", '$L20', "String"],
			["if<than", '$L7', '$L8', 6],
			["get", '$L9', '$L6.items', '$L7'],
			["if!=than", '$L7', 0, 1],
			["string.concat", '$L20', '$L20', ","],
			["string.concat", '$L20', '$L20', '$L9.id.videoId'],
			["math.add", '$L7', '$L7', 1],
			["jumpRel", -7],
			["set", '$P0.paginationCache.cursor', '$L6.nextPageToken'],
			["callFunc", "getVideos", '$P0', '$P0.paginationCache.metaCache', '$L20'],
			["jumpRel", -23],
			["create", '$L0', "Number"],
			["size", '$L0', '$P0.paginationCache.metaCache'],
			["math.add", '$L0', '$L0', '$P0.paginationCache.offset'],
			["if<than", '$P3', '$L0', 14],
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
			["size", '$L2', '$P0.paginationCache.metaCache'],
			["math.add", '$P0.paginationCache.offset', '$P0.paginationCache.offset', '$L2'],
			["create", '$P0.paginationCache.metaCache', "Array"],
			["string.concat", '$L1', "https://www.googleapis.com/youtube/v3/search?part=id&type=video&maxResults=50"],
			["string.urlEncode", '$L2', '$P2'],
			["string.concat", '$L1', '$L1', "&q=", '$L2'],
			["string.concat", '$L1', '$L1', "&pageToken=", '$P0.paginationCache.cursor'],
			["callFunc", "getRawMetaData", '$P0', '$L6', '$L1'],
			["create", '$L7', "Number"],
			["size", '$L8', '$L6.items'],
			["create", '$L20', "String"],
			["if<than", '$L7', '$L8', 6],
			["get", '$L9', '$L6.items', '$L7'],
			["if!=than", '$L7', 0, 1],
			["string.concat", '$L20', '$L20', ","],
			["string.concat", '$L20', '$L20', '$L9.id.videoId'],
			["math.add", '$L7', '$L7', 1],
			["jumpRel", -7],
			["set", '$P0.paginationCache.cursor', '$L6.nextPageToken'],
			["callFunc", "getVideos", '$P0', '$P0.paginationCache.metaCache', '$L20'],
			["jumpRel", -64]
		],
		'getVideo' => [
			["callFunc", "checkNull", '$P0', '$P2', "Video ID"],
			["callFunc", "checkIsEmpty", '$P0', '$P2', "Video ID"],
			["string.concat", '$L1', "https://www.googleapis.com/youtube/v3/videos?part=snippet,contentDetails,statistics,player"],
			["string.concat", '$L1', '$L1', "&id=", '$P2'],
			["callFunc", "getRawMetaData", '$P0', '$L3', '$L1'],
			["set", '$L4', '$L3.items'],
			["create", '$L5', "Number", 0],
			["get", '$L6', '$L4', '$L5'],
			["callFunc", "makeVideoMetaData", '$P0', '$P1', '$L6']
		],
		'getVideos' => [
			["string.concat", '$L1', "https://www.googleapis.com/youtube/v3/videos?part=snippet,contentDetails,statistics,player"],
			["string.concat", '$L1', '$L1', "&id=", '$P2'],
			["callFunc", "getRawMetaData", '$P0', '$L3', '$L1'],
			["set", '$L7', '$L3.items'],
			["create", '$L8', "Number", 0],
			["create", '$L9', "Number"],
			["size", '$L9', '$L7'],
			["create", '$P1', "Array"],
			["if<than", '$L8', '$L9', 5],
			["get", '$L10', '$L7', '$L8'],
			["callFunc", "makeVideoMetaData", '$P0', '$L11', '$L10'],
			["push", '$P1', '$L11'],
			["math.add", '$L8', '$L8', 1],
			["jumpRel", -6]
		],
		'getChannel' => [
			["callFunc", "checkNull", '$P0', '$P2', "Channel ID"],
			["callFunc", "checkIsEmpty", '$P0', '$P2', "Channel ID"],
			["string.concat", '$L1', "https://www.googleapis.com/youtube/v3/channels?part=snippet,contentDetails,statistics,brandingSettings"],
			["string.concat", '$L1', '$L1', "&id=", '$P2'],
			["callFunc", "getRawMetaData", '$P0', '$L3', '$L1'],
			["set", '$L4', '$L3.items'],
			["create", '$L5', "Number", 0],
			["get", '$L6', '$L4', '$L5'],
			["callFunc", "makeChannelMetaData", '$P0', '$P1', '$L6']
		],
		'getOwnChannel' => [
			["callFunc", "requiredClientIDConstructor", '$P0'],
			["callFunc", "checkAuthentication", '$P0'],
			["string.concat", '$L1', "https://www.googleapis.com/youtube/v3/channels?part=snippet,contentDetails,statistics,brandingSettings&mine=true"],
			["create", '$L0', "Object"],
			["set", '$L0.url', '$L1'],
			["set", '$L0.method', "GET"],
			["create", '$L3', "Object"],
			["create", '$L4', "String"],
			["string.concat", '$L4', "Bearer ", '$S0.accessToken'],
			["set", '$L3', '$L4', "Authorization"],
			["set", '$L0.requestHeaders', '$L3'],
			["create", '$L2', "Object"],
			["http.requestCall", '$L2', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L2'],
			["create", '$L3', "Object"],
			["json.parse", '$L3', '$L2.responseBody'],
			["set", '$L4', '$L3.items'],
			["create", '$L5', "Number", 0],
			["get", '$L6', '$L4', '$L5'],
			["callFunc", "makeChannelMetaData", '$P0', '$P1', '$L6']
		],
		'listVideosForChannel' => [
			["callFunc", "checkNull", '$P0', '$P2', "Channel Id"],
			["callFunc", "checkIsEmpty", '$P0', '$P2', "Channel Id"],
			["create", '$P1', "Array"],
			["if!=than", '$P0.paginationChannelCache.channelId', '$P2', 22],
			["jumpRel", 1],
			["if<than", '$P3', '$P0.paginationChannelCache.offset', 20],
			["set", '$P0.paginationChannelCache.channelId', '$P2'],
			["set", '$P0.paginationChannelCache.offset', 0],
			["create", '$P0.paginationChannelCache.metaCache', "Array"],
			["string.concat", '$L1', "https://www.googleapis.com/youtube/v3/search?part=id&type=video&maxResults=50"],
			["set", '$L2', '$P2'],
			["string.concat", '$L1', '$L1', "&channelId=", '$L2'],
			["callFunc", "getRawMetaData", '$P0', '$L6', '$L1'],
			["create", '$L7', "Number"],
			["size", '$L8', '$L6.items'],
			["create", '$L20', "String"],
			["if<than", '$L7', '$L8', 6],
			["get", '$L9', '$L6.items', '$L7'],
			["if!=than", '$L7', 0, 1],
			["string.concat", '$L20', '$L20', ","],
			["string.concat", '$L20', '$L20', '$L9.id.videoId'],
			["math.add", '$L7', '$L7', 1],
			["jumpRel", -7],
			["set", '$P0.paginationChannelCache.cursor', '$L6.nextPageToken'],
			["callFunc", "getVideos", '$P0', '$P0.paginationChannelCache.metaCache', '$L20'],
			["jumpRel", -23],
			["create", '$L0', "Number"],
			["size", '$L0', '$P0.paginationChannelCache.metaCache'],
			["math.add", '$L0', '$L0', '$P0.paginationChannelCache.offset'],
			["if<than", '$P3', '$L0', 14],
			["math.multiply", '$L1', '$P0.paginationChannelCache.offset', -1],
			["math.add", '$L1', '$L1', '$P3'],
			["size", '$L0', '$P1'],
			["if<than", '$L0', '$P4', 9],
			["get", '$L2', '$P0.paginationChannelCache.metaCache', '$L1'],
			["push", '$P1', '$L2'],
			["math.add", '$L1', '$L1', 1],
			["size", '$L3', '$P0.paginationChannelCache.metaCache'],
			["if==than", '$L3', '$L1', 3],
			["size", '$L4', '$P0.paginationChannelCache.metaCache'],
			["math.add", '$P3', '$L4', '$P0.paginationChannelCache.offset'],
			["jumpRel", 2],
			["jumpRel", -11],
			["return"],
			["if==than", '$P0.paginationChannelCache.cursor', NULL, 1],
			["return"],
			["size", '$L2', '$P0.paginationChannelCache.metaCache'],
			["math.add", '$P0.paginationChannelCache.offset', '$P0.paginationChannelCache.offset', '$L2'],
			["create", '$P0.paginationChannelCache.metaCache', "Array"],
			["string.concat", '$L1', "https://www.googleapis.com/youtube/v3/search?part=id&type=video&maxResults=50"],
			["set", '$L2', '$P2'],
			["string.concat", '$L1', '$L1', "&channelId=", '$L2'],
			["string.concat", '$L1', '$L1', "&pageToken=", '$P0.paginationChannelCache.cursor'],
			["callFunc", "getRawMetaData", '$P0', '$L6', '$L1'],
			["create", '$L7', "Number"],
			["size", '$L8', '$L6.items'],
			["create", '$L20', "String"],
			["if<than", '$L7', '$L8', 6],
			["get", '$L9', '$L6.items', '$L7'],
			["if!=than", '$L7', 0, 1],
			["string.concat", '$L20', '$L20', ","],
			["string.concat", '$L20', '$L20', '$L9.id.videoId'],
			["math.add", '$L7', '$L7', 1],
			["jumpRel", -7],
			["set", '$P0.paginationChannelCache.cursor', '$L6.nextPageToken'],
			["callFunc", "getVideos", '$P0', '$P0.paginationChannelCache.metaCache', '$L20'],
			["jumpRel", -64]
		],
		'AdvancedRequestSupporter:advancedRequest' => [
			["create", '$L0', "Object"],
			["create", '$L0.url', "String"],
			["if!=than", '$P2.appendBaseUrl', 0, 1],
			["set", '$L0.url', "https://www.googleapis.com/youtube/v3"],
			["string.concat", '$L0.url', '$L0.url', '$P2.url'],
			["set", '$L0.requestHeaders', '$P2.headers'],
			["set", '$L0.method', '$P2.method'],
			["set", '$L0.requestBody', '$P2.body'],
			["if!=than", '$P2.appendAuthorization', 0, 2],
			["callFunc", "checkAuthentication", '$P0'],
			["string.concat", '$L0.requestHeaders.Authorization', "Bearer ", '$S0.accessToken'],
			["http.requestCall", '$L1', '$L0'],
			["if!=than", '$P2.checkErrors', 0, 1],
			["callFunc", "validateResponse", '$P0', '$L1'],
			["create", '$P1', "AdvancedRequestResponse"],
			["set", '$P1.status', '$L1.code'],
			["set", '$P1.headers', '$L1.responseHeaders'],
			["set", '$P1.body', '$L1.responseBody']
		],
		'makeVideoMetaData' => [
			["set", '$L1', '$P2.snippet'],
			["set", '$L2', '$L1.thumbnails'],
			["set", '$L3', '$P2.statistics'],
			["create", '$L4', "Date", '$L1.publishedAt'],
			["create", '$L5', "Number", 0],
			["create", '$L6', "Number", 0],
			["create", '$L7', "Number", 0],
			["math.add", '$L5', '$L3.viewCount', 0],
			["if!=than", '$L3.likeCount', NULL, 2],
			["math.add", '$L6', '$L3.likeCount', 0],
			["jumpRel", 1],
			["set", '$L6', NULL],
			["if!=than", '$L3.likeCount', NULL, 2],
			["math.add", '$L7', '$L3.dislikeCount', 0],
			["jumpRel", 1],
			["set", '$L7', NULL],
			["callFunc", "convertDuration", '$P0', '$L11', '$P2.contentDetails.duration'],
			["create", '$L8', "Number", 0],
			["math.add", '$L8', '$L11', 0],
			["create", '$P1', "VideoMetaData", '$P2.id', '$L1.title', '$L1.description', '$L4.time', '$L1.channelId', '$L8', '$L2.high.url', '$P2.player.embedHtml', '$L5', '$L6', '$L7']
		],
		'makeChannelMetaData' => [
			["set", '$L1', '$P2.snippet.thumbnails'],
			["string.concat", '$L2', "www.youtube.com/channel/", '$P2.id'],
			["set", '$L3', '$P2.brandingSettings'],
			["create", '$L4', "Number", 0],
			["if!=than", '$P2.statistics.subscriberCount', NULL, 2],
			["math.add", '$L4', '$P2.statistics.subscriberCount', 0],
			["jumpRel", 1],
			["set", '$L4', NULL],
			["create", '$P1', "ChannelMetaData", '$P2.id', '$P2.snippet.title', '$L4', '$L2', '$L1.high.url', '$L3.image.bannerImageUrl']
		],
		'getRawMetaData' => [
			["set", '$L1', '$P2'],
			["create", '$L2', "Object"],
			["if!=than", '$P0.apiKey', NULL, 3],
			["string.concat", '$L1', '$L1', "&key=", '$P0.apiKey'],
			["callFunc", "getViaAPIKey", '$P0', '$L2', '$L1'],
			["jumpRel", 1],
			["callFunc", "getViaAuthorization", '$P0', '$L2', '$L1'],
			["set", '$P1', '$L2']
		],
		'convertDurationToSeconds' => [
			["set", '$L1', '$P2'],
			["create", '$L2', "Number", 0],
			["create", '$L4', "Number"],
			["create", '$L5', "Number"],
			["create", '$L6', "Number"],
			["create", '$L7', "Number"],
			["callFunc", "replace", '$P0', '$L31', '$L1', "P", ""],
			["callFunc", "replace", '$P0', '$L30', '$L31', "T", ""],
			["create", '$L0', "Number"],
			["string.indexOf", '$L0', '$L30', "H"],
			["if!=than", '$L0', -1, 5],
			["string.substring", '$L50', '$L30', 0, '$L0'],
			["math.add", '$L51', '$L50', 0],
			["math.multiply", '$L5', '$L51', 3600],
			["callFunc", "removeString", '$P0', '$L30', '$L30', "H"],
			["jumpRel", 1],
			["math.add", '$L2', '$L2', '$L5'],
			["math.add", '$L0', '$L0', 1],
			["create", '$L10', "Number"],
			["string.indexOf", '$L10', '$L30', "M"],
			["if!=than", '$L10', -1, 6],
			["string.substring", '$L60', '$L30', '$L0', '$L10'],
			["callFunc", "replace", '$P0', '$L61', '$L60', "M", ""],
			["math.add", '$L62', '$L61', 0],
			["math.multiply", '$L6', '$L62', 60],
			["callFunc", "removeString", '$P0', '$L30', '$L30', "M"],
			["jumpRel", 1],
			["math.add", '$L2', '$L2', '$L6'],
			["math.add", '$L10', '$L10', 1],
			["create", '$L20', "Number"],
			["string.indexOf", '$L20', '$L30', "S"],
			["if!=than", '$L20', -1, 4],
			["string.substring", '$L70', '$L30', '$L10', '$L20'],
			["callFunc", "replace", '$P0', '$L71', '$L70', "S", ""],
			["math.add", '$L7', '$L71', 0],
			["jumpRel", 1],
			["debug.out", '$L7'],
			["math.add", '$L2', '$L2', '$L7'],
			["set", '$P1', '$L2']
		],
		'convertDuration' => [
			["set", '$L1', '$P2'],
			["create", '$L2', "Number", 0],
			["create", '$L5', "Number"],
			["create", '$L6', "Number"],
			["create", '$L7', "Number"],
			["callFunc", "replace", '$P0', '$L31', '$L1', "P", ""],
			["callFunc", "replace", '$P0', '$L30', '$L31', "T", ""],
			["callFunc", "findNumberFromString", '$P0', '$L5', '$L30', "H", 3600],
			["callFunc", "removeString", '$P0', '$L31', '$L30', "H"],
			["math.add", '$L2', '$L2', '$L5'],
			["callFunc", "findNumberFromString", '$P0', '$L6', '$L31', "M", 60],
			["callFunc", "removeString", '$P0', '$L32', '$L31', "M"],
			["math.add", '$L2', '$L2', '$L6'],
			["callFunc", "findNumberFromString", '$P0', '$L7', '$L32', "S", 1],
			["math.add", '$L2', '$L2', '$L7'],
			["set", '$P1', '$L2']
		],
		'findNumberFromString' => [
			["create", '$L0', "Number"],
			["string.indexOf", '$L0', '$P2', '$P3'],
			["if!=than", '$L0', -1, 6],
			["string.substring", '$L2', '$P2', 0, '$L0'],
			["math.add", '$L3', '$L2', 0],
			["math.multiply", '$L5', '$L3', '$P4'],
			["set", '$P1', '$L5'],
			["return"],
			["jumpRel", 1],
			["set", '$P1', "0"]
		],
		'getViaAPIKey' => [
			["set", '$L1', '$P2'],
			["create", '$L0', "Object"],
			["set", '$L0.url', '$L1'],
			["set", '$L0.method', "GET"],
			["create", '$L2', "Object"],
			["http.requestCall", '$L2', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L2'],
			["json.parse", '$P1', '$L2.responseBody']
		],
		'getViaAuthorization' => [
			["set", '$L1', '$P2'],
			["callFunc", "checkAuthentication", '$P0'],
			["create", '$L0', "Object"],
			["set", '$L0.url', '$L1'],
			["set", '$L0.method', "GET"],
			["create", '$L3', "Object"],
			["create", '$L4', "String"],
			["string.concat", '$L4', "Bearer ", '$S0.accessToken'],
			["set", '$L3', '$L4', "Authorization"],
			["set", '$L0.requestHeaders', '$L3'],
			["create", '$L2', "Object"],
			["http.requestCall", '$L2', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L2'],
			["json.parse", '$P1', '$L2.responseBody']
		],
		'checkAuthentication' => [
			["create", '$L0', "Date"],
			["if==than", '$S0.accessToken', NULL, 2],
			["callFunc", "authenticate", '$P0', "accessToken"],
			["return"],
			["create", '$L1', "Date"],
			["set", '$L1.time', '$S0.expireIn'],
			["if<than", '$L1', '$L0', 1],
			["callFunc", "authenticate", '$P0', "refreshToken"]
		],
		'authenticate' => [
			["create", '$L2', "String"],
			["if==than", '$P1', "accessToken", 4],
			["string.concat", '$L0', "https://accounts.google.com/o/oauth2/v2/auth?client_id=", '$P0.clientId', "&scope=", '$P0.scope', "&response_type=code&prompt=consent&access_type=offline&redirect_uri=", '$P0.redirectUri', "&state=", '$P0.state', "&suppress_webview_warning=true"],
			["awaitCodeRedirect", '$L1', '$L0', NULL, '$P0.redirectUri'],
			["string.concat", '$L2', "client_id=", '$P0.clientId', "&redirect_uri=", '$P0.redirectUri', "&client_secret=", '$P0.clientSecret', "&code=", '$L1', "&grant_type=authorization_code"],
			["jumpRel", 1],
			["string.concat", '$L2', "client_id=", '$P0.clientId', "&redirect_uri=", '$P0.redirectUri', "&client_secret=", '$P0.clientSecret', "&refresh_token=", '$S0.refreshToken', "&grant_type=refresh_token"],
			["stream.stringToStream", '$L3', '$L2'],
			["create", '$L4', "Object"],
			["set", '$L4', "application/x-www-form-urlencoded", "Content-Type"],
			["create", '$L5', "Object"],
			["set", '$L5.url', "https://www.googleapis.com/oauth2/v4/token"],
			["set", '$L5.method', "POST"],
			["set", '$L5.requestBody', '$L3'],
			["set", '$L5.requestHeaders', '$L4'],
			["http.requestCall", '$L6', '$L5'],
			["callFunc", "validateResponse", '$P0', '$L6'],
			["stream.streamToString", '$L7', '$L6.responseBody'],
			["json.parse", '$L8', '$L7'],
			["set", '$S0.accessToken', '$L8.access_token'],
			["if!=than", '$L8.refresh_token', NULL, 1],
			["set", '$S0.refreshToken', '$L8.refresh_token'],
			["create", '$L10', "Date"],
			["math.multiply", '$L9', '$L8.expires_in', 1000],
			["math.add", '$L9', '$L9', '$L10.time', -60000],
			["set", '$S0.expireIn', '$L9']
		],
		'validateResponse' => [
			["if>=than", '$P1.code', 400, 19],
			["stream.streamToString", '$L2', '$P1.responseBody'],
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
		'checkNull' => [
			["if==than", '$P1', NULL, 3],
			["string.concat", '$L0', '$P2', " is not allowed to be null."],
			["create", '$L1', "Error", '$L0', "IllegalArgument"],
			["throwError", '$L1']
		],
		'checkIsEmpty' => [
			["size", '$L0', '$P2'],
			["if==than", '$L0', 0, 3],
			["string.concat", '$L0', '$P3', " is not allowed to be empty."],
			["create", '$L1', "Error", '$L0', "IllegalArgument"],
			["throwError", '$L1']
		],
		'checkGreater0' => [
			["if<=than", '$P1', 0, 3],
			["string.concat", '$L0', '$P2', " has to be greater than 0"],
			["create", '$L1', "Error", '$L0', "IllegalArgument"],
			["throwError", '$L1']
		],
		'requiredClientIDConstructor' => [
			["if!=than", '$P0.apiKey', NULL, 3],
			["string.concat", '$L0', "You cannot use the API key with this service, the other constructor "],
			["create", '$L1', "Error", '$L0', "IllegalArgument"],
			["throwError", '$L1']
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
		'removeString' => [
			["create", '$L3', "Number"],
			["string.lastIndexOf", '$L3', '$P2', '$P3'],
			["math.add", '$L3', '$L3', 1],
			["create", '$L29', "String"],
			["string.substring", '$L29', '$P2', '$L3'],
			["set", '$P1', '$L29']
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
	 * @param array $scopes
	 * @param string $apiKey
	 */
	public function __construct(callable $redirectReceiver, string $clientId, string $clientSecret, string $redirectUri, string $state, array $scopes, string $apiKey)
	{
		$this->interpreterStorage = array();
		$this->instanceDependencyStorage = ["redirectReceiver" => $redirectReceiver];
		$this->persistentStorage = array(array());
		InitSelfTest::initTest('YouTube');
		
		$this->interpreterStorage['clientId'] = $clientId;
		$this->interpreterStorage['clientSecret'] = $clientSecret;
		$this->interpreterStorage['redirectUri'] = $redirectUri;
		$this->interpreterStorage['state'] = $state;
		$this->interpreterStorage['scopes'] = $scopes;
		$this->interpreterStorage['apiKey'] = $apiKey;
		

		$ip = new Interpreter(new Sandbox(YouTube::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		if (array_key_exists("init",YouTube::$SERVICE_CODE)) {
			$parameters = [&$this->interpreterStorage];
		  $ip->callFunctionSync("init",$parameters );
		}
	}

	
	/**
	 * @param string $title
	 * @param string $description
	 * @param resource $stream
	 * @param int $size
	 * @param string $channelId
	 * @param string $mimeType
	 * @throws IllegalArgumentError
	 * @throws AuthenticationError
	 * @throws NotFoundError
	 * @throws HttpError
	 * @throws ServiceUnavailableError
	 * @throws \Exception
	 * @return VideoMetaData
	 */
	public function uploadVideo(string $title, string $description,  $stream, int $size, string $channelId, string $mimeType):VideoMetaData {
		Statistics::addCall("YouTube", "uploadVideo");
		$ip = new Interpreter(new Sandbox(YouTube::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null, $title, $description, $stream, $size, $channelId, $mimeType];
		$ip->callFunctionSync('uploadVideo', $auxArray);
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
	public function searchVideos(string $query, int $offset, int $limit):array {
		Statistics::addCall("YouTube", "searchVideos");
		$ip = new Interpreter(new Sandbox(YouTube::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null, $query, $offset, $limit];
		$ip->callFunctionSync('searchVideos', $auxArray);
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
	 * @param string $videoId
	 * @throws IllegalArgumentError
	 * @throws AuthenticationError
	 * @throws NotFoundError
	 * @throws HttpError
	 * @throws ServiceUnavailableError
	 * @throws \Exception
	 * @return VideoMetaData
	 */
	public function getVideo(string $videoId):VideoMetaData {
		Statistics::addCall("YouTube", "getVideo");
		$ip = new Interpreter(new Sandbox(YouTube::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null, $videoId];
		$ip->callFunctionSync('getVideo', $auxArray);
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
	 * @param string $channelId
	 * @throws IllegalArgumentError
	 * @throws AuthenticationError
	 * @throws NotFoundError
	 * @throws HttpError
	 * @throws ServiceUnavailableError
	 * @throws \Exception
	 * @return ChannelMetaData
	 */
	public function getChannel(string $channelId):ChannelMetaData {
		Statistics::addCall("YouTube", "getChannel");
		$ip = new Interpreter(new Sandbox(YouTube::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null, $channelId];
		$ip->callFunctionSync('getChannel', $auxArray);
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
	 * @return ChannelMetaData
	 */
	public function getOwnChannel():ChannelMetaData {
		Statistics::addCall("YouTube", "getOwnChannel");
		$ip = new Interpreter(new Sandbox(YouTube::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null];
		$ip->callFunctionSync('getOwnChannel', $auxArray);
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
	 * @param string $channelId
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
	public function listVideosForChannel(string $channelId, int $offset, int $limit):array {
		Statistics::addCall("YouTube", "listVideosForChannel");
		$ip = new Interpreter(new Sandbox(YouTube::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null, $channelId, $offset, $limit];
		$ip->callFunctionSync('listVideosForChannel', $auxArray);
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
		Statistics::addCall("YouTube", "login");
		$ip = new Interpreter(new Sandbox(YouTube::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("YouTube", "logout");
		$ip = new Interpreter(new Sandbox(YouTube::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("YouTube", "advancedRequest");
		$ip = new Interpreter(new Sandbox(YouTube::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		$ip = new Interpreter(new Sandbox(YouTube::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		return $ip->saveAsString();
	}

	/**
	 * @param string $savedState
	 */
	public function loadAsString($savedState) {
		$sandbox = new Sandbox(YouTube::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage);
		$ip = new Interpreter($sandbox);
		$ip->loadAsString($savedState);
		$this->persistentStorage = $sandbox->persistentStorage;
	}
}
