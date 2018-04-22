<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 17/02/18
 * Time: 16:04
 */

namespace CloudRail\ServiceCode\Command\string;

use CloudRail\ServiceCode\Command;
use CloudRail\ServiceCode\Sandbox;
use CloudRail\ServiceCode\Helper;
use CloudRail\ServiceCode\VarAddress;

class Split implements Command {

    public function getIdentifier(): string {
        return "string.split";
    }

    public function execute(Sandbox &$environment, array $parameters) {
        Helper::assert((count($parameters) === 3 ||
                count($parameters) === 4) &&
            $parameters[0] instanceof VarAddress);

        $resultVar = $parameters[0];
        $sourceString = Helper::resolve($environment, $parameters[1]);
        Helper::assert(Helper::isString($sourceString));
        $separator = Helper::resolve($environment, $parameters[2]);
        Helper::assert(Helper::isString($separator));

        $limit = -1;

        if (count($parameters) === 4) {
            $limit = Helper::resolve($environment, $parameters[3]);
            Helper::assert(Helper::isNumber($limit));
        }

        //Escape slash
        $separator = preg_quote($separator,"/");

        $separator = "/" . $separator . "/";

        $res = preg_split (  $separator ,  $sourceString ,  $limit );
        $environment->setVariable($resultVar, $res);
    }

}