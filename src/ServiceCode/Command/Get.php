<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 26/01/18
 * Time: 17:22
 */

namespace CloudRail\ServiceCode\Command;
use CloudRail\ServiceCode\Command;
use CloudRail\ServiceCode\Sandbox;
use CloudRail\ServiceCode\VarAddress;
use CloudRail\ServiceCode\Helper;

class Get implements Command {

    public function getIdentifier():string {
        return 'get';
    }

    public function execute(Sandbox &$environment, array $parameters) {
        Helper::assert(count($parameters) >= 2 &&
            $parameters[0] instanceof VarAddress &&
            $parameters[1] instanceof VarAddress);

        $targetId = $parameters[0];

        //Building array to get the variable
        $containerIdParts = Sandbox::decodeVariableAddress($parameters[1]);
        for ($i = 2; $i < count($parameters); $i++) {
            array_push($containerIdParts,Helper::resolve($environment, $parameters[$i]));
        }

        //Getting variable from the parameters
        $variableGotten = $environment->getVariable($containerIdParts);

        //Setting the variable to the output VarAddress
        $environment->setVariable($targetId, $variableGotten);
    }
}