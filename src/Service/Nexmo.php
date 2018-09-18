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

use CloudRail\Interfaces\SMS;

use CloudRail\Interfaces\AdvancedRequestSupporter;
use CloudRail\Type\AdvancedRequestSpecification;
use CloudRail\Type\AdvancedRequestResponse;
use CloudRail\Type\CloudRailError;

class Nexmo implements SMS, AdvancedRequestSupporter
{
	private static $SERVICE_CODE = [
		'init' => [
			["set", '$P0.baseUrl', "https://rest.nexmo.com"]
		],
		'SendNexmoSMS' => [
			["callFunc", "validateUserInput", '$P0', '$P1', '$P2', '$P3'],
			["create", '$L0', "Object"],
			["create", '$L1', "Object"],
			["create", '$L2', "Object"],
			["create", '$L3', "String"],
			["create", '$L4', "String"],
			["string.urlEncode", '$L3', '$P3'],
			["string.urlEncode", '$L5', '$P1'],
			["string.concat", '$L0.url', '$P0.baseUrl', "/sms/json?api_key=", '$P0.clientId', "&api_secret=", '$P0.clientSecret', "&to=", '$P2', "&from=", '$L5', "&text=", '$L3'],
			["set", '$L0.method', "POST"],
			["set", '$L1.Content-Type', "application/x-www-form-urlencoded"],
			["set", '$L1.Content-Length', "0"],
			["set", '$L0.requestHeaders', '$L1'],
			["http.requestCall", '$L2', '$L0'],
			["callFunc", "checkError", '$P0', '$L2', 1]
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
			["if!=than", '$P2.appendAuthorization', 0, 6],
			["string.indexOf", '$L1', '$L0.url', "?"],
			["if==than", '$L1', -1, 2],
			["string.concat", '$L0.url', '$L0.url', "?"],
			["jumpRel", 1],
			["string.concat", '$L0.url', '$L0.url', "&"],
			["string.concat", '$L0.url', '$L0.url', "api_key=", '$P0.clientId', "&api_secret=", '$P0.clientSecret'],
			["http.requestCall", '$L1', '$L0'],
			["if!=than", '$P2.checkErrors', 0, 1],
			["callFunc", "checkError", '$P0', '$L1'],
			["create", '$P1', "AdvancedRequestResponse"],
			["set", '$P1.status', '$L1.code'],
			["set", '$P1.headers', '$L1.responseHeaders'],
			["set", '$P1.body', '$L1.responseBody']
		],
		'validateUserInput' => [
			["if==than", '$P1', NULL, 2],
			["create", '$L1', "Error", "One of the arguments is 'null'. You need to assign a value to it.", "IllegalArgument"],
			["throwError", '$L1'],
			["if==than", '$P2', NULL, 2],
			["create", '$L1', "Error", "One of the arguments is 'null'. You need to assign a value to it.", "IllegalArgument"],
			["throwError", '$L1'],
			["if==than", '$P3', NULL, 2],
			["create", '$L1', "Error", "One of the arguments is 'null'. You need to assign a value to it.", "IllegalArgument"],
			["throwError", '$L1'],
			["size", '$L2', '$P1'],
			["if==than", '$L2', 0, 2],
			["create", '$L1', "Error", "The 'From' number parameter is empty.", "IllegalArgument"],
			["throwError", '$L1'],
			["size", '$L2', '$P2'],
			["if==than", '$L2', 0, 2],
			["create", '$L1', "Error", "The 'To' number parameter is empty.", "IllegalArgument"],
			["throwError", '$L1'],
			["size", '$L2', '$P3'],
			["if==than", '$L2', 0, 2],
			["create", '$L1', "Error", "The message is empty.", "IllegalArgument"],
			["throwError", '$L1'],
			["size", '$L2', '$P1'],
			["if>than", '$L2', 16, 2],
			["create", '$L1', "Error", "The 'From' phone number is too big, it should have maximum 15 digits. Example: +16175551212", "IllegalArgument"],
			["throwError", '$L1'],
			["string.lastIndexOf", '$L0', '$P2', "+"],
			["if!=than", '$L0', 0, 2],
			["create", '$L1', "Error", "The 'To' phone number isn't in E.164 format. Example: +16175551212", "IllegalArgument"],
			["throwError", '$L1'],
			["size", '$L2', '$P2'],
			["if>than", '$L2', 16, 2],
			["create", '$L1', "Error", "The 'To' phone number is too big, it should have maximum 15 digits. Example: +16175551212", "IllegalArgument"],
			["throwError", '$L1'],
			["size", '$L2', '$P3'],
			["if>than", '$L2', 1600, 2],
			["create", '$L1', "Error", "The length of the message exceeds the 1600 allowed characters.", "IllegalArgument"],
			["throwError", '$L1']
		],
		'checkError' => [
			["if>=than", '$P1.code', 400, 8],
			["if==than", '$P1.code', 401, 2],
			["create", '$L3', "Error", "Invalid credentials or access rights. Make sure that your application has read and write permission.", "Authentication"],
			["throwError", '$L3'],
			["if==than", '$P1.code', 503, 2],
			["create", '$L3', "Error", "Service unavailable. Try again later.", "ServiceUnavailable"],
			["throwError", '$L3'],
			["create", '$L3', "Error", '$P1.message', "Http"],
			["throwError", '$L3'],
			["if!=than", '$P2', NULL, 8],
			["stream.streamToString", '$L11', '$P1.responseBody'],
			["create", '$L12', "Object"],
			["json.parse", '$L12', '$L11'],
			["set", '$L13', '$L12.messages.0.status'],
			["set", '$L14', '$L12.messages.0.error-text'],
			["if!=than", '$L13', "0", 2],
			["create", '$L15', "Error", '$L14', "Http"],
			["throwError", '$L15']
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
		InitSelfTest::initTest('Nexmo');
		
		$this->interpreterStorage['clientId'] = $clientId;
		$this->interpreterStorage['clientSecret'] = $clientSecret;
		

		$ip = new Interpreter(new Sandbox(Nexmo::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		if (array_key_exists("init",Nexmo::$SERVICE_CODE)) {
			$parameters = [&$this->interpreterStorage];
		  $ip->callFunctionSync("init",$parameters );
		}
	}

	
	/**
	 * @param string $fromName
	 * @param string $toNumber
	 * @param string $content
	 * @throws IllegalArgumentError
	 * @throws AuthenticationError
	 * @throws NotFoundError
	 * @throws HttpError
	 * @throws ServiceUnavailableError
	 * @throws \Exception
	 */
	public function sendSMS(string $fromName, string $toNumber, string $content):void {
		Statistics::addCall("Nexmo", "sendSMS");
		$ip = new Interpreter(new Sandbox(Nexmo::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, $fromName, $toNumber, $content];
		$ip->callFunctionSync('SendNexmoSMS', $auxArray);
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
		Statistics::addCall("Nexmo", "advancedRequest");
		$ip = new Interpreter(new Sandbox(Nexmo::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		$ip = new Interpreter(new Sandbox(Nexmo::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		return $ip->saveAsString();
	}

	/**
	 * @param string $savedState
	 */
	public function loadAsString($savedState) {
		$sandbox = new Sandbox(Nexmo::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage);
		$ip = new Interpreter($sandbox);
		$ip->loadAsString($savedState);
		$this->persistentStorage = $sandbox->persistentStorage;
	}
}
