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

class Stringify implements Command {

    public function getIdentifier():string {
        return 'json.stringify';
    }

    public function execute(Sandbox &$environment, array $parameters) {

        Helper::assert(count($parameters) == 2 &&
            $parameters[0] instanceof VarAddress);

        $resultVar = $parameters[0];
        $input = Helper::resolve($environment, $parameters[1]); // should be an array,

//        Stringify takes a dictionary and convert to string
        $str =  json_encode($input, JSON_UNESCAPED_SLASHES); //does not accept a resource (streams in our case)

        $environment->setVariable($resultVar, $str);
    }
}
