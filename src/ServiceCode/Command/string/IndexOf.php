<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 17/02/18
 * Time: 14:54
 */

namespace CloudRail\ServiceCode\Command\string;

use CloudRail\ServiceCode\Command;
use CloudRail\ServiceCode\Sandbox;
use CloudRail\ServiceCode\Helper;
use CloudRail\ServiceCode\VarAddress;

class IndexOf implements Command
{

    public function getIdentifier(): string {
        return "string.indexOf";
    }

    public function execute(Sandbox &$environment, array $parameters) {
        Helper::assert((count($parameters) === 3 ||
                count($parameters) === 4) &&
            $parameters[0] instanceof VarAddress);

        $resultVar = $parameters[0];
        $sourceString = Helper::resolve($environment, $parameters[1]);
        Helper::assert(Helper::isString($sourceString));
        $find = Helper::resolve($environment, $parameters[2]);
        $fromIndex = 0;

        if (count($parameters) === 4) {
            $fromIndex = Helper::resolve($environment, $parameters[3]);
            Helper::assert(Helper::isNumber($fromIndex));
            Helper::assert($fromIndex <= strlen($sourceString));
        }

        $res = strpos($sourceString,$find,$fromIndex);

        if ($res === false) {
            $res = -1;
        }
//        $res = $sourceString.indexOf(find, fromIndex);

        $environment->setVariable($resultVar, $res);
    }

}