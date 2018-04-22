<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 21/02/18
 * Time: 10:41
 */

namespace CloudRail\ServiceCode\Command\stream;

use CloudRail\ServiceCode\Command;
use CloudRail\ServiceCode\Sandbox;
use CloudRail\ServiceCode\Helper;
use CloudRail\ServiceCode\VarAddress;

class StreamToData implements Command {

    public function getIdentifier(): string {
        return "stream.streamToData";
    }

    public function execute(Sandbox &$environment, array $parameters) {
        Helper::assert(count($parameters) === 2 &&
            $parameters[0] instanceof VarAddress &&
            $parameters[1] instanceof VarAddress);

        $resultVar = $parameters[0];
        $stream = $environment->getVariable($parameters[1]);

        Helper::isStream($stream);

        $dataString = stream_get_contents($stream);

        $environment->setVariable($resultVar,$dataString);
    }
}