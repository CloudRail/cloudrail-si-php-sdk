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

use CloudRail\Type\CloudRailError;

class Twizo implements SMS
{
	private static $SERVICE_CODE = [
		'init' => [
			["set", '$P0.baseUrl', "https://api-asia-01.twizo.com"]
		],
		'sendSMS' => [
			["callFunc", "validateUserInput", '$P0', '$P1', '$P2', '$P3'],
			["create", '$L0', "Object"],
			["set", '$L0.method', "POST"],
			["string.concat", '$L0.url', '$P0.baseUrl', "/sms/submitsimple"],
			["create", '$L1', "Object"],
			["set", '$L0.requestHeaders', '$L1'],
			["set", '$L1.Accept', "application/json"],
			["set", '$L1.Content-Type', "application/json; charset=utf8"],
			["string.concat", '$L2', "twizo:", '$P0.key'],
			["string.base64encode", '$L3', '$L2'],
			["string.concat", '$L1.Authorization', "Basic ", '$L3'],
			["create", '$L2', "Object"],
			["set", '$L2.tag', "cloudrail"],
			["set", '$L2.body', '$P3'],
			["set", '$L2.sender', '$P1'],
			["create", '$L3', "Array"],
			["push", '$L3', '$P2'],
			["set", '$L2.recipients', '$L3'],
			["json.stringify", '$L4', '$L2'],
			["stream.stringToStream", '$L0.requestBody', '$L4'],
			["http.requestCall", '$L1', '$L0'],
			["callFunc", "validateResponse", '$P0', '$L1']
		],
		'validateUserInput' => [
			["if==than", '$P1', NULL, 2],
			["create", '$L0', "Error", "The sender name is not allowed to be null.", "IllegalArgument"],
			["throwError", '$L0'],
			["if==than", '$P2', NULL, 2],
			["create", '$L0', "Error", "The recipient is not allowed to be null.", "IllegalArgument"],
			["throwError", '$L0'],
			["if==than", '$P3', NULL, 2],
			["create", '$L0', "Error", "The content is not allowed to be null.", "IllegalArgument"],
			["throwError", '$L0'],
			["if==than", '$P1', "", 2],
			["create", '$L0', "Error", "The sender name is not allowed to be empty.", "IllegalArgument"],
			["throwError", '$L0'],
			["if==than", '$P2', "", 2],
			["create", '$L0', "Error", "The recipient is not allowed to be empty.", "IllegalArgument"],
			["throwError", '$L0'],
			["if==than", '$P3', "", 2],
			["create", '$L0', "Error", "The content is not allowed to be empty.", "IllegalArgument"],
			["throwError", '$L0']
		],
		'validateResponse' => [
			["if>=than", '$P1.code', 400, 4],
			["json.parse", '$L0', '$P1.responseBody'],
			["json.stringify", '$L0', '$L0'],
			["create", '$L2', "Error", '$L0', "Http"],
			["throwError", '$L2']
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
	 * @param string $key
	 */
	public function __construct(string $key)
	{
		$this->interpreterStorage = array();
		$this->instanceDependencyStorage = [];
		$this->persistentStorage = array(array());
		InitSelfTest::initTest('Twizo');
		
		$this->interpreterStorage['key'] = $key;
		

		$ip = new Interpreter(new Sandbox(Twizo::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		if (array_key_exists("init",Twizo::$SERVICE_CODE)) {
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
		Statistics::addCall("Twizo", "sendSMS");
		$ip = new Interpreter(new Sandbox(Twizo::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, $fromName, $toNumber, $content];
		$ip->callFunctionSync('sendSMS', $auxArray);
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
	 * @return string
	 */
	public function saveAsString() {
		$ip = new Interpreter(new Sandbox(Twizo::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		return $ip->saveAsString();
	}

	/**
	 * @param string $savedState
	 */
	public function loadAsString($savedState) {
		$sandbox = new Sandbox(Twizo::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage);
		$ip = new Interpreter($sandbox);
		$ip->loadAsString($savedState);
		$this->persistentStorage = $sandbox->persistentStorage;
	}
}
