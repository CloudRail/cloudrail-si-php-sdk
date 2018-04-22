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
use GuzzleHttp\Psr7\Stream;
use CloudRail\Error\InternalError;

class StreamToString implements  Command {

    public function getIdentifier(): string {
        return "stream.streamToString";
    }

    public function execute(Sandbox &$environment, array $parameters) {

        Helper::assert(count($parameters) == 2 && $parameters[0] instanceof VarAddress);

        $resultVar = $parameters[0];
        $source = Helper::resolve($environment, $parameters[1]);

        Helper::assert(is_resource($source) || $source instanceof Stream);

        if (is_resource($source)){
            $string = stream_get_contents($source);
        } else if($source instanceof Stream) {
            $string = $source->getContents();
        } else {
            throw new InternalError("Unknow stream type on StreamToString");
        }
        $environment->setVariable($resultVar, $string);
        return $string;
    }
}