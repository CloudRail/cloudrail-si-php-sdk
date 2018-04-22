<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 15/01/18
 * Time: 09:28
 */

namespace CloudRail\ServiceCode\Command\stream;

use CloudRail\ServiceCode\Command;
use CloudRail\ServiceCode\Sandbox;
use CloudRail\ServiceCode\Helper;
use CloudRail\ServiceCode\VarAddress;

class StringToStream implements Command {


    public function getIdentifier(): string {
        return "stream.stringToStream";
    }

    public function execute(Sandbox &$environment, array $parameters) {
        Helper::assert(count($parameters) == 2 && $parameters[0] instanceof VarAddress);

        $resultVar = $parameters[0];
        $source = Helper::resolve($environment, $parameters[1]);

        Helper::assert(Helper::isString($source));

        //streamify
        $stream = fopen('php://memory','r+'); //creating the stream
        fwrite($stream, $source);//writing string on stream
        rewind($stream);//rewind the pointer.
        $environment->setVariable($resultVar, $stream);
        return $stream;
    }
}