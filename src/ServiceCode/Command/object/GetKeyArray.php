<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 20/02/18
 * Time: 17:25
 */

namespace CloudRail\ServiceCode\Command\object;

use CloudRail\ServiceCode\Command;
use CloudRail\ServiceCode\Sandbox;
use CloudRail\ServiceCode\Helper;
use CloudRail\ServiceCode\VarAddress;

class GetKeyArray implements Command {


    public function getIdentifier(): string
    {
        return "object.getKeyArray";
    }

    public function execute(Sandbox &$environment, array $parameters)
    {
        Helper::assert(count($parameters) === 2 &&
            $parameters[0] instanceof VarAddress &&
            $parameters[1] instanceof VarAddress);

        $resultVar = $parameters[0];
        $container = Helper::resolve($environment, $parameters[1]);

        $keys = array_keys($container);

        $environment->setVariable($resultVar, $keys);
    }
}