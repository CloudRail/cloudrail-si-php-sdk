<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 10/12/17
 * Time: 17:28
 */

namespace CloudRail\ServiceCode\Command\string;

use CloudRail\ServiceCode\Command;
use CloudRail\ServiceCode\Sandbox;
use CloudRail\ServiceCode\Helper;
use CloudRail\ServiceCode\VarAddress;

class Substring implements Command {


    public function getIdentifier(): string {
        return "string.substring";
    }

    public function execute(Sandbox &$environment, array $parameters) {

        $firstCondition = (count($parameters) == 3 || count($parameters) == 4);
        $secondCondition = ($parameters[0] instanceof VarAddress);
        var_dump($parameters);

        var_dump($firstCondition);
        var_dump($secondCondition);
        Helper::assert($firstCondition && $secondCondition);

        $resultVar = $parameters[0];

        //Here we check if the parameter is a variable and get it from the sandbox
        $sourceString = Helper::resolve($environment, $parameters[1]);

        Helper::assert(Helper::isString($sourceString));
        $startIdx = Helper::resolve($environment, $parameters[2]);
        Helper::assert(Helper::isInteger($startIdx));
        $endIdx = strlen($sourceString);

        if (count($parameters)  == 4 ) {
            $endIdx = Helper::resolve($environment, $parameters[3]);
            Helper::assert(Helper::isInteger($endIdx));
            if ($endIdx < 0) $endIdx = 0;
            Helper::assert($endIdx <= strlen($sourceString));
        }
        
        $lenght = $endIdx - $startIdx;
        $res = substr($sourceString, $startIdx, $lenght);//$sourceString.substring($startIdx, $endIdx);
        $environment->setVariable($resultVar, $res);

        return $res;
    }
}
