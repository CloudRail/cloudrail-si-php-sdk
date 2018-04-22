<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 20/11/17
 * Time: 03:02
 */

namespace CloudRail\ServiceCode;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Stream;

use GuzzleHttp\Client;
use CloudRail\Error\InternalError;

use \Error as Error;
use \ReflectionObject as ReflectionObject;
use \ReflectionProperty as ReflectionProperty;

class Helper{

    /**
     * @const array
     */
    const HTTP_METHODS = ["GET", "POST", "PUT", "DELETE", "HEAD", "OPTIONS", "TRACE"];

    /**
     * @param Sandbox $environment
     * @param $value
     * @param bool|null $checkExistence
     * @return mixed|null|string
     */
    public static function &resolve(Sandbox &$environment, $value, bool $checkExistence = null) {
        if ($checkExistence === null){
            $checkExistence = true;
        }

        if ($value instanceof VarAddress){
            $returnVar = &$environment->getVariable($value, -1, !$checkExistence);
            return $returnVar;
        }
        else {
            return $value;
        }
    }


    //is Numeric
    public static function isNumeric($obj){
        return is_numeric($obj);
    }

    public static function isNumber($obj){
        return (is_int($obj) || is_double($obj) || is_float($obj));
    }

    public static function isInteger($obj){
        if (gettype($obj) == "integer" || gettype($obj) == "int"){
            return true;
        }

        return false;
    }

    public static function isFloat($obj){
        if (gettype($obj) != "float"){
            return false;
        }
        return true;
    }


    public static function isStream($obj){
        if (is_resource($obj) || $obj instanceof Stream){
            return true;
        }
        return false;
    }

    public static function isArray($obj){
        if (gettype($obj) != "array"){
            return false;
        }
        return true;
    }


    public static function isString($obj){
        if (gettype($obj) != "string"){
            return false;
        }
        return true;
    }




    public static function isBoolean($obj){
        if (gettype($obj) == "boolean" || gettype($obj) == "bool"){
            return true;
        }
        return false;
    }


    public static function getTypeOrClass($aObj):string{
        $type = gettype($aObj);

        if ($type != "object") {
            return $type;
        }
        return get_class($aObj);
    }

    /**
     * Use this comparation if there is no clear way to compare the params directly or with native methods
     *
     * @param $aObj
     * @param $bObj
     * @return int
     * @throws InternalError
     *
     */
    public static function compare($aObj,$bObj):int{
        $typeA = gettype($aObj);
        $typeB = gettype($bObj);



        if ($typeA != "object" && $typeB != "object") {
            switch ($typeA){
                case "int":
                case "integer":
                case "float":
                case "double":
                    if ($aObj < $bObj) return -1;
                    else if ($bObj < $aObj) return 1;
                    if ($aObj == $bObj) return 0;
                    break;
                case "string":
                    return strcmp($aObj,$bObj);
                    break;
                case "boolean":
                case "array":
                    if($aObj == $bObj) return 0;
                    else return -1;
                    break;
            }
        }else { // comparing objects
            

            if ($aObj === $bObj) return 0;
            else if($aObj < $bObj) return -1;
            else if($aObj>$bObj) return 1;
        }

         throw new InternalError("Compare compares incomparable values");
    }

    public static function assert(bool $expression){
        if ($expression == false) throw new InternalError("Assertion failed");
    }


    public static function checkSandboxError(Interpreter $ip, string $serviceName, string $methodName){
        /**
         * @var Error
         */
        $error = $ip->sandbox->thrownError;

        if ($error != null) {
            throw new Error();
        }
    }

    public static function valuesAreIdentical($v1, $v2): bool {
        $type1 = gettype($v1);
        $type2 = gettype($v2);

        if($type1 !== $type2){
            return false;
        }

        switch(true){
            case ($type1==='boolean' || $type1==='integer' || $type1==='double' || $type1==='string'):
                //Do strict comparison here.
                if($v1 !== $v2){
                    return false;
                }
                break;

            case ($type1==='array'):
                $bool = Helper::arraysAreIdentical($v1, $v2);
                if($bool===false){
                    return false;
                }
                break;

            case 'object':
                $bool = Helper::objectsAreIdentical($v1,$v2);
                if($bool===false){
                    return false;
                }
                break;

            case 'NULL':
                //Since both types were of type NULL, consider their "values" equal.
                break;

            case 'resource':
                //How to compare if at all?
                break;

            case 'unknown type':
                //How to compare if at all?
                break;
        } //end switch

        //All tests passed.
        return true;
    }

    public static function objectsAreIdentical($o1, $o2): bool {
        //See if loose comparison passes.
        if($o1 != $o2){
            return false;
        }

        //Now do strict(er) comparison.
        $objReflection1 = new ReflectionObject($o1);
        $objReflection2 = new ReflectionObject($o2);

        $arrProperties1 = $objReflection1->getProperties(ReflectionProperty::IS_PUBLIC);
        $arrProperties2 = $objReflection2->getProperties(ReflectionProperty::IS_PUBLIC);

        $bool = Helper::arraysAreIdentical($arrProperties1, $arrProperties2);
        if($bool===false){
            return false;
        }

        foreach($arrProperties1 as $key=>$propName){
            $bool = Helper::valuesAreIdentical($o1->$propName, $o2->$propName);
            if($bool===false){
                return false;
            }
        }

        //All tests passed.
        return true;
    }

    public static function arraysAreIdentical(array $arr1, array $arr2): bool {
        $count = count($arr1);

        //Require that they have the same size.
        if(count($arr2) !== $count){
            return false;
        }

        //Require that they have the same keys.
        $arrKeysInCommon = array_intersect_key($arr1, $arr2);
        if(count($arrKeysInCommon)!== $count){
            return false;
        }

        //Require that their keys be in the same order.
        $arrKeys1 = array_keys($arr1);
        $arrKeys2 = array_keys($arr2);
        foreach($arrKeys1 as $key=>$val){
            if($arrKeys1[$key] !== $arrKeys2[$key]){
                return false;
            }
        }

        //They do have same keys and in same order.
        foreach($arr1 as $key=>$val){
            $bool = Helper::valuesAreIdentical($arr1[$key], $arr2[$key]);
            if($bool===false){
                return false;
            }
        }

        //All tests passed.
        return true;
    }


    /**
     * @param string $urlString
     * @param array|null $headers
     * @param resource|null $body
     * @param string $method
     * @param int $timeout
     * @return array
     */
    public static function makeRequest(string $urlString, array $headers = null,resource $body = null,string $method, int $timeout=0):array {


        $urlParsed = $urlString;//.parse(urlString, true);

        $client = new Client([
            // Base URI is used with relative requests
            'base_uri' => $urlString,
            'http_errors' => false,
            // You can set any number of default request options.
            'timeout'  => $timeout,
        ]);

        if ($headers == null) $headers = [];

        $request = new Request($method, $urlParsed, $headers, $body);
        $responseRaw = $client->send($request, [/* array of options*/]);

        //Check for request Error
        return $response = [
            "code" => $responseRaw->getStatusCode(), // 200
            "reason" => $responseRaw->getReasonPhrase(), // OK
            "responseHeaders" => $responseRaw->getHeaders(), // array
            "responseBody" => $responseRaw->getBody()->detach() // STREAM from the lib
        ];
    }


    public static function encodeURIComponent($str) {
        $revert = array('%21'=>'!', '%2A'=>'*', '%27'=>"'", '%28'=>'(', '%29'=>')');
        return strtr(rawurlencode($str), $revert);
    }

}