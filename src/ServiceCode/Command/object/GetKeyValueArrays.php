<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 20/02/18
 * Time: 17:34
 */

namespace CloudRail\ServiceCode\Command\object;

use CloudRail\ServiceCode\Command;
use CloudRail\ServiceCode\Sandbox;
use CloudRail\ServiceCode\Helper;
use CloudRail\ServiceCode\VarAddress;

class GetKeyValueArrays implements Command {
    public function getIdentifier(): string
    {
        return "object.getKeyValueArrays";
    }

    public function execute(Sandbox &$environment, array $parameters)
    {
        Helper::assert(count($parameters) === 3 &&
            $parameters[0] instanceof VarAddress &&
            $parameters[1] instanceof VarAddress &&
            $parameters[2] instanceof VarAddress);

        $resultKeysVar = $parameters[0];
        $resultValuesVar = $parameters[1];
        $container = Helper::resolve($environment, $parameters[2]);

        $keys = array_keys($container); //Object.keys($container);
        $values = array_values($container);

        $environment->setVariable($resultKeysVar, $keys);
        $environment->setVariable($resultValuesVar, $values);
    }
}