<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 18/04/18
 * Time: 02:21
 */

namespace CloudRail\ServiceCode\Command;


use CloudRail\ServiceCode\Command;
use CloudRail\ServiceCode\Sandbox;
use CloudRail\ServiceCode\VarAddress;
use CloudRail\ServiceCode\Helper;

class Delete implements Command
{

    public function getIdentifier(): string
    {
        return "delete";
    }

    public function execute(Sandbox &$environment, array $parameters)
    {
        Helper::assert(count($parameters) >= 1 && $parameters[0] instanceof VarAddress);

        $targetId =$parameters[0];

        $targetIdParts = Sandbox::decodeVariableAddress($targetId);

        for ($i = 1; $i < count($parameters); $i++) {
            $resolved = Helper::resolve($environment, $parameters[$i]);
            array_push($targetIdParts,$resolved);
        }

        $environment->deleteVariable($targetIdParts);
    }
}