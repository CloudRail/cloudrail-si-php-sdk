<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 21/02/18
 * Time: 02:05
 */

namespace CloudRail\ServiceCode\Command\crlist;
use CloudRail\Error\InternalError;
use CloudRail\ServiceCode\Command;
use CloudRail\ServiceCode\Sandbox;
use CloudRail\ServiceCode\Helper;
use CloudRail\ServiceCode\VarAddress;

class Sort implements Command {

    public function getIdentifier(): string {
        return "array.sort";
    }

    public function execute(Sandbox &$environment, array $parameters) {
        Helper::assert(count($parameters) >= 2 &&
            $parameters[0] instanceof VarAddress);
        $resultVar = $parameters[0];
        $unsorted = Helper::resolve($environment, $parameters[1]);
        $sortedArray = sort($unsorted); // Return a lexicographically sorted shallow clone of the array
        $sortedArray = $unsorted;
        $environment->setVariable($resultVar, $sortedArray);
    }

}