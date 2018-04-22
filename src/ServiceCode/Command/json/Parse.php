<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 16/01/18
 * Time: 15:22
 */

namespace CloudRail\ServiceCode\Command\json;

use CloudRail\ServiceCode\Command;
use CloudRail\ServiceCode\Sandbox;
use CloudRail\ServiceCode\Helper;
use CloudRail\ServiceCode\VarAddress;
use CloudRail\Error\InternalError;

class Parse implements  Command {


    public function getIdentifier():string {
        return 'json.parse';
    }

    public function execute(Sandbox &$environment, array $parameters) {
        //receives a resource (stream) and convert to JSON object or json string?
        Helper::assert(count($parameters) == 2 &&
            $parameters[0] instanceof VarAddress);

        $resultVar = $parameters[0];
        $input = Helper::resolve($environment, $parameters[1]);

        Helper::assert(Helper::isStream($input) ||
            Helper::isString($input));

        /**
         * @var string the resulting string to be converted to object
         */

        $obj = null;

        if (Helper::isStream($input)) {
            //convert from stream to string
            $str = stream_get_contents($input);
            //parse the string to Object
            $obj = json_decode($str,true);
        } else if(Helper::isString($input))  {// if it is String, parse it to object
            //parse the string
            $obj = json_decode($input,true);
        }

        if (!$obj) {//check if string wa properly parsed
            throw new InternalError("Could not parse JSON or stream to object");
        }

        $environment->setVariable($resultVar, $obj);
    }
}