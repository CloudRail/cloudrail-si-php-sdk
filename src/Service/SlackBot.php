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

class SlackBot implements Messaging, AdvancedRequestSupporter
{
	private static $SERVICE_CODE = [
		'init' => [
			["set", '$P0.baseURL', "https://slack.com/api/"],
			["set", '$P0.boundaryString', "------7V0ub86bNNNKWdgJgsF7r0DxYtOB06XYxWvyMuYg5BucWEINpyFRcqisOXWr"]
		],
		'AdvancedRequestSupporter:advancedRequest' => [
		],
		'processWebhookRequest' => [
			["stream.streamToString", '$L1', '$P2'],
			["string.indexOf", '$L2', '$L1', "payload"],
			["if==than", '$L2', 0, 2],
			["string.urlDecode", '$L1', '$L1'],
			["string.substring", '$L1', '$L1', 8],
			["json.parse", '$L0', '$L1'],
			["if==than", '$L0.type', "interactive_message", 2],
			["callFunc", "extractInteractiveMessageObject", '$P0', '$L1', '$L0'],
			["jumpRel", 1],
			["callFunc", "extractMessageObject", '$P0', '$L1', '$L0.event'],
			["create", '$P1', "Array"],
			["push", '$P1', '$L1']
		],
		'sendMessage' => [
			["create", '$L0', "Object"],
			["string.concat", '$L0.url', '$P0.baseURL', "chat.postMessage"],
			["create", '$L0.requestHeaders', "Object"],
			["set", '$L0.requestHeaders.Content-Type', "application/json"],
			["string.concat", '$L0.requestHeaders.Authorization', "Bearer ", '$P0.botToken'],
			["create", '$L3', "Object"],
			["set", '$L3.channel', '$P2'],
			["if!=than", '$P3', NULL, 1],
			["set", '$L3.text', '$P3'],
			["if!=than", '$P4', NULL, 3],
			["set", '$L3.replace_original', "false"],
			["set", '$L3.delete_original', "false"],
			["set", '$L3.attachments', '$P4'],
			["json.stringify", '$L4', '$L3'],
			["stream.stringToStream", '$L0.requestBody', '$L4'],
			["set", '$L0.method', "POST"],
			["create", '$L1', "Object"],
			["http.requestCall", '$L1', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L1'],
			["json.parse", '$L2', '$L1.responseBody'],
			["callFunc", "extractMessageObject", '$P0', '$P1', '$L2.message']
		],
		'sendImage' => [
			["callFunc", "sendContent", '$P0', '$P1', '$P2', '$P3', '$P4', '$P5', '$P6', '$P7']
		],
		'sendVideo' => [
			["callFunc", "sendContent", '$P0', '$P1', '$P2', '$P3', '$P4', '$P5', '$P6', '$P7']
		],
		'sendAudio' => [
			["callFunc", "sendContent", '$P0', '$P1', '$P2', '$P3', '$P4', '$P5', '$P6', '$P7']
		],
		'sendFile' => [
			["callFunc", "sendContent", '$P0', '$P1', '$P2', '$P3', '$P4', '$P5', '$P6', '$P7']
		],
		'sendCarousel' => [
			["set", '$L0', 0],
			["size", '$L1', '$P3'],
			["create", '$L2', "Array"],
			["if<than", '$L0', '$L1', 28],
			["get", '$L3', '$P3', '$L0'],
			["create", '$L4', "Object"],
			["set", '$L4.text', '$L3.title'],
			["if!=than", '$L3.subTitle', NULL, 1],
			["string.concat", '$L4.text', '$L4.text', " ", '$L3.subTitle'],
			["set", '$L4.fallback', "default"],
			["set", '$L4.callback_id', "default"],
			["set", '$L4.attachment_type', "default"],
			["set", '$L4.image_url', '$L3.mediaUrl'],
			["create", '$L4.actions', "Array"],
			["set", '$L5', 0],
			["size", '$L6', '$L3.buttons'],
			["if<than", '$L5', '$L6', 12],
			["get", '$L7', '$L3.buttons', '$L5'],
			["create", '$L8', "Object"],
			["set", '$L8.name', "someName"],
			["set", '$L8.text', '$L7.text'],
			["set", '$L8.type', "button"],
			["if!=than", '$L7.url', NULL, 2],
			["set", '$L8.value', '$L7.url'],
			["jumpRel", 1],
			["set", '$L8.value', '$L7.payload'],
			["push", '$L4.actions', '$L8'],
			["math.add", '$L5', '$L5', 1],
			["jumpRel", -13],
			["push", '$L2', '$L4'],
			["math.add", '$L0', '$L0', 1],
			["jumpRel", -29],
			["callFunc", "sendMessage", '$P0', '$P1', '$P2', "", '$L2']
		],
		'downloadContent' => [
			["create", '$L0', "Object"],
			["set", '$L0.url', '$P2.id'],
			["set", '$L0.method', "GET"],
			["create", '$L0.requestHeaders', "Object"],
			["string.concat", '$L0.requestHeaders.Authorization', "Bearer ", '$P0.botToken'],
			["create", '$L1', "Object"],
			["http.requestCall", '$L1', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L1'],
			["set", '$P1', '$P2'],
			["set", '$P1.stream', '$L1.responseBody'],
			["if==than", '$P1.mimeType', NULL, 1],
			["set", '$P1.mimeType', '$L4.responseHeaders.Content-Type']
		],
		'extractMessageObject' => [
			["create", '$P1', "Message"],
			["set", '$P1.senderId', '$P2.user'],
			["set", '$P1.chatId', '$P2.channel'],
			["set", '$P1.messageText', '$P2.text'],
			["set", '$P1.messageId', '$P2.ts'],
			["set", '$P1.sendAt', '$P2.ts'],
			["if!=than", '$P2.subtype', NULL, 2],
			["if==than", '$P2.subtype', "file_share", 1],
			["callFunc", "addAttachment", '$P0', '$P1', '$P2.file']
		],
		'extractInteractiveMessageObject' => [
			["create", '$P1', "Message"],
			["set", '$P1.senderId', '$P2.user.id'],
			["set", '$P1.chatId', '$P2.channel.id'],
			["set", '$P1.messageId', '$P2.action_ts'],
			["set", '$P1.replyTo', '$P2.message_ts'],
			["set", '$P1.sendAt', '$P2.action_ts'],
			["get", '$L1', '$P2.actions', 0],
			["set", '$P1.messageText', '$L1.value']
		],
		'addAttachment' => [
			["if!=than", '$P2.url_private_download', NULL, 2],
			["set", '$L1', '$P2.url_private_download'],
			["jumpRel", 1],
			["set", '$L1', '$P2.url_private'],
			["create", '$L0', "MessagingAttachment", '$L1', "file", '$P2.mimetype', NULL, NULL],
			["create", '$P1.Attachments', "Array"],
			["push", '$P1.Attachments', '$L0']
		],
		'sendContent' => [
			["if==than", '$P4', NULL, 2],
			["callFunc", "uploadContent", '$P0', '$P1', '$P5', '$P2', '$P7'],
			["return"],
			["callFunc", "sendMessage", '$P0', '$P1', '$P2', '$P4']
		],
		'uploadContent' => [
			["create", '$L0', "Object"],
			["string.concat", '$L0.url', '$P0.baseURL', "files.upload"],
			["set", '$L0.method', "POST"],
			["create", '$L1', "String"],
			["string.concat", '$L1', '$L1', "--", '$P0.boundaryString', "\r\n"],
			["string.concat", '$L1', '$L1', "Content-Disposition: form-data; name=\"channels\"\r\n"],
			["string.concat", '$L1', '$L1', "\r\n"],
			["string.concat", '$L1', '$L1', '$P3', "\r\n"],
			["string.concat", '$L1', '$L1', "--", '$P0.boundaryString', "\r\n"],
			["string.concat", '$L1', '$L1', "Content-Disposition: form-data; name=\"file\"; filename=\"", '$P3', "\"\r\n"],
			["string.concat", '$L1', '$L1', "Content-Type:image/png", "\r\n"],
			["string.concat", '$L1', '$L1', "\r\n"],
			["string.concat", '$L2', "\r\n--", '$P0.boundaryString', "--\r\n"],
			["stream.stringToStream", '$L3', '$L1'],
			["stream.stringToStream", '$L4', '$L2'],
			["stream.makeJoinedStream", '$L0.requestBody', '$L3', '$P2', '$L4'],
			["create", '$L0.requestHeaders', "Object"],
			["string.concat", '$L0.requestHeaders.Content-Type', "multipart/form-data; boundary=", '$P0.boundaryString'],
			["string.concat", '$L0.requestHeaders.Authorization', "Bearer ", '$P0.botToken'],
			["create", '$L2', "Object"],
			["http.requestCall", '$L2', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L2'],
			["json.parse", '$L3', '$L2.responseBody']
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
		InitSelfTest::initTest('SlackBot');
		
		$this->interpreterStorage['botToken'] = $botToken;
		

		$ip = new Interpreter(new Sandbox(SlackBot::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		if (array_key_exists("init",SlackBot::$SERVICE_CODE)) {
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
		Statistics::addCall("SlackBot", "sendMessage");
		$ip = new Interpreter(new Sandbox(SlackBot::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("SlackBot", "sendImage");
		$ip = new Interpreter(new Sandbox(SlackBot::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("SlackBot", "sendVideo");
		$ip = new Interpreter(new Sandbox(SlackBot::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("SlackBot", "sendAudio");
		$ip = new Interpreter(new Sandbox(SlackBot::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("SlackBot", "sendFile");
		$ip = new Interpreter(new Sandbox(SlackBot::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("SlackBot", "sendCarousel");
		$ip = new Interpreter(new Sandbox(SlackBot::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("SlackBot", "parseReceivedMessages");
		$ip = new Interpreter(new Sandbox(SlackBot::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("SlackBot", "downloadContent");
		$ip = new Interpreter(new Sandbox(SlackBot::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("SlackBot", "advancedRequest");
		$ip = new Interpreter(new Sandbox(SlackBot::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		$ip = new Interpreter(new Sandbox(SlackBot::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		return $ip->saveAsString();
	}

	/**
	 * @param string $savedState
	 */
	public function loadAsString($savedState) {
		$sandbox = new Sandbox(SlackBot::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage);
		$ip = new Interpreter($sandbox);
		$ip->loadAsString($savedState);
		$this->persistentStorage = $sandbox->persistentStorage;
	}
}
