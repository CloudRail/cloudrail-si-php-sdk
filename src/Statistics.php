<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 18/04/18
 * Time: 03:42
 */
namespace CloudRail;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Client;
use CloudRail\ServiceCode\InitSelfTest;
use CloudRail\ServiceCode\Interpreter;
use CloudRail\ServiceCode\Sandbox;

class Statistics
{
    private static $initialized = false;
    private static $CR_VERSION = "";
    private static $PLATFORM = "Node.js";
    private static $SERVER_URL = "https://developers.cloudrail.com/api/entries";
    private static $DELAY = 300; // In seconds
    private static $lastSent;

    private static $data = [];
    private static $next = 1;
    private static $count = 0;
    private static $entryID;
//    private static $callSyncPromise = Promise.resolve();
//    private static $sendStatSyncPromise = Promise.resolve();



    public static function addCall(string $service, string $method) {//
        //Check if initialized then if not initializes
        if (!Statistics::$initialized){
            Statistics::initialize();
        }

        $re = '/^[a-f\d]{24}$/i';
        $match = preg_match($re, Settings::$licenseKey);

        if ($match == 0)
            throw new \Error("A valid CloudRail license key is required. You can get one for free at https://developers.cloudrail.com");

        //check if the number fires, check if the timestamp fires
        Statistics::getMethodCalls($service,$method);
        Statistics::$count++;
        if (Statistics::$count >= Statistics::$next ||
            Statistics::timerExpired()){
            Statistics::sendStatistics();
        }
    }


    private static function initialize(){
        Statistics::$initialized = true;
        Statistics::$CR_VERSION = Statistics::getCRVer();
        Statistics::$lastSent = time();
        Statistics::$data = [];//:ObjectMap<ObjectMap<ObjectMap<number>>> = {}; // dic of dic of dic

    }


    public static function addError(string $service, string $method) {
        $calls = Statistics::getMethodCalls($service,$method);
        $calls["error"]++;
    }


    private static function sendStatistics() {

        if (Statistics::$count === 0) return;

        $body = [
            "data" => Statistics::$data,
                "appKey" => Settings::$licenseKey,
                "platform" => Statistics::$PLATFORM
            ];

        if (Statistics::$entryID) {
            $body["id"] = Statistics::$entryID;
        } else {
            $client = [
                "mac" => "unknown",
                "os" => InitSelfTest::getOS()
            ];

            $app = InitSelfTest::getNameVersion();

            $appHash = Statistics::hashString(json_encode($app));
            $clientHash = Statistics::hashString(json_encode($client));

            array_shift($client);

            $body["app"] = $app;
            $body["client"] = $client;
            $body["libraryVersion"] = Statistics::$CR_VERSION;
            $body["appHash"] = $appHash;
            $body["clientHash"] = $clientHash;
        }


        $client = new Client(['base_uri' => Statistics::$SERVER_URL, 'http_errors' => false,]);

        $method = "POST";
        $requestHeaders = ["Content-Type" => "application/json"];



        $stream = fopen('php://memory','r+'); //creating the stream
        fwrite($stream, json_encode($body, true));//writing string on stream
        rewind($stream);//rewind the pointer.
        $request = new Request($method, Statistics::$SERVER_URL, $requestHeaders, $stream);

        //Sending request
        $responseRaw = $client->send($request, [/* array of options*/]);

        if ($responseRaw->getStatusCode() != 200){

        } else {
            $obj = json_decode($responseRaw->getBody()->getContents(),true);

            //Check if user is blocked
            if (!Statistics::$entryID) {
                Statistics::$entryID = $obj["id"];
            }
            if (array_key_exists("block",$obj)) {
                Settings::$block = true;
            }

            //Reset state for next stats sending
            Statistics::$data = [];
            Statistics::$count = 0;
            Statistics::$lastSent = time();
            Statistics::$next *= 2;
        }
    }

    private static function getMethodCalls(string $service, string $method) {

        if(!array_key_exists($service, Statistics::$data)){
            Statistics::$data[$service] = [];
        }

        $callsToService = &Statistics::$data[$service];

        if (!array_key_exists($method,$callsToService)) {
             $callsToService[$method] = [
                "count" => 0,
                "error" => 0
             ];
        }
        $callsToService[$method]["count"] +=1;
        return $callsToService[$method];
    }

    private static function hashString(string $str) {

        $SERVICE_CODE = [
            'hashString' => [
                ['hash.md5', '$L0', '$P1'],
                ['size', '$L1', '$L0'],
                ['set', '$L2', 0],
                ['set', '$P0', ''],
                ['get', '$L3', '$L0', '$L2'],
                ['string.format', '$L4', '%02X', '$L3'],
                ['string.concat', '$P0', '$P0', '$L4'],
                ['math.add', '$L2', '$L2', 1],
                ['if>=than', '$L2', '$L1', -5]
            ]
        ];

        $persistentStorage = [];
        $instanceDependencyStorage = [];
        $interpreter = new Interpreter(new Sandbox($SERVICE_CODE,$persistentStorage,$instanceDependencyStorage));
        $parameters = [[], $str];
        $interpreter->callFunctionSync("hashString",$parameters); // check if this call is correct
        return $interpreter->getParameter(0);
    }

    private static function getCRVer() {

        //Try to get from composer.json from the excpected location
        $jsonPath = __DIR__ . "/../composer.json";
        $decodedComposer = null;
        if (file_exists($jsonPath)){
            $jsonString = file_get_contents($jsonPath);
            $decodedComposer = json_decode($jsonString,true,50);
        }

        if ( $decodedComposer &&
            array_key_exists("version",$decodedComposer)){
            return strval($decodedComposer["version"]);
        } else if(array_key_exists("cloudrail-php-version",$GLOBALS)){
            return$GLOBALS["cloudrail-php-version"]; // get upper dir name?
        } else {
            return "unknown";
        }
    }

    /**
     * @return string information regarding the OS and arch used
     */
    public static function getOS() {
        $osType = php_uname('s');
        $osArch = php_uname('m');
        $release = php_uname('r');
        return $osType . " , " . $osArch . " , " . $release;
    }

    /**
     * @return bool
     */
    public static function timerExpired() {
        $timeTolerance = Statistics::$lastSent + Statistics::$DELAY;
        return $timeTolerance <= time();
    }
}