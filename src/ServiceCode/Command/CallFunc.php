<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 10/01/18
 * Time: 22:04
 */

namespace CloudRail\ServiceCode\Command;
use CloudRail\ServiceCode\Command;
use CloudRail\ServiceCode\Sandbox;
use CloudRail\ServiceCode\VarAddress;
use CloudRail\ServiceCode\Helper;

class CallFunc implements  Command {


    public function getIdentifier():string {
        return 'callFunc';
    }

    public function execute(Sandbox &$environment, array $parameters) {
        Helper::assert(count($parameters) >= 1 &&
            (Helper::isString($parameters[0]) || $parameters[0] instanceof VarAddress));

        $functionName = Helper::resolve($environment, $parameters[0]);

        if (!Helper::isString($functionName)) {
            $functionName = strval($functionName);
        }

        /**
         * @var array Any type array
         */
        $functionParameters = [];

        for ($i = 1; $i < count($parameters); $i++) {
            $resolved = Helper::resolve($environment, $parameters[$i], false);
            array_push($functionParameters, $resolved);
        }

        $environment->callFunction($functionName, $functionParameters);
    }

}