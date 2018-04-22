<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 08/02/18
 * Time: 15:17
 */

namespace CloudRail\ServiceCode\Command;
use CloudRail\ServiceCode\Command;
use CloudRail\ServiceCode\Sandbox;
use CloudRail\ServiceCode\VarAddress;
use CloudRail\ServiceCode\Helper;
use CloudRail\Error\UserError;
use CloudRail\Settings;

class AwaitCodeRedirect implements Command {

    private const CHECK_URL = "https://developers.cloudrail.com/api/misc/check-license/";
    private const TIMEOUT = 500;

    public function getIdentifier():string {
        return 'awaitCodeRedirect';
    }

    public function execute(Sandbox &$environment, array $parameters) {

        $enoughParameters = count($parameters) >= 2;
        $returnIsVariable = $parameters[0] instanceof VarAddress;
        $inputIsStringOrVariable = Helper::isString($parameters[1]) ||
            $parameters[1] instanceof VarAddress;

        Helper::assert($enoughParameters && $returnIsVariable && $inputIsStringOrVariable);

        $resVar = $parameters[0];
        $urlStr = Helper::resolve($environment, $parameters[1]);
        $keys = [];

        if(count($parameters) >= 3 && $parameters[2]) {
            $keys = Helper::resolve($environment, $parameters[2]);
        } else {
            array_push($keys, "code");
        }

        /**
         * @var callable The redirect receiver to be called - RedirectReceiver
         */
        $redirectReceiver = $environment->instanceDependencyStorage["redirectReceiver"];

        if (!isset($redirectReceiver) || !is_callable($redirectReceiver)) {
            throw new UserError("This service needs the RedirectReceiver to be implemented as a function. Have a look at our examples and documentation if you are unsure how to do that.");
        }

        //Check for cloudrail key - and getting advertising first page.
        $cloudRailResponse = Helper::makeRequest(self::CHECK_URL . Settings::$licenseKey,null,null,"GET", self::TIMEOUT);


        if ($cloudRailResponse["code"] === 200) {
            $jsonBodyStr = stream_get_contents($cloudRailResponse["responseBody"]);
            $jsonBody = json_decode($jsonBodyStr,true);
            $urlStr = $jsonBody["url"] . Helper::encodeURIComponent($urlStr);
        }

        //Running the redirect Receiver
        $redirectReturn = $redirectReceiver($urlStr, "ENVIRONMENT_STATE"/*$environment->saveStateToString()*/);//, $callback);


        //If it still on first part of auth, do nothing
        if ($redirectReturn == null || gettype($redirectReturn) !== "array"){
            //Do something in the first part here
            throw new \ErrorException("The first step of the auth process has finished");
        } else {//If it is on second step, extract values and proceed
            //Extract the values and proceed
            $queryString = parse_url($redirectReturn["url"])["query"]; //url.parse(redirectUrl, true).query;
            $queryMap = null;
            parse_str($queryString,$queryMap);
            $resMap = [];

            foreach ($keys as $key) {
                if ($queryMap[$key] != null) {
                    $resMap[$key] = $queryMap[$key];
                } else {
                    throw new UserError("The URL the RedirectReceiver returns does not contain all necessary keys in the query, it's missing at least " . $key);
                }
            }

            //Check for Oauth1 or Oauth2
            $res = null;
            if (array_key_exists("code", $resMap)) {
                $res =  $resMap["code"];
            } else {
                $res = $resMap;
            }

            $environment->setVariable($resVar, $res);
        }
    }
}