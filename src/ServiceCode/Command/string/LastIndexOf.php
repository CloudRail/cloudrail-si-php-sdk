<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 16/02/18
 * Time: 08:07
 */

namespace CloudRail\ServiceCode\Command\string;

use CloudRail\ServiceCode\Command;
use CloudRail\ServiceCode\Sandbox;
use CloudRail\ServiceCode\Helper;
use CloudRail\ServiceCode\VarAddress;

class LastIndexOf implements  Command {

    public function getIdentifier():string {
        return 'string.lastIndexOf';
    }

    public function execute(Sandbox &$environment, array $parameters) {
        Helper::assert((count($parameters) === 3 ||
                count($parameters) === 4) &&
            $parameters[0] instanceof VarAddress);

        $resultVar = $parameters[0];
        $sourceString = Helper::resolve($environment, $parameters[1]);
        Helper::assert(Helper::isString($sourceString));
        $find = Helper::resolve($environment, $parameters[2]);
        $fromIndex = strlen($sourceString);

        if (count($parameters) === 4) {
            $fromIndex = Helper::resolve($environment, $parameters[3]);
            Helper::assert(Helper::isNumber($fromIndex));
            Helper::assert($fromIndex <= strlen($sourceString));
        }

//        $res = LastIndexOf::last_index($sourceString,$find,$fromIndex);
//        $res = sourceString.lastIndexOf(find, fromIndex);


        $sourceSubstring = substr($sourceString,0,$fromIndex);

        $res = strrpos($sourceSubstring,$find);


        if ($res === false){
            $res = -1;
        }

        $environment->setVariable($resultVar, $res);
    }

}