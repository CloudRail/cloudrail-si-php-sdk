<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 17/02/18
 * Time: 15:39
 */

namespace CloudRail\ServiceCode\Command\string;

use CloudRail\ServiceCode\Command;
use CloudRail\ServiceCode\Sandbox;
use CloudRail\ServiceCode\Helper;
use CloudRail\ServiceCode\VarAddress;

class Format implements Command {

    public function getIdentifier(): string {
        return "string.format";
    }

    public function execute(Sandbox &$environment, array $parameters) {
        Helper::assert(count($parameters) === 3 &&
            $parameters[0] instanceof VarAddress);
        $resultVar = $parameters[0];
        $format = Helper::resolve($environment, $parameters[1]);
        Helper::assert(Helper::isString($format));
        $element = Helper::resolve($environment, $parameters[2]);

        $res = sprintf($format,$element);
        $environment->setVariable($resultVar, $res);
    }
}