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

class DataToStream implements Command {

    public function getIdentifier(): string {

        return "stream.dataToStream";
    }

    public function execute(Sandbox &$environment, array $parameters)
    {
        Helper::assert(count($parameters) === 2 &&
        $parameters[0] instanceof VarAddress &&
        $parameters[1] instanceof VarAddress);

        $resultVar = $parameters[0];


        $dataString = $environment->getVariable($parameters[1]);

        //streamify
        $stream = fopen('php://memory','r+'); //creating the stream
        fwrite($stream, $dataString);//writing string on stream
        rewind($stream);//rewind the pointer.

        $environment->setVariable($resultVar, $stream);
    }
}