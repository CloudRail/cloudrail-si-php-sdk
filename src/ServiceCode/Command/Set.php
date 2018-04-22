<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 11/01/18
 * Time: 01:23
 */

namespace CloudRail\ServiceCode\Command;
use CloudRail\ServiceCode\Command;
use CloudRail\ServiceCode\Sandbox;
use CloudRail\ServiceCode\VarAddress;
use CloudRail\ServiceCode\Helper;

class Set implements Command {
    public function getIdentifier():string {
        return 'set';
    }

    public function execute(Sandbox &$environment, array $parameters) {

        Helper::assert(count($parameters) >= 2 && $parameters[0] instanceof VarAddress);

        if ($parameters[0]->addressString == "L0.requestHeaders"){
            print("stop here");
        }

        $targetVar = $parameters[0];
        $value = &Helper::resolve($environment, $parameters[1]);

        $targetVarParts = Sandbox::decodeVariableAddress($targetVar);

        for ($i = 2; $i < count($parameters); $i++) {
            $res = &Helper::resolve($environment, $parameters[$i]);
            array_push($targetVarParts, ($res));
        }

        $environment->setVariable($targetVarParts, $value);
    }
}