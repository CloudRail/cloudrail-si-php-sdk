<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 16/02/18
 * Time: 03:52
 */

namespace CloudRail\ServiceCode\Command\string;

use CloudRail\ServiceCode\Command;
use CloudRail\ServiceCode\Sandbox;
use CloudRail\ServiceCode\Helper;
use CloudRail\ServiceCode\VarAddress;

class Substr implements Command {


    public function getIdentifier(): string {
        return "string.substr";
    }

    public function execute(Sandbox &$environment, array $parameters) {
        Helper::assert((count($parameters) === 3 ||
                count($parameters) === 4) &&
            $parameters[0] instanceof VarAddress);

        $resultVar = $parameters[0];
        $sourceString = Helper::resolve($environment, $parameters[1]);
        Helper::assert(Helper::isString($sourceString));
        $startIdx = Helper::resolve($environment, $parameters[2]);
        Helper::assert(Helper::isNumber($startIdx));

        $length = strlen($sourceString) - $startIdx;

        if (count($parameters) === 4) {
            $length = Helper::resolve($environment, $parameters[3]);
            Helper::assert(Helper::isNumber($length));
            Helper::assert($length + $startIdx <= strlen($sourceString));
        }

//        $res = $sourceString.substring(startIdx, startIdx + length);

        $res = substr($sourceString, $startIdx,  $length);

        $environment->setVariable($resultVar, $res);

    }


}