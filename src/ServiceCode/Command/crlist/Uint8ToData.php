<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 21/02/18
 * Time: 02:07
 */

namespace CloudRail\ServiceCode\Command\crlist;
use CloudRail\ServiceCode\Command;
use CloudRail\ServiceCode\Sandbox;
use CloudRail\ServiceCode\Helper;
use CloudRail\ServiceCode\VarAddress;

class Uint8ToData implements Command {

    public function getIdentifier(): string {
        return "array.arrayToData";
    }

    public function execute(Sandbox &$environment, array $parameters) {
        Helper::assert(count($parameters) >= 2 &&
            $parameters[0] instanceof VarAddress);

        $resultVar = $parameters[0];

        $source = Helper::resolve($environment, $parameters[1]);

        //Explode array into parameters
        $resultData = pack("C*", ...$source);

        $environment->setVariable($resultVar, $resultData);

    }
}