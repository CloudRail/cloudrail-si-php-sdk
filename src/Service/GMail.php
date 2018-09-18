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

class GMail implements Email, AdvancedRequestSupporter
{
	private static $SERVICE_CODE = [
		'sendEmail' => [
			["callFunc", "checkAuthentication", '$P0'],
			["callFunc", "checkMandatory", '$P0', '$P1', "fromAddress"],
			["callFunc", "checkMandatory", '$P0', '$P2', "fromName"],
			["callFunc", "checkMandatory", '$P0', '$P3', "toAddresses"],
			["callFunc", "checkMandatory", '$P0', '$P4', "subject"],
			["callFunc", "checkEmptyList", '$P0', '$P3', "toAddresses"],
			["callFunc", "checkEmpty", '$P0', '$P1', "fromAddress"],
			["callFunc", "checkEmpty", '$P0', '$P2', "fromName"],
			["callFunc", "checkEmpty", '$P0', '$P4', "subject"],
			["set", '$L6', 1],
			["set", '$L7', 1],
			["if==than", '$P5', NULL, 1],
			["set", '$L6', 0],
			["if==than", '$P5', "", 1],
			["set", '$L6', 0],
			["if==than", '$P6', NULL, 1],
			["set", '$L7', 0],
			["if==than", '$P6', "", 1],
			["set", '$L7', 0],
			["if==than", '$L6', 0, 3],
			["if==than", '$L7', 0, 2],
			["create", '$L8', "Error", "Either a textBody or a htmlBody must be provided!", "IllegalArgument"],
			["throwError", '$L8'],
			["set", '$L11', 0],
			["if!=than", '$P9', NULL, 3],
			["size", '$L1', '$P9'],
			["if!=than", '$L1', 0, 1],
			["set", '$L11', 1],
			["set", '$L12', "nfh39t8gui34fhoifc90a9fhg39pkjoiu90oh4ug"],
			["create", '$L0', "Object"],
			["set", '$L0.method', "POST"],
			["string.concat", '$L0.url', "https://www.googleapis.com/gmail/v1/users/", '$P1', "/messages/send"],
			["create", '$L0.requestHeaders', "Object"],
			["string.concat", '$L0.requestHeaders.Authorization', "Bearer ", '$S0.accessToken'],
			["set", '$L0.requestHeaders.Content-Type', "application/json"],
			["set", '$L0.requestHeaders.uploadType', "multipart"],
			["create", '$L4', "String"],
			["string.concat", '$L4', '$L4', "Content-Type: multipart/mixed; boundary=", '$L12', "\r\n"],
			["string.concat", '$L4', '$L4', "MIME-Version: 1.0\r\n"],
			["create", '$L5', "Date"],
			["string.concat", '$L4', '$L4', "Date: ", '$L5.rfcTime2822', "\r\n"],
			["if!=than", '$P2', NULL, 3],
			["if!=than", '$P2', "", 2],
			["string.concat", '$L4', '$L4', "From: ", '$P2', " <", '$P1', ">", "\r\n"],
			["jumpRel", 1],
			["string.concat", '$L4', '$L4', "From: ", '$P1', "\r\n"],
			["callFunc", "addAddresses", '$P0', '$L4', '$P3', "To"],
			["callFunc", "addAddresses", '$P0', '$L4', '$P7', "Cc"],
			["callFunc", "addAddresses", '$P0', '$L4', '$P8', "Bcc"],
			["string.concat", '$L4', '$L4', "Subject: ", '$P4', "\r\n"],
			["string.concat", '$L4', '$L4', "\r\n"],
			["string.concat", '$L4', '$L4', "--", '$L12', "\r\n"],
			["if==than", '$L7', 1, 2],
			["string.concat", '$L4', '$L4', "Content-Type: text/html\r\n"],
			["jumpRel", 1],
			["string.concat", '$L4', '$L4', "Content-Type: text/plain\r\n"],
			["string.concat", '$L4', '$L4', "Content-Transfer-Encoding: quoted-printable\r\n"],
			["if==than", '$L7', 1, 2],
			["string.concat", '$L4', '$L4', '$P6'],
			["jumpRel", 1],
			["string.concat", '$L4', '$L4', '$P5'],
			["if==than", '$L11', 1, 1],
			["callFunc", "addAttachments", '$P0', '$L4', '$P9', '$L12'],
			["string.concat", '$L4', '$L4', "\r\n\r\n", "--", '$L12', "--"],
			["string.base64encode", '$L4', '$L4', 0, 1],
			["string.concat", '$L0.requestBody', "{\"raw\": \"", '$L4', "\"}"],
			["stream.stringToStream", '$L0.requestBody', '$L0.requestBody'],
			["create", '$L9', "Object"],
			["http.requestCall", '$L9', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L9'],
			["create", '$L10', "String"],
			["stream.streamToString", '$L10', '$L9.responseBody']
		],
		'AdvancedRequestSupporter:advancedRequest' => [
			["create", '$L0', "Object"],
			["create", '$L0.url', "String"],
			["if!=than", '$P2.appendBaseUrl', 0, 1],
			["set", '$L0.url', "https://www.googleapis.com/gmail/v1"],
			["string.concat", '$L0.url', '$L0.url', '$P2.url'],
			["set", '$L0.requestHeaders', '$P2.headers'],
			["set", '$L0.method', '$P2.method'],
			["set", '$L0.requestBody', '$P2.body'],
			["if==than", '$L0.requestHeaders', NULL, 1],
			["create", '$L0.requestHeaders', "Object"],
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
		'addAddresses' => [
			["create", '$L0', "String"],
			["if==than", '$P2', NULL, 1],
			["return"],
			["size", '$L1', '$P2'],
			["if==than", '$L1', 0, 1],
			["return"],
			["create", '$L2', "Number", 0],
			["if<than", '$L2', '$L1', 6],
			["get", '$L3', '$P2', '$L2'],
			["if>than", '$L2', 0, 1],
			["string.concat", '$L0', '$L0', ", "],
			["string.concat", '$L0', '$L0', '$L3'],
			["math.add", '$L2', '$L2', 1],
			["jumpRel", -7],
			["string.concat", '$P1', '$P1', '$P3', ": ", '$L0', "\r\n"]
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
			["string.concat", '$L0', "https://accounts.google.com/o/oauth2/v2/auth?client_id=", '$P0.clientID', "&scope=", "https%3A%2F%2Fwww.googleapis.com%2Fauth%2Fgmail.send", "&response_type=code&prompt=consent&access_type=offline&redirect_uri=", '$P0.redirectUri', "&state=", '$P0.state', "&suppress_webview_warning=true"],
			["awaitCodeRedirect", '$L1', '$L0'],
			["string.concat", '$L2', "client_id=", '$P0.clientID', "&redirect_uri=", '$P0.redirectUri', "&client_secret=", '$P0.clientSecret', "&code=", '$L1', "&grant_type=authorization_code"],
			["jumpRel", 1],
			["string.concat", '$L2', "client_id=", '$P0.clientID', "&redirect_uri=", '$P0.redirectUri', "&client_secret=", '$P0.clientSecret', "&refresh_token=", '$S0.refreshToken', "&grant_type=refresh_token"],
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
		'addAttachments' => [
			["size", '$L0', '$P2'],
			["create", '$L1', "Number", 0],
			["if<than", '$L1', '$L0', 20],
			["get", '$L2', '$P2', '$L1'],
			["set", '$L4', '$L2.content'],
			["set", '$L5', '$L2.filename'],
			["callFunc", "checkMandatory", '$P0', '$L4', "content"],
			["callFunc", "checkMandatory", '$P0', '$L5', "filename"],
			["string.concat", '$P1', '$P1', "\r\n\r\n"],
			["string.concat", '$P1', '$P1', "--", '$P3', "\r\n"],
			["set", '$L10', "application/octet-stream"],
			["if!=than", '$L2.mimeType', NULL, 2],
			["if!=than", '$L2.mimeType', "", 1],
			["set", '$L10', '$L2.mimeType'],
			["string.concat", '$P1', '$P1', "Content-Type: ", '$L10', "; name: ", '$L5', "\r\n"],
			["string.concat", '$P1', '$P1', "MIME-Version: 1.0\r\n"],
			["string.concat", '$P1', '$P1', "Content-Transfer-Encoding: base64\r\n"],
			["string.concat", '$P1', '$P1', "Content-Disposition: attachment; filename=", '$L5', "\r\n\r\n"],
			["stream.streamToData", '$L4', '$L2.content'],
			["string.base64encode", '$L4', '$L4'],
			["string.concat", '$P1', '$P1', '$L4', "\r\n"],
			["math.add", '$L1', '$L1', 1],
			["jumpRel", -21],
			["string.concat", '$P1', '$P1', "\r\n"]
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
			["if>=than", '$P1.code', 400, 12],
			["if==than", '$P1.code', 401, 2],
			["create", '$L0', "Error", "Invalid credentials or access rights. Make sure that your application has read and write permission.", "Authentication"],
			["throwError", '$L0'],
			["if==than", '$P1.code', 503, 2],
			["create", '$L0', "Error", "Service unavailable. Try again later.", "ServiceUnavailable"],
			["throwError", '$L0'],
			["json.parse", '$L1', '$P1.responseBody'],
			["json.stringify", '$L1', '$L1.errors'],
			["create", '$L0', "Error", '$L1', "Http"],
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
	 * @param string $clientID
	 * @param string $clientSecret
	 * @param string $redirectUri
	 * @param string $state
	 */
	public function __construct(string $clientID, string $clientSecret, string $redirectUri, string $state)
	{
		$this->interpreterStorage = array();
		$this->instanceDependencyStorage = [];
		$this->persistentStorage = array(array());
		InitSelfTest::initTest('GMail');
		
		$this->interpreterStorage['clientID'] = $clientID;
		$this->interpreterStorage['clientSecret'] = $clientSecret;
		$this->interpreterStorage['redirectUri'] = $redirectUri;
		$this->interpreterStorage['state'] = $state;
		

		$ip = new Interpreter(new Sandbox(GMail::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		if (array_key_exists("init",GMail::$SERVICE_CODE)) {
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
		Statistics::addCall("GMail", "sendEmail");
		$ip = new Interpreter(new Sandbox(GMail::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("GMail", "advancedRequest");
		$ip = new Interpreter(new Sandbox(GMail::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		$ip = new Interpreter(new Sandbox(GMail::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		return $ip->saveAsString();
	}

	/**
	 * @param string $savedState
	 */
	public function loadAsString($savedState) {
		$sandbox = new Sandbox(GMail::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage);
		$ip = new Interpreter($sandbox);
		$ip->loadAsString($savedState);
		$this->persistentStorage = $sandbox->persistentStorage;
	}
}
