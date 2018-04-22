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

class Line implements Messaging, AdvancedRequestSupporter
{
	private static $SERVICE_CODE = [
		'AdvancedRequestSupporter:advancedRequest' => [
			["create", '$L0', "Object"],
			["create", '$L0.url', "String"],
			["if!=than", '$P2.appendBaseUrl', 0, 1],
			["set", '$L0.url', "https://api.line.me/v2/bot/message"],
			["string.concat", '$L0.url', '$L0.url', '$P2.url'],
			["set", '$L0.requestHeaders', '$P2.headers'],
			["set", '$L0.method', '$P2.method'],
			["set", '$L0.requestBody', '$P2.body'],
			["if==than", '$L0.requestHeaders', NULL, 1],
			["create", '$L0.requestHeaders', "Object"],
			["if!=than", '$P2.appendAuthorization', 0, 1],
			["string.concat", '$L0.requestHeaders.Authorization', "Bearer ", '$P0.botToken'],
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
			["get", '$L1', '$L0.events', 0],
			["if==than", '$L1.source.type', "user", 1],
			["set", '$L2', '$L1.source.userId'],
			["if==than", '$L1.type', "group", 1],
			["set", '$L2', '$L1.source.groupId'],
			["if==than", '$L1.type', "room", 1],
			["set", '$L2', '$L1.source.roomId'],
			["set", '$L3', NULL],
			["if!=than", '$L1.message.longitude', NULL, 3],
			["create", '$L3', "Location"],
			["set", '$L3.Longitude', '$L1.message.longitude'],
			["set", '$L3.Latitude', '$L1.message.latitude'],
			["set", '$L4', NULL],
			["if!=than", '$L1.message.type', "text", 3],
			["create", '$L4', "Array"],
			["create", '$L5', "MessagingAttachment", '$L1.message.id', '$L1.message.type', NULL, NULL, NULL],
			["push", '$L4', '$L5'],
			["create", '$L6', "Message"],
			["if!=than", '$L1.message.id', NULL, 2],
			["string.concat", '$L11', '$L1.message.id'],
			["set", '$L6.MessageId', '$L11'],
			["if!=than", '$L1.source.userId', NULL, 2],
			["string.concat", '$L12', '$L1.source.userId'],
			["set", '$L6.SenderId', '$L12'],
			["if!=than", '$L2', NULL, 2],
			["string.concat", '$L13', '$L2'],
			["set", '$L6.ChatId', '$L13'],
			["if!=than", '$L1.timestamp', NULL, 1],
			["set", '$L6.SendAt', '$L1.timestamp'],
			["if!=than", '$L1.message.text', NULL, 2],
			["string.concat", '$L15', '$L1.message.text'],
			["set", '$L6.MessageText', '$L15'],
			["if!=than", '$L3', NULL, 1],
			["set", '$L6.Location', '$L3'],
			["if!=than", '$L4', NULL, 1],
			["set", '$L6.Attachments', '$L4'],
			["create", '$P1', "Array"],
			["push", '$P1', '$L6']
		],
		'sendMessage' => [
			["callFunc", "checkMandatory", '$P3', "message text"],
			["callFunc", "send", '$P0', '$P1', "text", '$P2', '$P3', NULL, NULL]
		],
		'sendImage' => [
			["callFunc", "checkMandatory", '$P4', "content URL"],
			["if==than", '$P6', NULL, 1],
			["set", '$P6', '$P4'],
			["callFunc", "send", '$P0', '$P1', "image", '$P2', '$P3', '$P4', '$P6']
		],
		'sendVideo' => [
			["callFunc", "checkMandatory", '$P4', "content URL"],
			["if==than", '$P6', NULL, 1],
			["set", '$P6', "https://webhooks.cloudrail.com/home/ubuntu/server/media/cloudrail_preview.png"],
			["callFunc", "send", '$P0', '$P1', "video", '$P2', '$P3', '$P4', '$P6']
		],
		'sendAudio' => [
			["callFunc", "checkMandatory", '$P4', "content URL"],
			["if==than", '$P6', NULL, 1],
			["set", '$P6', "https://webhooks.cloudrail.com/home/ubuntu/server/media/cloudrail_preview.png"],
			["callFunc", "send", '$P0', '$P1', "audio", '$P2', '$P3', '$P4', '$P6']
		],
		'sendFile' => [
		],
		'sendCarousel' => [
			["create", '$L0', "Object"],
			["set", '$L0.method', "POST"],
			["set", '$L0.url', "https://api.line.me/v2/bot/message/push"],
			["create", '$L1', "Object"],
			["set", '$L1.to', '$P2'],
			["create", '$L1.messages', "Array"],
			["create", '$L2', "Object"],
			["set", '$L2.type', "template"],
			["set", '$L2.altText', "this is a template message"],
			["create", '$L2.template', "Object"],
			["set", '$L2.template.type', "carousel"],
			["create", '$L2.template.columns', "Array"],
			["create", '$L3', "Number", 0],
			["size", '$L4', '$P3'],
			["if<than", '$L3', '$L4', 21],
			["get", '$L5', '$P3', '$L3'],
			["create", '$L6', "Object"],
			["set", '$L6.thumbnailImageUrl', '$L5.mediaUrl'],
			["set", '$L6.title', '$L5.title'],
			["set", '$L6.text', '$L5.subTitle'],
			["create", '$L6.actions', "Array"],
			["set", '$L7', 0],
			["size", '$L8', '$L5.buttons'],
			["if<than", '$L7', '$L8', 9],
			["get", '$L9', '$L5.buttons', '$L7'],
			["create", '$L10', "Object"],
			["set", '$L10.type', '$L9.type'],
			["set", '$L10.label', '$L9.text'],
			["set", '$L10.data', '$L9.payload'],
			["set", '$L10.uri', '$L9.url'],
			["push", '$L6.actions', '$L10'],
			["math.add", '$L7', '$L7', 1],
			["jumpRel", -10],
			["push", '$L2.template.columns', '$L6'],
			["math.add", '$L3', '$L3', 1],
			["jumpRel", -22],
			["push", '$L1.messages', '$L2'],
			["json.stringify", '$L1', '$L1'],
			["stream.stringToStream", '$L0.requestBody', '$L1'],
			["create", '$L0.requestHeaders', "Object"],
			["set", '$L0.requestHeaders.Content-Type', "application/json"],
			["string.concat", '$L0.requestHeaders.Authorization', "Bearer ", '$P0.botToken'],
			["size", '$L11', '$L1'],
			["string.concat", '$L12', '$L11'],
			["set", '$L0.requestHeaders.Content-Length', '$L12'],
			["create", '$L13', "Object"],
			["http.requestCall", '$L13', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L13'],
			["create", '$L14', "Date"],
			["set", '$L14', '$L14.time'],
			["create", '$P1', "Message"],
			["set", '$P1.ChatId', '$P2'],
			["set", '$P1.SendAt', '$L14']
		],
		'downloadContent' => [
			["create", '$L0', "Object"],
			["set", '$L0.method', "GET"],
			["string.concat", '$L0.url', "https://api.line.me/v2/bot/message/", '$P2.id', "/content"],
			["create", '$L0.requestHeaders', "Object"],
			["string.concat", '$L0.requestHeaders.Authorization', "Bearer ", '$P0.botToken'],
			["create", '$L1', "Object"],
			["http.requestCall", '$L1', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L1'],
			["set", '$P1', '$P2'],
			["set", '$P1.stream', '$L1.responseBody'],
			["set", '$P1.mimeType', '$L1.responseHeaders.Content-Type']
		],
		'send' => [
			["create", '$L0', "Object"],
			["set", '$L0.method', "POST"],
			["set", '$L0.url', "https://api.line.me/v2/bot/message/push"],
			["create", '$L1', "Object"],
			["set", '$L1.to', '$P3'],
			["create", '$L1.messages', "Array"],
			["if!=than", '$P4', NULL, 4],
			["create", '$L4', "Object"],
			["set", '$L4.type', "text"],
			["set", '$L4.text', '$P4'],
			["push", '$L1.messages', '$L4'],
			["if!=than", '$P5', NULL, 7],
			["create", '$L5', "Object"],
			["set", '$L5.type', '$P2'],
			["set", '$L5.originalContentUrl', '$P5'],
			["set", '$L5.previewImageUrl', '$P6'],
			["if==than", '$P2', "audio", 1],
			["set", '$L5.duration', 1],
			["push", '$L1.messages', '$L5'],
			["json.stringify", '$L1', '$L1'],
			["stream.stringToStream", '$L0.requestBody', '$L1'],
			["create", '$L0.requestHeaders', "Object"],
			["set", '$L0.requestHeaders.Content-Type', "application/json"],
			["string.concat", '$L0.requestHeaders.Authorization', "Bearer ", '$P0.botToken'],
			["size", '$L2', '$L1'],
			["string.concat", '$L20', '$L2'],
			["set", '$L0.requestHeaders.Content-Length', '$L20'],
			["create", '$L3', "Object"],
			["http.requestCall", '$L3', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L3'],
			["create", '$L4', "Date"],
			["set", '$L4', '$L4.time'],
			["create", '$P1', "Message"],
			["set", '$P1.ChatId', '$P3'],
			["set", '$P1.SendAt', '$L4'],
			["set", '$P1.MessageText', '$P4']
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
			["debug.out", '$L5'],
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
		InitSelfTest::initTest('Line');
		
		$this->interpreterStorage['botToken'] = $botToken;
		

		$ip = new Interpreter(new Sandbox(Line::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		if (array_key_exists("init",Line::$SERVICE_CODE)) {
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
		Statistics::addCall("Line", "sendMessage");
		$ip = new Interpreter(new Sandbox(Line::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Line", "sendImage");
		$ip = new Interpreter(new Sandbox(Line::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Line", "sendVideo");
		$ip = new Interpreter(new Sandbox(Line::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Line", "sendAudio");
		$ip = new Interpreter(new Sandbox(Line::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Line", "sendFile");
		$ip = new Interpreter(new Sandbox(Line::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Line", "sendCarousel");
		$ip = new Interpreter(new Sandbox(Line::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Line", "parseReceivedMessages");
		$ip = new Interpreter(new Sandbox(Line::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Line", "downloadContent");
		$ip = new Interpreter(new Sandbox(Line::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Line", "advancedRequest");
		$ip = new Interpreter(new Sandbox(Line::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		$ip = new Interpreter(new Sandbox(Line::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		return $ip->saveAsString();
	}

	/**
	 * @param string $savedState
	 */
	public function loadAsString($savedState) {
		$sandbox = new Sandbox(Line::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage);
		$ip = new Interpreter($sandbox);
		$ip->loadAsString($savedState);
		$this->persistentStorage = $sandbox->persistentStorage;
	}
}
