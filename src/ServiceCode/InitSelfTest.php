<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 17/04/18
 * Time: 19:57
 */

namespace CloudRail\ServiceCode;

class InitSelfTest  {

    /**
     * @var array
     */
    private static $testedServices = [];

    /**
     * @param string $serviceName
     * @return boolean
     */
    public static function initTest(string $serviceName){

        $indexOf = array_search($serviceName,InitSelfTest::$testedServices);
        if ($indexOf !== false) return true;
        $testRes = InitSelfTest::execute($serviceName);
        $indexOf = array_search($serviceName,InitSelfTest::$testedServices);
        if ($testRes && $indexOf === false) array_push(InitSelfTest::$testedServices,$serviceName);
        return $testRes;
    }

    /**
     * @param string $serviceName
     * @return bool
     */
    public static function execute(string $serviceName)
    {
        $testState = true;

        $SERVICE_CODE = [
            'selfTest' => [
                ['create', '$L0', 'Object'],
                ['create', '$L1', 'Object'],
                ['create', '$L1.client', 'Object'],
                ['create', '$L1.app', 'Object'],
                ['set', '$L1.client.mac', '$P1'],
                ['set', '$L1.client.platform', '$P0.platform'],
                ['set', '$L1.client.os', '$P0.os'],
                ['set', '$L1.app.name', '$P2'],
                ['set', '$L1.app.version', '$P3'],
                ['json.stringify', '$L3', '$L1.client'],
                ['callFunc', 'hashString', '$L4', '$L3'],
                ['json.stringify', '$L5', '$L1.app'],
                ['callFunc', 'hashString', '$L6', '$L5'],
                ['delete', '$L1.client.mac'],
                ['create', '$L8', 'Object'],
                ['set', '$L8.method', 'GET'],
                ['string.concat', '$L8.url', 'https://stat-si.cloudrail.com/current_version?service=', '$P0.serviceName', '&client=', '$L4', '&app=', '$L6'],
                ['create', '$L8.requestHeaders', 'Object'],
                ['json.stringify', '$L8.requestHeaders.clientdata', '$L1.client'],
                ['json.stringify', '$L8.requestHeaders.appdata', '$L1.app'],
                ['http.requestCall', '$L9', '$L8']
            ],

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

        $interpreterStorage = [
        'serviceName' => $serviceName,
            'platform' => 'Node.js',
            'os'=> InitSelfTest::getOS()
        ];


        $nameVersion = InitSelfTest::getNameVersion();

        $persistentStorage = [];
        $instanceDependencyStorage = [];
        $interpreter = new Interpreter(new Sandbox($SERVICE_CODE,$persistentStorage,$instanceDependencyStorage));
        $parameters = [$interpreterStorage,
            "",
            $nameVersion["name"],
            $nameVersion["version"]];
        $interpreter->callFunctionSync("selfTest",$parameters);

        return $testState;
    }

    /**
     * @return array [name => version]
     */
    public static function getNameVersion() { //of the app that is using CR

        //Try to get from composer.json from the excpected location
        $jsonPath = __DIR__ . "/../../../../../composer.json";
        $decodedComposer = null;
        if (file_exists($jsonPath)){
            $jsonString = file_get_contents($jsonPath);
            $decodedComposer = json_decode($jsonString,true,50);
        }

        if ( $decodedComposer &&
            array_key_exists("name",$decodedComposer) &&
            array_key_exists("version",$decodedComposer)){
            return ["name" => strval($decodedComposer["name"]), "version"=> strval($decodedComposer["version"])];
        } else {
            return ["name" => "unknown", "version" => "unknown"]; // get upper dir name?
        }
    }

    public static function getOS() {
        $osType = php_uname('s');
        $osArch = php_uname('m');
        $release = php_uname('r');
        return $osType . " , " . $osArch . " , " . $release;
    }
}
