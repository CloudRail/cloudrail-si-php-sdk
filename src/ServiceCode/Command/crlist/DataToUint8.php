<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 21/02/18
 * Time: 02:02
 */

namespace CloudRail\ServiceCode\Command\crlist;
use CloudRail\ServiceCode\Command;
use CloudRail\ServiceCode\Sandbox;
use CloudRail\ServiceCode\Helper;
use CloudRail\ServiceCode\VarAddress;

class DataToUint8 implements Command {

    public function getIdentifier(): string
    {
        return "array.dataToArray";
    }

    public function execute(Sandbox &$environment, array $parameters) {
        Helper::assert(count($parameters) >= 2 &&
            $parameters[0] instanceof VarAddress);

        $resultVar = $parameters[0];
        $dataString = Helper::resolve($environment, $parameters[1]);

        $uint8Array = unpack('C*', $dataString);

        //Workaround the string to byte conversion that starts with index 1 not 0
        array_unshift($uint8Array,-1);
        array_shift($uint8Array);

        $environment->setVariable($resultVar, $uint8Array);

    }
}