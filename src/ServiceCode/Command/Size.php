<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 20/01/18
 * Time: 16:32
 */

namespace CloudRail\ServiceCode\Command;
use CloudRail\ServiceCode\Command;
use CloudRail\ServiceCode\Sandbox;
use CloudRail\ServiceCode\VarAddress;
use CloudRail\ServiceCode\Helper;
use \ReflectionObject;
use CloudRail\Error\InternalError;

class Size implements Command {

    public function getIdentifier(): string {
        return "size";
    }

    public function execute(Sandbox &$environment, array $parameters) {
        Helper::assert(count($parameters) === 2 &&
            $parameters[0] instanceof VarAddress &&
            $parameters[1] instanceof VarAddress);

        $targetVar = $parameters[0];
        $container = $environment->getVariable($parameters[1]);

        $size = -1;

        if (Helper::isArray($container)){
            $size = count($container);
        } else if (Helper::isString($container)){
            $size = strlen($container);
        } else if (gettype($container) === "object"){
            $objectReflection = new ReflectionObject($container);
            $properties = $objectReflection->getProperties();
            $size = count($properties);
        } else if (true/*Helper::isData($container)*/){
            throw new InternalError("Count with Data not implemented yet");
        }

        $environment->setVariable($targetVar, $size);
    }

}