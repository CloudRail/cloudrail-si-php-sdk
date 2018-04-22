<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 15/01/18
 * Time: 09:17
 */

namespace CloudRail\ServiceCode\Command\string;

use CloudRail\ServiceCode\Command;
use CloudRail\ServiceCode\Sandbox;
use CloudRail\ServiceCode\Helper;
use CloudRail\ServiceCode\VarAddress;

class Concat implements Command {


    public function getIdentifier(): string {
        return "string.concat";
    }

    public function execute(Sandbox &$environment, array $parameters) {
        Helper::assert(count($parameters) >= 2 && $parameters[0] instanceof VarAddress);

        $resultVar = $parameters[0];
        $str = "";

        for ($i = 1; $i < count($parameters); $i++) {
            $strPart = Helper::resolve($environment, $parameters[$i]);
            $str .= strval($strPart);
        }

        $environment->setVariable($resultVar, $str);
        return $str;
    }

}