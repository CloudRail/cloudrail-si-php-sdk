<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 27/01/18
 * Time: 15:19
 */

namespace CloudRail\ServiceCode\Command;
use CloudRail\ServiceCode\Command;
use CloudRail\ServiceCode\Sandbox;
use CloudRail\ServiceCode\VarAddress;
use CloudRail\ServiceCode\Helper;
use CloudRail\Error\InternalError;

class Push implements Command {

    public function getIdentifier():string {
        return 'push';
    }

    public function execute(Sandbox &$environment, array $parameters) {
        Helper::assert(count($parameters) >= 2 &&
            $parameters[0] instanceof VarAddress);

        $targetVar = $parameters[0];
        $value = Helper::resolve($environment, $parameters[1]);

        $targetVarParts = Sandbox::decodeVariableAddress($targetVar);

        for ($i = 2; $i < count($parameters); $i++) {
            $resolved = Helper::resolve($environment, $parameters[$i]);
            array_push($targetVarParts,$resolved);
        }

        $container = &$environment->getVariable($targetVarParts);

        if (Helper::isArray($container)) {
            array_push($container,$value);
        } else
//            if (Helper.isObject(container)) {
//            container[container.length] = value;
//        } else
            if (Helper::isString($container)) {
            $resultString = $container . strval($value);

            $environment->setVariable($targetVarParts, $resultString);
        } else throw new InternalError("Push only works for lists, objects and strings");
    }

}