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

use CloudRail\Interfaces\Messaging;
use CloudRail\Type\MessagingAttachment;
use CloudRail\Interfaces\AdvancedRequestSupporter;
use CloudRail\Type\AdvancedRequestSpecification;
use CloudRail\Type\AdvancedRequestResponse;
use CloudRail\Type\CloudRailError;

class FacebookMessenger implements Messaging, AdvancedRequestSupporter
{
	private static $SERVICE_CODE = [
		'init' => [
			["string.concat", '$P0.baseURL', "https://graph.facebook.com/v2.6/me/messages?access_token=", '$P0.botToken'],
			["set", '$P0.boundaryString', "------7V0ub86bNNNKWdgJgsF7r0DxYtOB06XYxWvyMuYg5BucWEINpyFRcqisOXWr"]
		],
		'AdvancedRequestSupporter:advancedRequest' => [
			["create", '$L0', "Object"],
			["create", '$L0.url', "String"],
			["if!=than", '$P2.appendBaseUrl', 0, 1],
			["set", '$L0.url', "https://graph.facebook.com/v2.6/me"],
			["string.concat", '$L0.url', '$L0.url', '$P2.url'],
			["if!=than", '$P2.appendAuthorization', 0, 1],
			["string.concat", '$L0.url', '$L0.url', "?access_token=", '$P0.botToken'],
			["set", '$L0.requestHeaders', '$P2.headers'],
			["set", '$L0.method', '$P2.method'],
			["set", '$L0.requestBody', '$P2.body'],
			["http.requestCall", '$L1', '$L0'],
			["if!=than", '$P2.checkErrors', 0, 1],
			["callFunc", "validateResponse", '$P0', '$L1'],
			["create", '$P1', "AdvancedRequestResponse"],
			["set", '$P1.status', '$L1.code'],
			["set", '$P1.headers', '$L1.responseHeaders'],
			["set", '$P1.body', '$L1.responseBody']
		],
		'processWebhookRequest' => [
			["json.parse", '$L0', '$P2'],
			["set", '$L0', '$L0.entry'],
			["create", '$L1', "Number", 0],
			["size", '$L2', '$L0'],
			["create", '$P1', "Array"],
			["if<than", '$L1', '$L2', 18],
			["get", '$L3', '$L0', '$L1'],
			["get", '$L4', '$L3.messaging', 0],
			["get", '$L8', '$L3.messaging', 0],
			["callFunc", "getLocation", '$P0', '$L5', '$L8.message.attachments'],
			["callFunc", "getAttachments", '$P0', '$L6', '$L4.message.attachments'],
			["create", '$L7', "Message"],
			["set", '$L7.MessageId', '$L4.message.mid'],
			["set", '$L7.SenderId', '$L4.sender.id'],
			["set", '$L7.ChatId', '$L4.sender.id'],
			["set", '$L7.SendAt', '$L4.timestamp'],
			["set", '$L7.MessageText', '$L4.message.text'],
			["if!=than", '$L5', NULL, 1],
			["set", '$L7.Location', '$L5'],
			["if!=than", '$L6', NULL, 1],
			["set", '$L7.Attachments', '$L6'],
			["push", '$P1', '$L7'],
			["math.add", '$L1', '$L1', 1],
			["jumpRel", -19]
		],
		'sendMessage' => [
			["callFunc", "checkMandatory", '$P0', '$P2', "chatId"],
			["callFunc", "checkMandatory", '$P0', '$P3', "messageText"],
			["callFunc", "sendContent", '$P0', '$P1', '$P2', '$P3', NULL, NULL, NULL]
		],
		'sendImage' => [
			["callFunc", "sendContent", '$P0', '$P1', '$P2', '$P3', '$P4', '$P5', "image", '$P7', '$P8']
		],
		'sendVideo' => [
			["callFunc", "sendContent", '$P0', '$P1', '$P2', '$P3', '$P4', '$P5', "video", '$P7', '$P8']
		],
		'sendAudio' => [
			["callFunc", "sendContent", '$P0', '$P1', '$P2', '$P3', '$P4', '$P5', "audio", '$P7', '$P8']
		],
		'sendFile' => [
			["callFunc", "sendContent", '$P0', '$P1', '$P2', '$P3', '$P4', '$P5', "file", '$P7', '$P8']
		],
		'downloadContent' => [
			["create", '$L0', "Object"],
			["set", '$L0.method', "GET"],
			["string.concat", '$L0.url', '$P2.id'],
			["create", '$L1', "Object"],
			["http.requestCall", '$L1', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L1'],
			["set", '$P1', '$P2'],
			["set", '$P1.stream', '$L1.responseBody'],
			["set", '$P1.mimeType', '$L1.responseHeaders.Content-Type']
		],
		'sendCarousel' => [
			["create", '$L0', "Object"],
			["set", '$L0.url', '$P0.baseURL'],
			["set", '$L0.method', "POST"],
			["create", '$L0.requestHeaders', "Object"],
			["set", '$L0.requestHeaders.Content-Type', "application/json"],
			["create", '$L1', "Object"],
			["create", '$L1.recipient', "Object"],
			["create", '$L1.message', "Object"],
			["create", '$L1.message.attachment', "Object"],
			["create", '$L1.message.attachment.payload', "Object"],
			["set", '$L1.recipient.id', '$P2'],
			["set", '$L1.message.attachment.type', "template"],
			["set", '$L1.message.attachment.payload.template_type', "generic"],
			["create", '$L1.message.attachment.payload.elements', "Array"],
			["create", '$L3', "Number", 0],
			["size", '$L4', '$P3'],
			["if<than", '$L3', '$L4', 26],
			["get", '$L5', '$P3', '$L3'],
			["create", '$L6', "Object"],
			["set", '$L6.image_url', '$L5.mediaUrl'],
			["set", '$L6.title', '$L5.title'],
			["set", '$L6.subtitle', '$L5.subTitle'],
			["create", '$L6.buttons', "Array"],
			["set", '$L7', 0],
			["size", '$L8', '$L5.buttons'],
			["if<than", '$L7', '$L8', 14],
			["get", '$L9', '$L5.buttons', '$L7'],
			["create", '$L10', "Object"],
			["set", '$L10.title', '$L9.text'],
			["set", '$L11', '$L9.type'],
			["if==than", '$L9.type', "uri", 1],
			["set", '$L11', "web_url"],
			["set", '$L10.type', '$L11'],
			["if!=than", '$L9.url', NULL, 1],
			["set", '$L10.url', '$L9.url'],
			["if!=than", '$L9.payload', NULL, 1],
			["set", '$L10.payload', '$L9.payload'],
			["push", '$L6.buttons', '$L10'],
			["math.add", '$L7', '$L7', 1],
			["jumpRel", -15],
			["push", '$L1.message.attachment.payload.elements', '$L6'],
			["math.add", '$L3', '$L3', 1],
			["jumpRel", -27],
			["json.stringify", '$L1', '$L1'],
			["stream.stringToStream", '$L0.requestBody', '$L1'],
			["create", '$L13', "Object"],
			["http.requestCall", '$L13', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L13'],
			["create", '$L14', "Date"],
			["set", '$L14', '$L14.time'],
			["create", '$P1', "Message"],
			["set", '$P1.ChatId', '$P2'],
			["set", '$P1.SendAt', '$L14']
		],
		'sendContent' => [
			["callFunc", "checkMandatory", '$P0', '$P2', "chatId"],
			["if!=than", '$P3', NULL, 8],
			["if!=than", '$P4', NULL, 3],
			["callFunc", "sendContent", '$P0', '$P1', '$P2', NULL, '$P4', '$P5', '$P6', '$P7', '$P8'],
			["callFunc", "sendContent", '$P0', '$P1', '$P2', '$P3', NULL, NULL, NULL, NULL, NULL],
			["return"],
			["if!=than", '$P5', NULL, 3],
			["callFunc", "sendContent", '$P0', '$P1', '$P2', NULL, '$P4', '$P5', '$P6', '$P7', '$P8'],
			["callFunc", "sendContent", '$P0', '$P1', '$P2', '$P3', NULL, NULL, NULL, NULL, NULL],
			["return"],
			["if!=than", '$P5', NULL, 2],
			["callFunc", "sendContentWithStream", '$P0', '$P1', '$P2', '$P3', '$P5', '$P6', '$P7', '$P8'],
			["jumpRel", 1],
			["callFunc", "sendContentWithID", '$P0', '$P1', '$P2', '$P3', '$P4', '$P6']
		],
		'sendContentWithID' => [
			["create", '$L0', "Object"],
			["set", '$L0.url', '$P0.baseURL'],
			["set", '$L0.method', "POST"],
			["create", '$L0.requestHeaders', "Object"],
			["set", '$L0.requestHeaders.Content-Type', "application/json"],
			["create", '$L1', "Object"],
			["create", '$L1.recipient', "Object"],
			["create", '$L1.message', "Object"],
			["if!=than", '$P4', NULL, 2],
			["create", '$L1.message.attachment', "Object"],
			["create", '$L1.message.attachment.payload', "Object"],
			["set", '$L1.recipient.id', '$P2'],
			["if!=than", '$P3', NULL, 1],
			["set", '$L1.message.text', '$P3'],
			["if!=than", '$P4', NULL, 2],
			["set", '$L1.message.attachment.type', '$P5'],
			["set", '$L1.message.attachment.payload.url', '$P4'],
			["json.stringify", '$L1', '$L1'],
			["stream.stringToStream", '$L0.requestBody', '$L1'],
			["create", '$L2', "Object"],
			["http.requestCall", '$L2', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L2'],
			["json.parse", '$L3', '$L2.responseBody'],
			["create", '$L4', "Date"],
			["create", '$P1', "Message"],
			["if!=than", '$L3.message_id', NULL, 2],
			["string.concat", '$L11', '$L3.message_id'],
			["set", '$P1.MessageId', '$L11'],
			["if!=than", '$P2', NULL, 2],
			["string.concat", '$L13', '$P2'],
			["set", '$P1.ChatId', '$L13'],
			["set", '$P1.SendAt', '$L4.time'],
			["if!=than", '$P3', NULL, 2],
			["string.concat", '$L15', '$P3'],
			["set", '$P1.MessageText', '$L15']
		],
		'sendContentWithStream' => [
			["create", '$L0', "Object"],
			["set", '$L0.url', '$P0.baseURL'],
			["set", '$L0.method', "POST"],
			["string.concat", '$L11', "application/octet-stream"],
			["if==than", '$P5', "image", 2],
			["set", '$L11', '$P8'],
			["if!=than", '$P6', NULL, 6],
			["string.split", '$L8', '$P6', "\\."],
			["size", '$L9', '$L8'],
			["math.add", '$L9', '$L9', "-1"],
			["get", '$L10', '$L8', '$L9'],
			["getMimeType", '$L11', '$L10'],
			["create", '$L1', "String"],
			["string.concat", '$L1', '$L1', "--", '$P0.boundaryString', "\r\n"],
			["string.concat", '$L1', '$L1', "Content-Disposition: form-data; name=\"recipient\"\r\n"],
			["string.concat", '$L1', '$L1', "Content-Type:text/plain", "\r\n"],
			["string.concat", '$L1', '$L1', "\r\n"],
			["string.concat", '$L1', '$L1', "{\"id\":\"", '$P2', "\"}\r\n"],
			["string.concat", '$L1', '$L1', "--", '$P0.boundaryString', "\r\n"],
			["string.concat", '$L1', '$L1', "Content-Disposition: form-data; name=\"message\"\r\n"],
			["string.concat", '$L1', '$L1', "Content-Type:text/plain", "\r\n"],
			["string.concat", '$L1', '$L1', "\r\n"],
			["string.concat", '$L1', '$L1', "{"],
			["if!=than", '$P3', NULL, 1],
			["string.concat", '$L1', '$L1', "\"text\": \"", '$P3', "\", "],
			["string.concat", '$L1', '$L1', "\"attachment\":{\"type\":\"", '$P5', "\", \"payload\":{}}}"],
			["string.concat", '$L1', '$L1', "\r\n"],
			["string.concat", '$L1', '$L1', "--", '$P0.boundaryString', "\r\n"],
			["string.concat", '$L1', '$L1', "Content-Disposition: form-data; name=\"filedata\"; filename=\"", '$P6', "\"\r\n"],
			["string.concat", '$L1', '$L1', "Content-Type:", '$L11', "\r\n"],
			["string.concat", '$L1', '$L1', "\r\n"],
			["string.concat", '$L5', "\r\n--", '$P0.boundaryString', "--\r\n"],
			["stream.stringToStream", '$L6', '$L1'],
			["stream.stringToStream", '$L7', '$L5'],
			["stream.makeJoinedStream", '$L0.requestBody', '$L6', '$P4', '$L7'],
			["create", '$L0.requestHeaders', "Object"],
			["string.concat", '$L0.requestHeaders.Content-Type', "multipart/form-data; boundary=", '$P0.boundaryString'],
			["create", '$L2', "Object"],
			["http.requestCall", '$L2', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L2'],
			["json.parse", '$L3', '$L2.responseBody']
		],
		'getLocation' => [
			["if==than", '$P2', NULL, 1],
			["return"],
			["create", '$L0', "Number", 0],
			["size", '$L1', '$P2'],
			["if<than", '$L0', '$L1', 6],
			["get", '$L2', '$P2', '$L0'],
			["if==than", '$L2.type', "location", 4],
			["create", '$P1', "Location"],
			["set", '$P1.Longitude', '$L2.payload.coordinates.long'],
			["set", '$P1.Latitude', '$L2.payload.coordinates.lat'],
			["return"]
		],
		'getAttachments' => [
			["if==than", '$P2', NULL, 1],
			["return"],
			["create", '$L0', "Number", 0],
			["size", '$L1', '$P2'],
			["create", '$P1', "Array"],
			["if<than", '$L0', '$L1', 7],
			["get", '$L2', '$P2', '$L0'],
			["if!=than", '$L2.type', "location", 4],
			["if!=than", '$L2.type', "fallback", 3],
			["create", '$L3', "MessagingAttachment", '$L2.payload.url', '$L2.type', NULL, NULL, NULL],
			["push", '$P1', '$L3'],
			["math.add", '$L0', '$L0', 1],
			["jumpRel", -8]
		],
		'extractMessageObject' => [
		],
		'checkMandatory' => [
			["if==than", '$P1', NULL, 3],
			["string.concat", '$L1', "Field ", '$P2', " is mandatory"],
			["create", '$L0', "Error", '$L1', "IllegalArgument"],
			["throwError", '$L0']
		],
		'validateResponse' => [
			["if>=than", '$P1.code', 400, 15],
			["debug.out", '$P1.code'],
			["stream.streamToString", '$L5', '$P1.responseBody'],
			["set", '$L2', ""],
			["if==than", '$P1.code', 401, 2],
			["create", '$L3', "Error", '$L2', "Authentication"],
			["throwError", '$L3'],
			["if==than", '$P1.code', 404, 2],
			["create", '$L3', "Error", '$L2', "NotFound"],
			["throwError", '$L3'],
			["if==than", '$P1.code', 503, 2],
			["create", '$L3', "Error", '$L2', "ServiceUnavailable"],
			["throwError", '$L3'],
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
	 * @param string $botToken
	 */
	public function __construct(callable $redirectReceiver, string $botToken)
	{
		$this->interpreterStorage = array();
		$this->instanceDependencyStorage = ["redirectReceiver" => $redirectReceiver];
		$this->persistentStorage = array(array());
		InitSelfTest::initTest('FacebookMessenger');
		
		$this->interpreterStorage['botToken'] = $botToken;
		

		$ip = new Interpreter(new Sandbox(FacebookMessenger::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		if (array_key_exists("init",FacebookMessenger::$SERVICE_CODE)) {
			$parameters = [&$this->interpreterStorage];
		  $ip->callFunctionSync("init",$parameters );
		}
	}

	
	/**
	 * @param string $receiverId
	 * @param string $message
	 * @throws IllegalArgumentError
	 * @throws AuthenticationError
	 * @throws NotFoundError
	 * @throws HttpError
	 * @throws ServiceUnavailableError
	 * @throws \Exception
	 * @return Message
	 */
	public function sendMessage(string $receiverId, string $message):Message {
		Statistics::addCall("FacebookMessenger", "sendMessage");
		$ip = new Interpreter(new Sandbox(FacebookMessenger::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null, $receiverId, $message];
		$ip->callFunctionSync('sendMessage', $auxArray);
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
	 * @param string $receiverId
	 * @param string $message
	 * @param string $imageId
	 * @param resource $imageStream
	 * @param string $previewUrl
	 * @param string $mimeType
	 * @throws IllegalArgumentError
	 * @throws AuthenticationError
	 * @throws NotFoundError
	 * @throws HttpError
	 * @throws ServiceUnavailableError
	 * @throws \Exception
	 * @return Message
	 */
	public function sendImage(string $receiverId, string $message, string $imageId,  $imageStream, string $previewUrl, string $mimeType):Message {
		Statistics::addCall("FacebookMessenger", "sendImage");
		$ip = new Interpreter(new Sandbox(FacebookMessenger::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null, $receiverId, $message, $imageId, $imageStream, $previewUrl, $mimeType];
		$ip->callFunctionSync('sendImage', $auxArray);
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
	 * @param string $receiverId
	 * @param string $message
	 * @param string $videoId
	 * @param resource $videoStream
	 * @param string $previewUrl
	 * @param int $size
	 * @throws IllegalArgumentError
	 * @throws AuthenticationError
	 * @throws NotFoundError
	 * @throws HttpError
	 * @throws ServiceUnavailableError
	 * @throws \Exception
	 * @return Message
	 */
	public function sendVideo(string $receiverId, string $message, string $videoId,  $videoStream, string $previewUrl, int $size):Message {
		Statistics::addCall("FacebookMessenger", "sendVideo");
		$ip = new Interpreter(new Sandbox(FacebookMessenger::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null, $receiverId, $message, $videoId, $videoStream, $previewUrl, $size];
		$ip->callFunctionSync('sendVideo', $auxArray);
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
	 * @param string $receiverId
	 * @param string $message
	 * @param string $audioId
	 * @param resource $audioStream
	 * @param string $previewUrl
	 * @param string $audioName
	 * @param int $size
	 * @throws IllegalArgumentError
	 * @throws AuthenticationError
	 * @throws NotFoundError
	 * @throws HttpError
	 * @throws ServiceUnavailableError
	 * @throws \Exception
	 * @return Message
	 */
	public function sendAudio(string $receiverId, string $message, string $audioId,  $audioStream, string $previewUrl, string $audioName, int $size):Message {
		Statistics::addCall("FacebookMessenger", "sendAudio");
		$ip = new Interpreter(new Sandbox(FacebookMessenger::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null, $receiverId, $message, $audioId, $audioStream, $previewUrl, $audioName, $size];
		$ip->callFunctionSync('sendAudio', $auxArray);
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
	 * @param string $receiverId
	 * @param string $message
	 * @param string $fileId
	 * @param resource $fileStream
	 * @param string $previewUrl
	 * @param string $fileName
	 * @param int $size
	 * @throws IllegalArgumentError
	 * @throws AuthenticationError
	 * @throws NotFoundError
	 * @throws HttpError
	 * @throws ServiceUnavailableError
	 * @throws \Exception
	 * @return Message
	 */
	public function sendFile(string $receiverId, string $message, string $fileId,  $fileStream, string $previewUrl, string $fileName, int $size):Message {
		Statistics::addCall("FacebookMessenger", "sendFile");
		$ip = new Interpreter(new Sandbox(FacebookMessenger::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null, $receiverId, $message, $fileId, $fileStream, $previewUrl, $fileName, $size];
		$ip->callFunctionSync('sendFile', $auxArray);
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
	 * @param string $receiverId
	 * @param array $messageItem
	 * @throws IllegalArgumentError
	 * @throws AuthenticationError
	 * @throws NotFoundError
	 * @throws HttpError
	 * @throws ServiceUnavailableError
	 * @throws \Exception
	 * @return Message
	 */
	public function sendCarousel(string $receiverId, array $messageItem):Message {
		Statistics::addCall("FacebookMessenger", "sendCarousel");
		$ip = new Interpreter(new Sandbox(FacebookMessenger::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null, $receiverId, $messageItem];
		$ip->callFunctionSync('sendCarousel', $auxArray);
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
	 * @param resource $httpRequest
	 * @throws IllegalArgumentError
	 * @throws AuthenticationError
	 * @throws NotFoundError
	 * @throws HttpError
	 * @throws ServiceUnavailableError
	 * @throws \Exception
	 * @return array
	 */
	public function parseReceivedMessages( $httpRequest):array {
		Statistics::addCall("FacebookMessenger", "parseReceivedMessages");
		$ip = new Interpreter(new Sandbox(FacebookMessenger::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null, $httpRequest];
		$ip->callFunctionSync('processWebhookRequest', $auxArray);
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
	 * @param MessagingAttachment $attachment
	 * @throws IllegalArgumentError
	 * @throws AuthenticationError
	 * @throws NotFoundError
	 * @throws HttpError
	 * @throws ServiceUnavailableError
	 * @throws \Exception
	 * @return MessagingAttachment
	 */
	public function downloadContent(MessagingAttachment $attachment):MessagingAttachment {
		Statistics::addCall("FacebookMessenger", "downloadContent");
		$ip = new Interpreter(new Sandbox(FacebookMessenger::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null, $attachment];
		$ip->callFunctionSync('downloadContent', $auxArray);
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
		Statistics::addCall("FacebookMessenger", "advancedRequest");
		$ip = new Interpreter(new Sandbox(FacebookMessenger::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		$ip = new Interpreter(new Sandbox(FacebookMessenger::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		return $ip->saveAsString();
	}

	/**
	 * @param string $savedState
	 */
	public function loadAsString($savedState) {
		$sandbox = new Sandbox(FacebookMessenger::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage);
		$ip = new Interpreter($sandbox);
		$ip->loadAsString($savedState);
		$this->persistentStorage = $sandbox->persistentStorage;
	}
}
