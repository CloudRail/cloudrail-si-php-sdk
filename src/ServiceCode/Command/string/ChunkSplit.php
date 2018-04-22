<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 17/02/18
 * Time: 15:21
 */

namespace CloudRail\ServiceCode\Command\string;

use CloudRail\ServiceCode\Command;
use CloudRail\ServiceCode\Sandbox;
use CloudRail\ServiceCode\Helper;
use CloudRail\ServiceCode\VarAddress;

class ChunkSplit implements Command {


    public function getIdentifier(): string {
        return "string.chunkSplit";
    }

    public function execute(Sandbox &$environment, array $parameters) {

        Helper:assert(count($parameters) === 3 &&
            $parameters[0] instanceof VarAddress);
        $resultVar = $parameters[0];
        $sourceString = Helper::resolve($environment, $parameters[1]);
        Helper::assert(Helper::isString($sourceString));
        $chunkSize = Helper::resolve($environment, $parameters[2]);
        Helper::assert(Helper::isNumber($chunkSize));

        $resultArray = str_split($sourceString,$chunkSize);

        $environment->setVariable($resultVar, $resultArray);
    }

}