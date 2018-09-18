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

use CloudRail\Interfaces\Email;

use CloudRail\Interfaces\AdvancedRequestSupporter;
use CloudRail\Type\AdvancedRequestSpecification;
use CloudRail\Type\AdvancedRequestResponse;
use CloudRail\Type\CloudRailError;

class SendGrid implements Email, AdvancedRequestSupporter
{
	private static $SERVICE_CODE = [
		'init' => [
			["set", '$P0.baseUrl', "https://api.sendgrid.com/v3"]
		],
		'sendEmail' => [
			["callFunc", "checkMandatory", '$P0', '$P1', "fromAddress"],
			["callFunc", "checkMandatory", '$P0', '$P2', "fromName"],
			["callFunc", "checkMandatory", '$P0', '$P3', "toAddresses"],
			["callFunc", "checkMandatory", '$P0', '$P4', "subject"],
			["callFunc", "checkEmptyList", '$P0', '$P3', "toAddresses"],
			["callFunc", "checkEmpty", '$P0', '$P1', "fromAddress"],
			["callFunc", "checkEmpty", '$P0', '$P2', "fromName"],
			["callFunc", "checkEmpty", '$P0', '$P4', "subject"],
			["set", '$L2', 1],
			["set", '$L3', 1],
			["if==than", '$P5', NULL, 1],
			["set", '$L2', 0],
			["if==than", '$P5', "", 1],
			["set", '$L2', 0],
			["if==than", '$P6', NULL, 1],
			["set", '$L3', 0],
			["if==than", '$P6', "", 1],
			["set", '$L3', 0],
			["if==than", '$L2', 0, 3],
			["if==than", '$L3', 0, 2],
			["create", '$L4', "Error", "Either a textBody or a htmlBody must be provided!", "IllegalArgument"],
			["throwError", '$L4'],
			["create", '$L0', "Object"],
			["set", '$L0.method', "POST"],
			["string.concat", '$L0.url', '$P0.baseUrl', "/mail/send"],
			["create", '$L0.requestHeaders', "Object"],
			["set", '$L0.requestHeaders.Content-Type', "application/json"],
			["string.concat", '$L0.requestHeaders.Authorization', "Bearer ", '$P0.apiKey'],
			["create", '$L1', "Object"],
			["create", '$L1.from', "Object"],
			["set", '$L1.from.email', '$P1'],
			["set", '$L1.from.name', '$P2'],
			["set", '$L1.subject', '$P4'],
			["create", '$L1.content', "Array"],
			["if==than", '$L2', 1, 4],
			["create", '$L5', "Object"],
			["set", '$L5.type', "text/plain"],
			["set", '$L5.value', '$P5'],
			["push", '$L1.content', '$L5'],
			["if==than", '$L3', 1, 4],
			["create", '$L6', "Object"],
			["set", '$L6.type', "text/html"],
			["set", '$L6.value', '$P6'],
			["push", '$L1.content', '$L6'],
			["create", '$L1.personalizations', "Array"],
			["create", '$L7', "Object"],
			["callFunc", "pushAddresses", '$P0', '$L7.to', '$P3'],
			["callFunc", "pushAddresses", '$P0', '$L7.cc', '$P7'],
			["callFunc", "pushAddresses", '$P0', '$L7.bcc', '$P8'],
			["push", '$L1.personalizations', '$L7'],
			["callFunc", "pushAttachments", '$P0', '$L1', '$P9'],
			["json.stringify", '$L1', '$L1'],
			["stream.stringToStream", '$L0.requestBody', '$L1'],
			["http.requestCall", '$L0', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L0']
		],
		'AdvancedRequestSupporter:advancedRequest' => [
			["create", '$L0', "Object"],
			["if!=than", '$P2.appendBaseUrl', 0, 2],
			["string.concat", '$L0.url', '$P0.baseUrl', '$P2.url'],
			["jumpRel", 1],
			["set", '$L0.url', '$P2.url'],
			["set", '$L0.requestHeaders', '$P2.headers'],
			["set", '$L0.method', '$P2.method'],
			["set", '$L0.requestBody', '$P2.body'],
			["if==than", '$L0.requestHeaders', NULL, 1],
			["create", '$L0.requestHeaders', "Object"],
			["if!=than", '$P2.appendAuthorization', 0, 1],
			["string.concat", '$L0.requestHeaders.Authorization', "Bearer ", '$P0.apiKey'],
			["http.requestCall", '$L1', '$L0'],
			["if!=than", '$P2.checkErrors', 0, 1],
			["callFunc", "validateResponse", '$P0', '$L1'],
			["create", '$P1', "AdvancedRequestResponse"],
			["set", '$P1.status', '$L1.code'],
			["set", '$P1.headers', '$L1.responseHeaders'],
			["set", '$P1.body', '$L1.responseBody']
		],
		'pushAddresses' => [
			["if==than", '$P2', NULL, 1],
			["return"],
			["size", '$L0', '$P2'],
			["if==than", '$L0', 0, 1],
			["return"],
			["create", '$P1', "Array"],
			["set", '$L1', 0],
			["get", '$L2', '$P2', '$L1'],
			["create", '$L3', "Object"],
			["set", '$L3.email', '$L2'],
			["push", '$P1', '$L3'],
			["math.add", '$L1', 1],
			["if<than", '$L1', '$L0', 1],
			["jumpRel", -7]
		],
		'pushAttachments' => [
			["if==than", '$P2', NULL, 1],
			["return"],
			["size", '$L0', '$P2'],
			["if==than", '$L0', 0, 1],
			["return"],
			["create", '$L1', "Number", 0],
			["create", '$P1.attachments', "Array"],
			["if<than", '$L1', '$L0', 16],
			["get", '$L2', '$P2', '$L1'],
			["set", '$L4', '$L2.content'],
			["set", '$L5', '$L2.filename'],
			["callFunc", "checkMandatory", '$P0', '$L4', "content"],
			["callFunc", "checkMandatory", '$P0', '$L5', "filename"],
			["create", '$L3', "Object"],
			["create", '$L4', "String"],
			["stream.streamToData", '$L4', '$L2.content'],
			["string.base64encode", '$L4', '$L4'],
			["set", '$L3.content', '$L4'],
			["if!=than", '$L2.mimeType', NULL, 1],
			["set", '$L3.type', '$L2.mimeType'],
			["set", '$L3.filename', '$L2.filename'],
			["push", '$P1.attachments', '$L3'],
			["math.add", '$L1', '$L1', 1],
			["jumpRel", -17]
		],
		'checkMandatory' => [
			["if==than", '$P1', NULL, 3],
			["string.concat", '$L1', "Field ", '$P2', " is mandatory"],
			["create", '$L0', "Error", '$L1', "IllegalArgument"],
			["throwError", '$L0']
		],
		'checkEmptyList' => [
			["size", '$L0', '$P1'],
			["if==than", '$L0', 0, 3],
			["string.concat", '$L2', "The list ", '$P2', " cannot be empty"],
			["create", '$L1', "Error", '$L2', "IllegalArgument"],
			["throwError", '$L1']
		],
		'checkEmpty' => [
			["if==than", '$P1', "", 3],
			["string.concat", '$L0', "Field ", '$P2', " is mandatory"],
			["create", '$L1', "Error", '$L0', "IllegalArgument"],
			["throwError", '$L1']
		],
		'validateResponse' => [
			["if>=than", '$P1.code', 400, 10],
			["if==than", '$P1.code', 401, 2],
			["create", '$L3', "Error", "Invalid credentials or access rights. Make sure that your application has read and write permission.", "Authentication"],
			["throwError", '$L3'],
			["if==than", '$P1.code', 503, 2],
			["create", '$L3', "Error", "Service unavailable. Try again later.", "ServiceUnavailable"],
			["throwError", '$L3'],
			["json.parse", '$L0', '$P1.responseBody'],
			["json.stringify", '$L0', '$L0.errors'],
			["create", '$L3', "Error", '$L0', "Http"],
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
	 * @param string $apiKey
	 */
	public function __construct(string $apiKey)
	{
		$this->interpreterStorage = array();
		$this->instanceDependencyStorage = [];
		$this->persistentStorage = array(array());
		InitSelfTest::initTest('SendGrid');
		
		$this->interpreterStorage['apiKey'] = $apiKey;
		

		$ip = new Interpreter(new Sandbox(SendGrid::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		if (array_key_exists("init",SendGrid::$SERVICE_CODE)) {
			$parameters = [&$this->interpreterStorage];
		  $ip->callFunctionSync("init",$parameters );
		}
	}

	
	/**
	 * @param string $fromAddress
	 * @param string $fromName
	 * @param array $toAddresses
	 * @param string $subject
	 * @param string $textBody
	 * @param string $htmlBody
	 * @param array $ccAddresses
	 * @param array $bccAddresses
	 * @param array $attachments
	 * @throws IllegalArgumentError
	 * @throws AuthenticationError
	 * @throws NotFoundError
	 * @throws HttpError
	 * @throws ServiceUnavailableError
	 * @throws \Exception
	 */
	public function sendEmail(string $fromAddress, string $fromName, array $toAddresses, string $subject, string $textBody, string $htmlBody, array $ccAddresses, array $bccAddresses, array $attachments):void {
		Statistics::addCall("SendGrid", "sendEmail");
		$ip = new Interpreter(new Sandbox(SendGrid::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, $fromAddress, $fromName, $toAddresses, $subject, $textBody, $htmlBody, $ccAddresses, $bccAddresses, $attachments];
		$ip->callFunctionSync('sendEmail', $auxArray);
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
		Statistics::addCall("SendGrid", "advancedRequest");
		$ip = new Interpreter(new Sandbox(SendGrid::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		$ip = new Interpreter(new Sandbox(SendGrid::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		return $ip->saveAsString();
	}

	/**
	 * @param string $savedState
	 */
	public function loadAsString($savedState) {
		$sandbox = new Sandbox(SendGrid::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage);
		$ip = new Interpreter($sandbox);
		$ip->loadAsString($savedState);
		$this->persistentStorage = $sandbox->persistentStorage;
	}
}
