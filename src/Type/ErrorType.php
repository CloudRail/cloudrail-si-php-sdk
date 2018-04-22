<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 18/12/17
 * Time: 18:18
 */

namespace CloudRail\Type;

abstract  class ErrorType {

    const __default = self::NONE;

    const ILLEGAL_ARGUMENT = 0;
    const AUTHENTICATION = 1;
    const NOT_FOUND = 2;
    const HTTP = 3;
    const SERVICE_UNAVAILABLE = 4;
    const NONE = 5;


    public static function getValueOf(string $errorString):int{
        switch ($errorString){
            case "IllegalArgument":
                return ErrorType::ILLEGAL_ARGUMENT;
            case "Authentication":
                return ErrorType::AUTHENTICATION;
            case "NotFound":
                return ErrorType::NOT_FOUND;
            case "Http":
                return ErrorType::HTTP;
            case "ServiceUnavailable":
                return ErrorType::SERVICE_UNAVAILABLE;
            default:
                return ErrorType::NONE;
        }
    }

}