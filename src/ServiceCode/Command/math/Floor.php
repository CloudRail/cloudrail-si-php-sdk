<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 20/02/18
 * Time: 17:44
 */

namespace CloudRail\ServiceCode\Command\math;

use CloudRail\ServiceCode\Command;
use CloudRail\ServiceCode\Sandbox;
use CloudRail\ServiceCode\Helper;
use CloudRail\ServiceCode\VarAddress;

class Floor implements  Command {

    public function getIdentifier(): string {
        return "math.floor";
    }

    public function execute(Sandbox &$environment, array $parameters)
    {
        Helper::assert(count($parameters) == 2 &&
        $parameters[0] instanceof VarAddress);

        $resultVar = $parameters[0];
        $input = Helper::resolve($environment, $parameters[1]);
        Helper::assert(Helper::isNumber($input));

        $res = floor($input);

        $environment->setVariable($resultVar, $res);
    }
}