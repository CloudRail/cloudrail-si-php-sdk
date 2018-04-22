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

class Yelp implements PointsOfInterest, AdvancedRequestSupporter
{
	private static $SERVICE_CODE = [
		'init' => [
			["create", '$P0.crToYelp', "Object"],
			["create", '$P0.yelpToCr', "Object"],
			["callFunc", "addCategory", '$P0', "airport", "airports"],
			["callFunc", "addCategory", '$P0', "atm", "bank"],
			["callFunc", "addCategory", '$P0', "amusement_park", "amusementparks"],
			["callFunc", "addCategory", '$P0', "aquarium", "aquariums"],
			["callFunc", "addCategory", '$P0', "art_gallery", "galleries"],
			["callFunc", "addCategory", '$P0', "bakery", "bakeries"],
			["callFunc", "addCategory", '$P0', "bank", "banks"],
			["callFunc", "addCategory", '$P0', "bar", "bars"],
			["callFunc", "addCategory", '$P0', "beauty_salon", "beautysvc"],
			["callFunc", "addCategory", '$P0', "bicycle_store", "bicycles"],
			["callFunc", "addCategory", '$P0', "book_store", "bookstores"],
			["callFunc", "addCategory", '$P0', "bowling_alley", "bowling"],
			["callFunc", "addCategory", '$P0', "bus_station", "buses"],
			["callFunc", "addCategory", '$P0', "cafe", "cafes"],
			["callFunc", "addCategory", '$P0', "car_dealer", "car_dealers"],
			["callFunc", "addCategory", '$P0', "car_rental", "carrental"],
			["callFunc", "addCategory", '$P0', "car_repair", "autorepair"],
			["callFunc", "addCategory", '$P0', "car_wash", "carwash"],
			["callFunc", "addCategory", '$P0', "casino", "casinos"],
			["callFunc", "addCategory", '$P0', "cemetery", "funeralservices"],
			["callFunc", "addCategory", '$P0', "church", "churches"],
			["callFunc", "addCategory", '$P0', "clothing_store", "fashion"],
			["callFunc", "addCategory", '$P0', "convenience_store", "convenience"],
			["callFunc", "addCategory", '$P0', "courthouse", "courthouses"],
			["callFunc", "addCategory", '$P0', "dentist", "dentists"],
			["callFunc", "addCategory", '$P0', "department_store", "deptstores"],
			["callFunc", "addCategory", '$P0', "doctor", "physicians"],
			["callFunc", "addCategory", '$P0', "electronics_store", "electronics"],
			["callFunc", "addCategory", '$P0', "embassy", "embassy"],
			["callFunc", "addCategory", '$P0', "finance", "financialservices"],
			["callFunc", "addCategory", '$P0', "fire_station", "firedepartments"],
			["callFunc", "addCategory", '$P0', "florist", "florists"],
			["callFunc", "addCategory", '$P0', "food", "food"],
			["callFunc", "addCategory", '$P0', "funeral_home", "funeralservices"],
			["callFunc", "addCategory", '$P0', "furniture_store", "furniture"],
			["callFunc", "addCategory", '$P0', "gas_station", "servicestations"],
			["callFunc", "addCategory", '$P0', "grocery_or_supermarket", "grocery"],
			["callFunc", "addCategory", '$P0', "gym", "gyms"],
			["callFunc", "addCategory", '$P0', "hardware_store", "hardware"],
			["callFunc", "addCategory", '$P0', "health", "health"],
			["callFunc", "addCategory", '$P0', "hindu_temple", "hindu_temples"],
			["callFunc", "addCategory", '$P0', "hospital", "hospitals"],
			["callFunc", "addCategory", '$P0', "jewelry_store", "jewelry"],
			["callFunc", "addCategory", '$P0', "laundry", "drycleaninglaundry"],
			["callFunc", "addCategory", '$P0', "lawyer", "lawyers"],
			["callFunc", "addCategory", '$P0', "library", "libraries"],
			["callFunc", "addCategory", '$P0', "locksmith", "locksmiths"],
			["callFunc", "addCategory", '$P0', "mosque", "mosques"],
			["callFunc", "addCategory", '$P0', "movie_theater", "movietheaters"],
			["callFunc", "addCategory", '$P0', "museum", "museums"],
			["callFunc", "addCategory", '$P0', "night_club", "danceclubs"],
			["callFunc", "addCategory", '$P0', "parks", "parks"],
			["callFunc", "addCategory", '$P0', "parking", "parking"],
			["callFunc", "addCategory", '$P0', "pet_store", "petstore"],
			["callFunc", "addCategory", '$P0', "pharmacy", "pharmacy"],
			["callFunc", "addCategory", '$P0', "physiotherapist", "physicaltherapy"],
			["callFunc", "addCategory", '$P0', "police", "policedepartments"],
			["callFunc", "addCategory", '$P0', "post_office", "postoffices"],
			["callFunc", "addCategory", '$P0', "real_estate_agency", "realestateagents"],
			["callFunc", "addCategory", '$P0', "restaurant", "restaurants"],
			["callFunc", "addCategory", '$P0', "rv_park", "rvparks"],
			["callFunc", "addCategory", '$P0', "school", "education"],
			["callFunc", "addCategory", '$P0', "shoe_store", "shoes"],
			["callFunc", "addCategory", '$P0', "shopping_mall", "shoppingcenters"],
			["callFunc", "addCategory", '$P0', "spa", "spas"],
			["callFunc", "addCategory", '$P0', "stadium", "stadiumsarenas"],
			["callFunc", "addCategory", '$P0', "synagogue", "synagogues"],
			["callFunc", "addCategory", '$P0', "taxi_stand", "taxis"],
			["callFunc", "addCategory", '$P0', "train_station", "trainstations"],
			["callFunc", "addCategory", '$P0', "travel_agency", "travelagents"],
			["callFunc", "addCategory", '$P0', "university", "collegeuniv"],
			["callFunc", "addCategory", '$P0', "veterinary_care", "vet"],
			["callFunc", "addCategory", '$P0', "zoo", "zoos"]
		],
		'AdvancedRequestSupporter:advancedRequest' => [
			["create", '$L0', "Object"],
			["create", '$L0.url', "String"],
			["if!=than", '$P2.appendBaseUrl', 0, 1],
			["set", '$L0.url', "https://api.yelp.com/v3"],
			["string.concat", '$L0.url', '$L0.url', '$P2.url'],
			["set", '$L0.requestHeaders', '$P2.headers'],
			["set", '$L0.method', '$P2.method'],
			["set", '$L0.requestBody', '$P2.body'],
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
			["set", '$L0.url', "https://api.yelp.com/v3/businesses/search"],
			["create", '$L1', "String", "?"],
			["string.concat", '$L1', '$L1', "latitude=", '$P2'],
			["string.concat", '$L1', '$L1', "&longitude=", '$P3'],
			["string.concat", '$L1', '$L1', "&radius=", '$P4'],
			["if!=than", '$P5', NULL, 2],
			["string.urlEncode", '$L2', '$P5'],
			["string.concat", '$L1', '$L1', "&term=", '$L2'],
			["if!=than", '$P6', NULL, 3],
			["callFunc", "getCategoriesString", '$P0', '$L2', '$P6'],
			["string.urlEncode", '$L2', '$L2'],
			["string.concat", '$L1', '$L1', "&categories=", '$L2'],
			["string.concat", '$L0.url', '$L0.url', '$L1'],
			["create", '$L0.requestHeaders', "Object"],
			["string.concat", '$L0.requestHeaders.Authorization', "Bearer ", '$P0.apiKey'],
			["http.requestCall", '$L2', '$L0'],
			["callFunc", "checkHttpResponse", '$P0', '$L2'],
			["json.parse", '$L3', '$L2.responseBody'],
			["create", '$P1', "Array"],
			["create", '$L4', "Number", 0],
			["size", '$L5', '$L3.businesses'],
			["if<than", '$L4', '$L5', 5],
			["get", '$L6', '$L3.businesses', '$L4'],
			["callFunc", "extractBusiness", '$P0', '$L7', '$L6'],
			["push", '$P1', '$L7'],
			["math.add", '$L4', '$L4', 1],
			["jumpRel", -6]
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
			["if>=than", '$P1.code', 400, 9],
			["if==than", '$P1.code', 401, 2],
			["create", '$L3', "Error", "Invalid credentials or access rights. Make sure that your application has read and write permission.", "Authentication"],
			["throwError", '$L3'],
			["if==than", '$P1.code', 503, 2],
			["create", '$L3', "Error", "Service unavailable. Try again later.", "ServiceUnavailable"],
			["throwError", '$L3'],
			["json.parse", '$L0', '$P1.responseBody'],
			["create", '$L1', "Error", '$L0.error.description', "Http"],
			["throwError", '$L1']
		],
		'getCategoriesString' => [
			["create", '$P1', "String"],
			["create", '$L0', "Number", 0],
			["size", '$L1', '$P2'],
			["if<than", '$L0', '$L1', 10],
			["get", '$L2', '$P2', '$L0'],
			["get", '$L2', '$P0.crToYelp', '$L2'],
			["if==than", '$L2', NULL, 2],
			["create", '$L3', "Error", "Unknown category.", "IllegalArgument"],
			["throwError", '$L3'],
			["if!=than", '$L0', 0, 1],
			["string.concat", '$P1', '$P1', ","],
			["string.concat", '$P1', '$P1', '$L2'],
			["math.add", '$L0', '$L0', 1],
			["jumpRel", -11]
		],
		'extractBusiness' => [
			["create", '$L0', "Location"],
			["set", '$L0.latitude', '$P2.coordinates.latitude'],
			["set", '$L0.longitude', '$P2.coordinates.longitude'],
			["create", '$L1', "Array"],
			["create", '$L2', "Number", 0],
			["size", '$L3', '$P2.categories'],
			["if<than", '$L2', '$L3', 7],
			["get", '$L4', '$P2.categories', '$L2'],
			["set", '$L5', '$L4.alias'],
			["get", '$L6', '$P0.yelpToCr', '$L5'],
			["if!=than", '$L6', NULL, 1],
			["push", '$L1', '$L6'],
			["math.add", '$L2', '$L2', 1],
			["jumpRel", -8],
			["create", '$P1', "POI", '$L1', '$P2.image_url', '$L0', '$P2.name', '$P2.phone']
		],
		'addCategory' => [
			["set", '$P0.crToYelp', '$P2', '$P1'],
			["set", '$P0.yelpToCr', '$P1', '$P2']
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
		InitSelfTest::initTest('Yelp');
		
		$this->interpreterStorage['apiKey'] = $apiKey;
		

		$ip = new Interpreter(new Sandbox(Yelp::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		if (array_key_exists("init",Yelp::$SERVICE_CODE)) {
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
		Statistics::addCall("Yelp", "getNearbyPOIs");
		$ip = new Interpreter(new Sandbox(Yelp::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		Statistics::addCall("Yelp", "advancedRequest");
		$ip = new Interpreter(new Sandbox(Yelp::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
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
		$ip = new Interpreter(new Sandbox(Yelp::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		return $ip->saveAsString();
	}

	/**
	 * @param string $savedState
	 */
	public function loadAsString($savedState) {
		$sandbox = new Sandbox(Yelp::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage);
		$ip = new Interpreter($sandbox);
		$ip->loadAsString($savedState);
		$this->persistentStorage = $sandbox->persistentStorage;
	}
}
