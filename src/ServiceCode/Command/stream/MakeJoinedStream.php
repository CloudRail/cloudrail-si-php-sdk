<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 21/02/18
 * Time: 10:40
 */

namespace CloudRail\ServiceCode\Command\stream;

use CloudRail\ServiceCode\Command;
use CloudRail\ServiceCode\Sandbox;
use CloudRail\ServiceCode\Helper;
use CloudRail\ServiceCode\VarAddress;

class MakeJoinedStream implements Command {

    public function getIdentifier(): string {
        return "stream.makeJoinedStream";
    }

    public function execute(Sandbox &$environment, array $parameters) {
        Helper::assert(count($parameters) >= 3 &&
            $parameters[0] instanceof VarAddress);
        $resultVar = $parameters[0];
        $joinedStream = fopen('php://memory','r+');

        for ($i = 1; $i<count($parameters); $i++) {
            $stream = Helper::resolve($environment, $parameters[$i]);
            Helper::assert(Helper::isStream($stream));
            stream_copy_to_stream($stream,$joinedStream);
        }
        rewind($joinedStream);
        $environment->setVariable($resultVar, $joinedStream);
    }
}