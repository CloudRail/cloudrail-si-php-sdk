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

class Viber implements Messaging, AdvancedRequestSupporter
{
	private static $SERVICE_CODE = [
		'init' => [
			["string.concat", '$P0.baseURL', "https://chatapi.viber.com/pa"]
		],
		'AdvancedRequestSupporter:advancedRequest' => [
			["create", '$L0', "Object"],
			["create", '$L0.url', "String"],
			["if!=than", '$P2.appendBaseUrl', 0, 1],
			["set", '$L0.url', '$P0.baseURL'],
			["string.concat", '$L0.url', '$L0.url', '$P2.url'],
			["set", '$L0.requestHeaders', '$P2.headers'],
			["set", '$L0.method', '$P2.method'],
			["set", '$L0.requestBody', '$P2.body'],
			["if==than", '$L0.requestHeaders', NULL, 1],
			["create", '$L0.requestHeaders', "Object"],
			["if!=than", '$P2.appendAuthorization', 0, 1],
			["string.concat", '$L0.requestHeaders.X-Viber-Auth-Token', '$P0.botToken'],
			["http.requestCall", '$L1', '$L0'],
			["if!=than", '$P2.checkErrors', 0, 1],
			["callFunc", "validateResponse", '$P0', '$L1'],
			["create", '$P1', "AdvancedRequestResponse"],
			["set", '$P1.status', '$L1.code'],
			["set", '$P1.headers', '$L1.responseHeaders'],
			["set", '$P1.body', '$L1.responseBody']
		],
		'setWebhook' => [
			["create", '$L0', "Object"],
			["string.concat", '$L0.url', '$P0.baseURL', "/set_webhook"],
			["set", '$L0.method', "POST"],
			["create", '$L3', "Array"],
			["push", '$L3', "delivered"],
			["push", '$L3', "seen"],
			["create", '$L4', "Object"],
			["set", '$L4.url', '$P0.webhook'],
			["set", '$L4.event_types', '$L3'],
			["json.stringify", '$L4', '$L4'],
			["size", '$L20', '$L4'],
			["string.concat", '$L21', '$L20'],
			["create", '$L2', "Object"],
			["set", '$L2', '$P0.botToken', "X-Viber-Auth-Token"],
			["set", '$L2', "application/json", "Content-Type"],
			["set", '$L2', '$L21', "Content-Length"],
			["set", '$L0.requestHeaders', '$L2'],
			["stream.stringToStream", '$L0.requestBody', '$L4'],
			["http.requestCall", '$L0', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L0']
		],
		'processWebhookRequest' => [
			["json.parse", '$L0', '$P2'],
			["if!=than", '$L0.event', "message", 1],
			["return"],
			["set", '$L3', NULL],
			["set", '$L2', '$L0.message.location'],
			["if!=than", '$L2', NULL, 3],
			["create", '$L3', "Location"],
			["set", '$L3.Longitude', '$L2.lng'],
			["set", '$L3.Latitude', '$L2.lat'],
			["set", '$L4', NULL],
			["if!=than", '$L0.message.type', "text", 6],
			["create", '$L4', "Array"],
			["set", '$L8', '$L0.message.type'],
			["if==than", '$L8', "picture", 1],
			["set", '$L8', "image"],
			["create", '$L5', "MessagingAttachment", '$L0.message.media', '$L8', NULL, NULL, NULL],
			["push", '$L4', '$L5'],
			["create", '$L6', "Message"],
			["if!=than", '$L0.message_token', NULL, 2],
			["string.concat", '$L11', '$L0.message_token'],
			["set", '$L6.MessageId', '$L11'],
			["if!=than", '$L0.sender.id', NULL, 3],
			["string.concat", '$L12', '$L0.sender.id'],
			["set", '$L6.SenderId', '$L12'],
			["set", '$L6.ChatId', '$L12'],
			["if!=than", '$L0.user_id', NULL, 3],
			["string.concat", '$L14', '$L0.sender.id'],
			["set", '$L6.SenderId', '$L14'],
			["set", '$L6.ChatId', '$L14'],
			["create", '$L7', "Date"],
			["set", '$L7', '$L7.time'],
			["set", '$L6.SendAt', '$L7'],
			["if!=than", '$L0.message.text', NULL, 2],
			["string.concat", '$L15', '$L0.message.text'],
			["set", '$L6.MessageText', '$L15'],
			["if!=than", '$L3', NULL, 1],
			["set", '$L6.Location', '$L3'],
			["if!=than", '$L4', NULL, 1],
			["set", '$L6.Attachments', '$L4'],
			["create", '$P1', "Array"],
			["push", '$P1', '$L6']
		],
		'sendMessage' => [
			["callFunc", "checkMandatory", '$P0', '$P2', "chatId"],
			["callFunc", "checkMandatory", '$P3', "message text"],
			["callFunc", "send", '$P0', '$P1', '$P2', "text", '$P3', NULL, NULL, NULL, NULL]
		],
		'sendImage' => [
			["callFunc", "checkMandatory", '$P0', '$P2', "chatId"],
			["callFunc", "checkMandatory", '$P4', "content URL"],
			["if==than", '$P6', NULL, 1],
			["set", '$P6', "https://webhooks.cloudrail.com/home/ubuntu/server/media/cloudrail_small_preview_viber.jpg"],
			["callFunc", "send", '$P0', '$P1', '$P2', "picture", '$P3', '$P4', '$P6', NULL, NULL]
		],
		'sendVideo' => [
			["callFunc", "checkMandatory", '$P0', '$P2', "chatId"],
			["callFunc", "checkMandatory", '$P4', "content URL"],
			["callFunc", "checkMandatory", '$P7', "video size"],
			["if==than", '$P6', NULL, 1],
			["set", '$P6', "https://webhooks.cloudrail.com/home/ubuntu/server/media/cloudrail_small_preview_viber.jpg"],
			["callFunc", "send", '$P0', '$P1', '$P2', "video", '$P3', '$P4', '$P6', NULL, '$P7']
		],
		'sendAudio' => [
			["callFunc", "checkMandatory", '$P0', '$P2', "chatId"],
			["callFunc", "checkMandatory", '$P4', "content URL"],
			["callFunc", "checkMandatory", '$P7', "mp3 file name"],
			["callFunc", "checkMandatory", '$P8', "file size"],
			["callFunc", "send", '$P0', '$P1', '$P2', "file", NULL, '$P4', NULL, '$P7', '$P8']
		],
		'sendFile' => [
			["callFunc", "checkMandatory", '$P0', '$P2', "chatId"],
			["callFunc", "checkMandatory", '$P4', "content URL"],
			["callFunc", "checkMandatory", '$P7', "file name"],
			["callFunc", "checkMandatory", '$P8', "file size"],
			["callFunc", "send", '$P0', '$P1', '$P2', "file", NULL, '$P4', NULL, '$P7', '$P8']
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
			["set", '$L0.method', "POST"],
			["string.concat", '$L0.url', '$P0.baseURL', "/send_message"],
			["create", '$L5', "Object"],
			["set", '$L5.name', '$P0.botName'],
			["create", '$L1', "Object"],
			["set", '$L1.receiver', '$P2'],
			["set", '$L1.type', "rich_media"],
			["set", '$L1.min_api_version', "2"],
			["create", '$L1.rich_media', "Object"],
			["set", '$L1.rich_media.Type', "rich_media"],
			["set", '$L1.rich_media.ButtonsGroupColumns', "6"],
			["set", '$L1.rich_media.ButtonsGroupRows', "7"],
			["set", '$L1.rich_media.BgColor', "#FFFFFF"],
			["create", '$L1.rich_media.Buttons', "Array"],
			["create", '$L3', "Number", 0],
			["size", '$L4', '$P3'],
			["if<than", '$L3', '$L4', 16],
			["get", '$L5', '$P3', '$L3'],
			["create", '$L6', "Object"],
			["callFunc", "makeCarouselRow", '$P0', '$L15', "image", NULL, NULL, NULL, '$L5.mediaUrl', NULL],
			["push", '$L1.rich_media.Buttons', '$L15'],
			["callFunc", "makeCarouselRow", '$P0', '$L16', "text", '$L5.title', '$L5.subTitle', NULL, NULL, NULL],
			["push", '$L1.rich_media.Buttons', '$L16'],
			["create", '$L7', "Number", 0],
			["size", '$L8', '$L5.buttons'],
			["if<than", '$L7', '$L8', 5],
			["get", '$L9', '$L5.buttons', '$L7'],
			["callFunc", "makeCarouselRow", '$P0', '$L17', "button", '$L9.text', NULL, '$L9.type', '$L9.url', NULL],
			["push", '$L1.rich_media.Buttons', '$L17'],
			["math.add", '$L7', '$L7', 1],
			["jumpRel", -6],
			["math.add", '$L3', '$L3', 1],
			["jumpRel", -17],
			["json.stringify", '$L1', '$L1'],
			["stream.stringToStream", '$L0.requestBody', '$L1'],
			["size", '$L20', '$L1'],
			["string.concat", '$L21', '$L20'],
			["create", '$L2', "Object"],
			["set", '$L2', '$P0.botToken', "X-Viber-Auth-Token"],
			["set", '$L2', "application/json", "Content-Type"],
			["set", '$L2', '$L21', "Content-Length"],
			["set", '$L0.requestHeaders', '$L2'],
			["create", '$L3', "Object"],
			["http.requestCall", '$L3', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L3'],
			["create", '$L4', "Date"],
			["set", '$L8', '$L4.time'],
			["create", '$P1', "Message"],
			["set", '$P1.SenderId', '$P2'],
			["set", '$P1.ChatId', '$P2'],
			["set", '$P1.SendAt', '$L8']
		],
		'send' => [
			["create", '$L0', "Object"],
			["set", '$L0.method', "POST"],
			["string.concat", '$L0.url', '$P0.baseURL', "/send_message"],
			["create", '$L5', "Object"],
			["set", '$L5.name', '$P0.botName'],
			["create", '$L1', "Object"],
			["string.concat", '$L20', '$P2'],
			["set", '$L1.receiver', '$L20'],
			["set", '$L1.type', '$P3'],
			["set", '$L1.sender', '$L5'],
			["if!=than", '$P4', NULL, 1],
			["set", '$L1.text', '$P4'],
			["if!=than", '$P5', NULL, 1],
			["set", '$L1.media', '$P5'],
			["if!=than", '$P6', NULL, 1],
			["set", '$L1.thumbnail', '$P6'],
			["if!=than", '$P7', NULL, 1],
			["set", '$L1.file_name', '$P7'],
			["if!=than", '$P8', NULL, 1],
			["set", '$L1.size', '$P8'],
			["json.stringify", '$L1', '$L1'],
			["stream.stringToStream", '$L0.requestBody', '$L1'],
			["size", '$L20', '$L1'],
			["string.concat", '$L21', '$L20'],
			["create", '$L2', "Object"],
			["set", '$L2', '$P0.botToken', "X-Viber-Auth-Token"],
			["set", '$L2', "application/json", "Content-Type"],
			["set", '$L2', '$L21', "Content-Length"],
			["set", '$L0.requestHeaders', '$L2'],
			["create", '$L3', "Object"],
			["http.requestCall", '$L3', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L3'],
			["create", '$L4', "Date"],
			["set", '$L8', '$L4.time'],
			["create", '$P1', "Message"],
			["set", '$P1.SenderId', '$P2'],
			["set", '$P1.ChatId', '$P2'],
			["set", '$P1.SendAt', '$L8'],
			["if!=than", '$P4', NULL, 1],
			["set", '$P1.MessageText', '$P4']
		],
		'makeCarouselRow' => [
			["create", '$P1', "Object"],
			["set", '$P1.Columns', "6"],
			["if==than", '$P2', "image", 2],
			["set", '$P1.Rows', "3"],
			["set", '$P1.Image', '$P6'],
			["if==than", '$P2', "text", 4],
			["set", '$P1.Rows', "2"],
			["string.concat", '$L1', "<font color=#323232><b>", '$P3', "</b></font><br />", '$P4'],
			["set", '$P1.Text', '$L1'],
			["set", '$P1.TextHAlign', "left"],
			["if==than", '$P2', "button", 4],
			["set", '$P1.Rows', "1"],
			["string.concat", '$L1', "<font color=#8367db><b>", '$P3', "</b></font>"],
			["set", '$P1.Text', '$L1'],
			["set", '$P1.TextHAlign', "middle"],
			["if!=than", '$P2', "image", 2],
			["set", '$P1.TextSize', "medium"],
			["set", '$P1.TextVAlign', "middle"],
			["if!=than", '$P6', "button", 3],
			["set", '$P1.ActionType', ""],
			["set", '$P1.ActionBody', ""],
			["return"],
			["if!=than", '$P6', NULL, 3],
			["set", '$P1.ActionType', "open-url"],
			["set", '$P1.ActionBody', '$P6'],
			["return"],
			["set", '$P1.ActionType', "reply"],
			["set", '$P1.ActionBody', '$P7']
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
	 * @param string $webhook
	 * @param string $botName
	 */
	public function __construct(callable $redirectReceiver, string $botToken, string $webhook, string $botName)
	{
		$this->interpreterStorage = array();
		$this->instanceDependencyStorage = ["redirectReceiver" => $redirectReceiver];
		$this->persistentStorage = array(array());
		InitSelfTest::initTest('Viber');
		
		$this->interpreterStorage['botToken'] = $botToken;
		$this->interpreterStorage['webhook'] = $webhook;
		$this->interpreterStorage['botName'] = $botName;
		

		$ip = new Interpreter(new Sandbox(Viber::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		if (array_key_exists("init",Viber::$SERVICE_CODE)) {
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
		Statistics::addCall("Viber", "sendMessage");
		$ip = new Interpreter(new Sandbox(Viber::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Viber", "sendImage");
		$ip = new Interpreter(new Sandbox(Viber::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Viber", "sendVideo");
		$ip = new Interpreter(new Sandbox(Viber::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Viber", "sendAudio");
		$ip = new Interpreter(new Sandbox(Viber::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Viber", "sendFile");
		$ip = new Interpreter(new Sandbox(Viber::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Viber", "sendCarousel");
		$ip = new Interpreter(new Sandbox(Viber::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Viber", "parseReceivedMessages");
		$ip = new Interpreter(new Sandbox(Viber::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Viber", "downloadContent");
		$ip = new Interpreter(new Sandbox(Viber::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Viber", "advancedRequest");
		$ip = new Interpreter(new Sandbox(Viber::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		$ip = new Interpreter(new Sandbox(Viber::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		return $ip->saveAsString();
	}

	/**
	 * @param string $savedState
	 */
	public function loadAsString($savedState) {
		$sandbox = new Sandbox(Viber::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage);
		$ip = new Interpreter($sandbox);
		$ip->loadAsString($savedState);
		$this->persistentStorage = $sandbox->persistentStorage;
	}
}
