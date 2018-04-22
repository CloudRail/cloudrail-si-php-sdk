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

class MailJet implements Email, AdvancedRequestSupporter
{
	private static $SERVICE_CODE = [
		'init' => [
			["set", '$P0.baseUrl', "https://api.mailjet.com/v3"]
		],
		'sendMJEMail' => [
			["callFunc", "validateParameters", '$P0', '$P1', '$P2', '$P3', '$P4', '$P5', '$P6'],
			["create", '$L1', "Object"],
			["string.concat", '$L1.url', '$P0.baseUrl', "/send"],
			["set", '$L1.method', "POST"],
			["create", '$L1.requestHeaders', "Object"],
			["set", '$L1.requestHeaders.Content-Type', "application/json"],
			["create", '$L7', "String"],
			["string.concat", '$L7', '$P0.clientId', ":", '$P0.clientSecret'],
			["string.base64encode", '$L7', '$L7'],
			["string.concat", '$L1.requestHeaders.Authorization', "Basic ", '$L7'],
			["create", '$L2', "Object"],
			["set", '$L2.FromEmail', '$P1'],
			["set", '$L2.FromName', '$P2'],
			["create", '$L3', "Array"],
			["set", '$L3', '$P3'],
			["callFunc", "processAddresses", '$P0', '$L20', '$L3'],
			["set", '$L2.To', '$L20'],
			["set", '$L2.Subject', '$P4'],
			["if!=than", '$P5', NULL, 1],
			["set", '$L2.Text-part', '$P5'],
			["if!=than", '$P6', NULL, 1],
			["set", '$L2.Html-part', '$P6'],
			["create", '$L4', "Array"],
			["set", '$L4', '$P7'],
			["if==than", '$L4', NULL, 1],
			["set", '$L2.Cc', NULL],
			["if!=than", '$L4', NULL, 2],
			["callFunc", "processAddresses", '$P0', '$L21', '$L4'],
			["set", '$L2.Cc', '$L21'],
			["create", '$L5', "Array"],
			["set", '$L5', '$P8'],
			["if==than", '$L5', NULL, 1],
			["set", '$L2.Bcc', NULL],
			["if!=than", '$L5', NULL, 2],
			["callFunc", "processAddresses", '$P0', '$L23', '$L5'],
			["set", '$L2.Bcc', '$L23'],
			["callFunc", "processAttachments", '$P0', '$L2', '$P9'],
			["json.stringify", '$L6', '$L2'],
			["stream.stringToStream", '$L1.requestBody', '$L6'],
			["create", '$L8', "Object"],
			["http.requestCall", '$L8', '$L1'],
			["callFunc", "validateResponse", '$P0', '$L8'],
			["create", '$L9', "String"],
			["stream.streamToString", '$L9', '$L8.responseBody'],
			["debug.out", '$L9']
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
			["if!=than", '$P2.appendAuthorization', 0, 3],
			["string.concat", '$L1', '$P0.clientId', ":", '$P0.clientSecret'],
			["string.base64encode", '$L1', '$L1'],
			["string.concat", '$L0.requestHeaders.Authorization', "Basic ", '$L1'],
			["http.requestCall", '$L1', '$L0'],
			["if!=than", '$P2.checkErrors', 0, 1],
			["callFunc", "validateResponse", '$P0', '$L1'],
			["create", '$P1', "AdvancedRequestResponse"],
			["set", '$P1.status', '$L1.code'],
			["set", '$P1.headers', '$L1.responseHeaders'],
			["set", '$P1.body', '$L1.responseBody']
		],
		'buildRequestObject' => [
			["create", '$L0', "Object"],
			["set", '$L0', "application/json", "Content-Type"],
			["create", '$P1', "Object"],
			["set", '$P1.url', "https://api.mailjet.com/v3/send"],
			["set", '$P1.method', "POST"],
			["set", '$P1.requestHeaders', '$L0']
		],
		'processAttachments' => [
			["if==than", '$P2', NULL, 1],
			["return"],
			["size", '$L0', '$P2'],
			["if==than", '$L0', 0, 1],
			["return"],
			["create", '$L1', "Number", 0],
			["create", '$P1.attachments', "Array"],
			["if<than", '$L1', '$L0', 15],
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
			["set", '$L3.Content-type', '$L2.mimeType'],
			["set", '$L3.Filename', '$L2.filename'],
			["push", '$P1.attachments', '$L3'],
			["math.add", '$L1', '$L1', 1],
			["jumpRel", -16]
		],
		'processAddresses' => [
			["create", '$L0', "Number"],
			["size", '$L0', '$P2'],
			["create", '$L1', "Number", 0],
			["create", '$L2', "String", ""],
			["if<than", '$L1', '$L0', 5],
			["create", '$L3', "String"],
			["get", '$L3', '$P2', '$L1'],
			["string.concat", '$L2', '$L2', '$L3', ","],
			["math.add", '$L1', '$L1', 1],
			["jumpRel", -6],
			["size", '$L4', '$L2'],
			["if!=than", '$L4', 0, 2],
			["math.add", '$L4', '$L4', -1],
			["string.substr", '$L2', '$L2', 0, '$L4'],
			["set", '$P1', '$L2']
		],
		'validateParameters' => [
			["if==than", '$P1', NULL, 2],
			["create", '$L0', "Error", "A valid sender address has to be provided.", "IllegalArgument"],
			["throwError", '$L0'],
			["if==than", '$P2', NULL, 2],
			["create", '$L0', "Error", "A sender name has to be provided.", "IllegalArgument"],
			["throwError", '$L0'],
			["if==than", '$P3', NULL, 2],
			["create", '$L0', "Error", "At least one address of a recipient has to be provided.", "IllegalArgument"],
			["throwError", '$L0'],
			["if==than", '$P4', NULL, 2],
			["create", '$L0', "Error", "A subject has to be provided.", "IllegalArgument"],
			["throwError", '$L0'],
			["if==than", '$P5', NULL, 6],
			["if==than", '$P6', NULL, 2],
			["create", '$L0', "Error", "Either textBody or htmlBody has to be set (or both).", "IllegalArgument"],
			["throwError", '$L0'],
			["if==than", '$P6', "", 2],
			["create", '$L0', "Error", "Either textBody or htmlBody has to be set (or both).", "IllegalArgument"],
			["throwError", '$L0'],
			["if==than", '$P5', "", 6],
			["if==than", '$P6', NULL, 2],
			["create", '$L0', "Error", "Either textBody or htmlBody has to be set (or both).", "IllegalArgument"],
			["throwError", '$L0'],
			["if==than", '$P6', "", 2],
			["create", '$L0', "Error", "Either textBody or htmlBody has to be set (or both).", "IllegalArgument"],
			["throwError", '$L0'],
			["if==than", '$P1', "", 2],
			["create", '$L0', "Error", "A valid sender address has to be provided.", "IllegalArgument"],
			["throwError", '$L0'],
			["if==than", '$P2', "", 2],
			["create", '$L0', "Error", "A sender name has to be provided.", "IllegalArgument"],
			["throwError", '$L0'],
			["if==than", '$P4', "", 2],
			["create", '$L0', "Error", "A subject has to be provided.", "IllegalArgument"],
			["throwError", '$L0'],
			["size", '$L1', '$P3'],
			["if==than", '$L1', 0, 2],
			["create", '$L0', "Error", "At least one address of a recipient has to be provided.", "IllegalArgument"],
			["throwError", '$L0']
		],
		'validateResponse' => [
			["if>=than", '$P1.code', 400, 9],
			["if==than", '$P1.code', 401, 2],
			["create", '$L3', "Error", "Invalid credentials or access rights. Make sure that your application has read and write permission.", "Authentication"],
			["throwError", '$L3'],
			["if==than", '$P1.code', 503, 2],
			["create", '$L3', "Error", "Service unavailable. Try again later.", "ServiceUnavailable"],
			["throwError", '$L3'],
			["string.concat", '$L2', '$P1.code', " - ", '$P1.message'],
			["create", '$L3', "Error", '$L2', "Http"],
			["throwError", '$L3']
		],
		'checkMandatory' => [
			["if==than", '$P1', NULL, 3],
			["string.concat", '$L1', "Field ", '$P2', " is mandatory"],
			["create", '$L0', "Error", '$L1', "IllegalArgument"],
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
	 */
	public function __construct(string $clientId, string $clientSecret)
	{
		$this->interpreterStorage = array();
		$this->instanceDependencyStorage = [];
		$this->persistentStorage = array(array());
		InitSelfTest::initTest('MailJet');
		
		$this->interpreterStorage['clientId'] = $clientId;
		$this->interpreterStorage['clientSecret'] = $clientSecret;
		

		$ip = new Interpreter(new Sandbox(MailJet::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		if (array_key_exists("init",MailJet::$SERVICE_CODE)) {
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
		Statistics::addCall("MailJet", "sendEmail");
		$ip = new Interpreter(new Sandbox(MailJet::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, $fromAddress, $fromName, $toAddresses, $subject, $textBody, $htmlBody, $ccAddresses, $bccAddresses, $attachments];
		$ip->callFunctionSync('sendMJEMail', $auxArray);
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
		Statistics::addCall("MailJet", "advancedRequest");
		$ip = new Interpreter(new Sandbox(MailJet::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		$ip = new Interpreter(new Sandbox(MailJet::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		return $ip->saveAsString();
	}

	/**
	 * @param string $savedState
	 */
	public function loadAsString($savedState) {
		$sandbox = new Sandbox(MailJet::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage);
		$ip = new Interpreter($sandbox);
		$ip->loadAsString($savedState);
		$this->persistentStorage = $sandbox->persistentStorage;
	}
}
