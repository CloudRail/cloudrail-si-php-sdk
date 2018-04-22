<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 25/01/18
 * Time: 16:16
 */

namespace CloudRail\ServiceCode\Command\string;

use CloudRail\ServiceCode\Command;
use CloudRail\ServiceCode\Sandbox;
use CloudRail\ServiceCode\Helper;
use CloudRail\ServiceCode\VarAddress;

class URLEncode implements Command{

    public function getIdentifier():string {
        return 'string.urlEncode';
    }

    public function execute(Sandbox &$environment, array $parameters) {
        Helper::assert(count($parameters) === 2 &&
            $parameters[0] instanceof VarAddress);
        $resultVar = $parameters[0];
        $sourceString = Helper::resolve($environment, $parameters[1]);
        Helper::assert(Helper::isString($sourceString));
        $res =  rawurlencode($sourceString);
        $environment->setVariable($resultVar, $res);
    }

}