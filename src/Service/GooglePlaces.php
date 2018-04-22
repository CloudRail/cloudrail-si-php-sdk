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

use CloudRail\Interfaces\PointsOfInterest;

use CloudRail\Interfaces\AdvancedRequestSupporter;
use CloudRail\Type\AdvancedRequestSpecification;
use CloudRail\Type\AdvancedRequestResponse;
use CloudRail\Type\CloudRailError;

class GooglePlaces implements PointsOfInterest, AdvancedRequestSupporter
{
	private static $SERVICE_CODE = [
		'init' => [
			["set", '$P0.baseUrl', "https://maps.googleapis.com/maps/api/place"],
			["create", '$P0.crToPlaces', "Object"],
			["create", '$P0.placesToCr', "Object"],
			["callFunc", "addCategory", '$P0', "airport", "airport"],
			["callFunc", "addCategory", '$P0', "atm", "atm"],
			["callFunc", "addCategory", '$P0', "amusement_park", "amusement_park"],
			["callFunc", "addCategory", '$P0', "aquarium", "aquarium"],
			["callFunc", "addCategory", '$P0', "art_gallery", "art_gallery"],
			["callFunc", "addCategory", '$P0', "bakery", "bakery"],
			["callFunc", "addCategory", '$P0', "bank", "bank"],
			["callFunc", "addCategory", '$P0', "bar", "bar"],
			["callFunc", "addCategory", '$P0', "beauty_salon", "beauty_salon"],
			["callFunc", "addCategory", '$P0', "bicycle_store", "bicycle_store"],
			["callFunc", "addCategory", '$P0', "book_store", "book_store"],
			["callFunc", "addCategory", '$P0', "bowling_alley", "bowling_alley"],
			["callFunc", "addCategory", '$P0', "bus_station", "bus_station"],
			["callFunc", "addCategory", '$P0', "cafe", "cafe"],
			["callFunc", "addCategory", '$P0', "car_dealer", "car_dealer"],
			["callFunc", "addCategory", '$P0', "car_rental", "car_rental"],
			["callFunc", "addCategory", '$P0', "car_repair", "car_repair"],
			["callFunc", "addCategory", '$P0', "car_wash", "car_wash"],
			["callFunc", "addCategory", '$P0', "casino", "casino"],
			["callFunc", "addCategory", '$P0', "cemetery", "cemetery"],
			["callFunc", "addCategory", '$P0', "church", "church"],
			["callFunc", "addCategory", '$P0', "clothing_store", "clothing_store"],
			["callFunc", "addCategory", '$P0', "convenience_store", "convenience_store"],
			["callFunc", "addCategory", '$P0', "courthouse", "courthouse"],
			["callFunc", "addCategory", '$P0', "dentist", "dentist"],
			["callFunc", "addCategory", '$P0', "department_store", "department_store"],
			["callFunc", "addCategory", '$P0', "doctor", "doctor"],
			["callFunc", "addCategory", '$P0', "electronics_store", "electronics_store"],
			["callFunc", "addCategory", '$P0', "embassy", "embassy"],
			["callFunc", "addCategory", '$P0', "finance", "finance"],
			["callFunc", "addCategory", '$P0', "fire_station", "fire_station"],
			["callFunc", "addCategory", '$P0', "florist", "florist"],
			["callFunc", "addCategory", '$P0', "food", "food"],
			["callFunc", "addCategory", '$P0', "funeral_home", "funeral_home"],
			["callFunc", "addCategory", '$P0', "furniture_store", "furniture_store"],
			["callFunc", "addCategory", '$P0', "gas_station", "gas_station"],
			["callFunc", "addCategory", '$P0', "grocery_or_supermarket", "grocery_or_supermarket"],
			["callFunc", "addCategory", '$P0', "gym", "gym"],
			["callFunc", "addCategory", '$P0', "hardware_store", "hardware_store"],
			["callFunc", "addCategory", '$P0', "health", "health"],
			["callFunc", "addCategory", '$P0', "hindu_temple", "hindu_temple"],
			["callFunc", "addCategory", '$P0', "hospital", "hospital"],
			["callFunc", "addCategory", '$P0', "jewelry_store", "jewelry_store"],
			["callFunc", "addCategory", '$P0', "laundry", "laundry"],
			["callFunc", "addCategory", '$P0', "lawyer", "lawyer"],
			["callFunc", "addCategory", '$P0', "library", "library"],
			["callFunc", "addCategory", '$P0', "locksmith", "locksmith"],
			["callFunc", "addCategory", '$P0', "mosque", "mosque"],
			["callFunc", "addCategory", '$P0', "movie_theater", "movie_theater"],
			["callFunc", "addCategory", '$P0', "museum", "museum"],
			["callFunc", "addCategory", '$P0', "night_club", "night_club"],
			["callFunc", "addCategory", '$P0', "parks", "parks"],
			["callFunc", "addCategory", '$P0', "parking", "parking"],
			["callFunc", "addCategory", '$P0', "pet_store", "pet_store"],
			["callFunc", "addCategory", '$P0', "pharmacy", "pharmacy"],
			["callFunc", "addCategory", '$P0', "physiotherapist", "physiotherapist"],
			["callFunc", "addCategory", '$P0', "police", "police"],
			["callFunc", "addCategory", '$P0', "post_office", "post_office"],
			["callFunc", "addCategory", '$P0', "real_estate_agency", "real_estate_agency"],
			["callFunc", "addCategory", '$P0', "restaurant", "restaurant"],
			["callFunc", "addCategory", '$P0', "rv_park", "rv_park"],
			["callFunc", "addCategory", '$P0', "school", "school"],
			["callFunc", "addCategory", '$P0', "shoe_store", "shoe_store"],
			["callFunc", "addCategory", '$P0', "shopping_mall", "shopping_mall"],
			["callFunc", "addCategory", '$P0', "spa", "spa"],
			["callFunc", "addCategory", '$P0', "stadium", "stadium"],
			["callFunc", "addCategory", '$P0', "synagogue", "synagogue"],
			["callFunc", "addCategory", '$P0', "taxi_stand", "taxi_stand"],
			["callFunc", "addCategory", '$P0', "train_station", "train_station"],
			["callFunc", "addCategory", '$P0', "travel_agency", "travel_agency"],
			["callFunc", "addCategory", '$P0', "university", "university"],
			["callFunc", "addCategory", '$P0', "veterinary_care", "veterinary_care"],
			["callFunc", "addCategory", '$P0', "zoo", "zoo"]
		],
		'getNearbyPOIs' => [
			["callFunc", "checkNull", '$P0', '$P2', "Latitude"],
			["callFunc", "checkNull", '$P0', '$P3', "Longitude"],
			["callFunc", "checkNull", '$P0', '$P4', "Radius"],
			["callFunc", "checkLessThan", '$P0', '$P2', -90, "Latitude"],
			["callFunc", "checkLessThan", '$P0', '$P3', -180, "Longitude"],
			["callFunc", "checkLessThan", '$P0', '$P4', 0, "Radius"],
			["callFunc", "checkGreaterThan", '$P0', '$P2', 90, "Latitude"],
			["callFunc", "checkGreaterThan", '$P0', '$P3', 180, "Longitude"],
			["callFunc", "checkGreaterThan", '$P0', '$P4', 40000, "Radius"],
			["if!=than", '$P6', NULL, 1],
			["callFunc", "checkIsEmpty", '$P0', '$P6', "Categories"],
			["create", '$L0', "Object"],
			["set", '$L0.method', "GET"],
			["string.concat", '$L1', '$P0.baseUrl', "/nearbysearch/json?"],
			["string.concat", '$L1', '$L1', "key=", '$P0.apiKey'],
			["string.concat", '$L2', '$P2', ",", '$P3'],
			["string.urlEncode", '$L2', '$L2'],
			["string.concat", '$L1', '$L1', "&location=", '$L2'],
			["string.concat", '$L1', '$L1', "&radius=", '$P4'],
			["if!=than", '$P5', NULL, 2],
			["string.urlEncode", '$L2', '$P5'],
			["string.concat", '$L1', '$L1', "&keyword=", '$L2'],
			["if!=than", '$P6', NULL, 3],
			["callFunc", "getCategoriesString", '$P0', '$L2', '$P6'],
			["string.urlEncode", '$L2', '$L2'],
			["string.concat", '$L1', '$L1', "&types=", '$L2'],
			["set", '$L0.url', '$L1'],
			["http.requestCall", '$L2', '$L0'],
			["callFunc", "checkHttpResponse", '$P0', '$L3', '$L2', 1],
			["create", '$P1', "Array"],
			["create", '$L4', "Number", 0],
			["size", '$L5', '$L3.results'],
			["if<than", '$L4', '$L5', 5],
			["get", '$L6', '$L3.results', '$L4'],
			["callFunc", "extractPOI", '$P0', '$L7', '$L6'],
			["push", '$P1', '$L7'],
			["math.add", '$L4', '$L4', 1],
			["jumpRel", -6]
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
			["string.concat", '$L0.url', '$L0.url', "key=", '$P0.apiKey'],
			["http.requestCall", '$L1', '$L0'],
			["if!=than", '$P2.checkErrors', 0, 1],
			["callFunc", "checkHttpResponse", '$P0', NULL, '$L1'],
			["create", '$P1', "AdvancedRequestResponse"],
			["set", '$P1.status', '$L1.code'],
			["set", '$P1.headers', '$L1.responseHeaders'],
			["set", '$P1.body', '$L1.responseBody']
		],
		'checkNull' => [
			["if==than", '$P1', NULL, 3],
			["string.concat", '$L0', '$P2', " is not allowed to be null."],
			["create", '$L1', "Error", '$L0', "IllegalArgument"],
			["throwError", '$L1']
		],
		'checkLessThan' => [
			["if<than", '$P1', '$P2', 3],
			["string.concat", '$L0', '$P3', " is not allowed to be less than ", '$P2', "."],
			["create", '$L1', "Error", '$L0', "IllegalArgument"],
			["throwError", '$L1']
		],
		'checkGreaterThan' => [
			["if>than", '$P1', '$P2', 3],
			["string.concat", '$L0', '$P3', " is not allowed to be greater than ", '$P2', "."],
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
		'checkHttpResponse' => [
			["if>=than", '$P2.code', 400, 10],
			["if==than", '$P2.code', 401, 2],
			["create", '$L3', "Error", "Invalid credentials or access rights. Make sure that your application has read and write permission.", "Authentication"],
			["throwError", '$L3'],
			["if==than", '$P2.code', 503, 2],
			["create", '$L3', "Error", "Service unavailable. Try again later.", "ServiceUnavailable"],
			["throwError", '$L3'],
			["json.parse", '$L0', '$P2.responseBody'],
			["string.concat", '$L2', '$P2.code', " - ", '$L0.status'],
			["create", '$L3', "Error", '$L2', "Http"],
			["throwError", '$L3'],
			["if!=than", '$P3', NULL, 9],
			["json.parse", '$L0', '$P2.responseBody'],
			["if!=than", '$L0.status', "OK", 6],
			["if!=than", '$L0.error_message', NULL, 2],
			["create", '$L3', "Error", '$L0.error_message', "Http"],
			["throwError", '$L3'],
			["if==than", '$L0.error_message', NULL, 2],
			["create", '$L3', "Error", '$L0.status', "Http"],
			["throwError", '$L3'],
			["set", '$P1', '$L0']
		],
		'getCategoriesString' => [
			["create", '$P1', "String"],
			["create", '$L0', "Number", 0],
			["size", '$L1', '$P2'],
			["if<than", '$L0', '$L1', 10],
			["get", '$L2', '$P2', '$L0'],
			["get", '$L2', '$P0.crToPlaces', '$L2'],
			["if==than", '$L2', NULL, 2],
			["create", '$L3', "Error", "Unknown category.", "IllegalArgument"],
			["throwError", '$L3'],
			["if!=than", '$L0', 0, 1],
			["string.concat", '$P1', '$P1', "|"],
			["string.concat", '$P1', '$P1', '$L2'],
			["math.add", '$L0', '$L0', 1],
			["jumpRel", -11]
		],
		'extractPOI' => [
			["create", '$L0', "Array"],
			["create", '$L1', "Number", 0],
			["size", '$L2', '$P2.types'],
			["if<than", '$L1', '$L2', 6],
			["get", '$L3', '$P2.types', '$L1'],
			["get", '$L4', '$P0.placesToCr', '$L3'],
			["if!=than", '$L4', NULL, 1],
			["push", '$L0', '$L4'],
			["math.add", '$L1', '$L1', 1],
			["jumpRel", -7],
			["create", '$L1', "Location"],
			["set", '$L1.latitude', '$P2.geometry.location.lat'],
			["set", '$L1.longitude', '$P2.geometry.location.lng'],
			["if!=than", '$P2.photos', NULL, 3],
			["get", '$L2', '$P2.photos', 0],
			["get", '$L3', '$L2.photo_reference'],
			["string.concat", '$L4', "https://maps.googleapis.com/maps/api/place/photo?key=", '$P0.apiKey', "&photoreference=", '$L3', "&maxheight=", '$L2.height'],
			["create", '$P1', "POI", '$L0', '$L4', '$L1', '$P2.name', NULL]
		],
		'addCategory' => [
			["set", '$P0.crToPlaces', '$P2', '$P1'],
			["set", '$P0.placesToCr', '$P1', '$P2']
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
		InitSelfTest::initTest('GooglePlaces');
		
		$this->interpreterStorage['apiKey'] = $apiKey;
		

		$ip = new Interpreter(new Sandbox(GooglePlaces::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		if (array_key_exists("init",GooglePlaces::$SERVICE_CODE)) {
			$parameters = [&$this->interpreterStorage];
		  $ip->callFunctionSync("init",$parameters );
		}
	}

	
	/**
	 * @param float $latitude
	 * @param float $longitude
	 * @param int $radius
	 * @param string $searchTerm
	 * @param array $categories
	 * @throws IllegalArgumentError
	 * @throws AuthenticationError
	 * @throws NotFoundError
	 * @throws HttpError
	 * @throws ServiceUnavailableError
	 * @throws \Exception
	 * @return array
	 */
	public function getNearbyPOIs(float $latitude, float $longitude, int $radius, string $searchTerm, array $categories):array {
		Statistics::addCall("GooglePlaces", "getNearbyPOIs");
		$ip = new Interpreter(new Sandbox(GooglePlaces::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null, $latitude, $longitude, $radius, $searchTerm, $categories];
		$ip->callFunctionSync('getNearbyPOIs', $auxArray);
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
		Statistics::addCall("GooglePlaces", "advancedRequest");
		$ip = new Interpreter(new Sandbox(GooglePlaces::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		$ip = new Interpreter(new Sandbox(GooglePlaces::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		return $ip->saveAsString();
	}

	/**
	 * @param string $savedState
	 */
	public function loadAsString($savedState) {
		$sandbox = new Sandbox(GooglePlaces::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage);
		$ip = new Interpreter($sandbox);
		$ip->loadAsString($savedState);
		$this->persistentStorage = $sandbox->persistentStorage;
	}
}
