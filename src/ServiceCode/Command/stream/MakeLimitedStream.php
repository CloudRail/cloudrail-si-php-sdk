<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 16/02/18
 * Time: 08:49
 */

namespace CloudRail\ServiceCode\Command\stream;

use CloudRail\ServiceCode\Command;
use CloudRail\ServiceCode\Sandbox;
use CloudRail\ServiceCode\Helper;
use CloudRail\ServiceCode\VarAddress;

class MakeLimitedStream implements Command{


    public function getIdentifier(): string {
        return "stream.makeLimitedStream";
    }

    public function execute(Sandbox &$environment, array $parameters) {
        Helper::assert(count($parameters) == 3 &&
            $parameters[0] instanceof VarAddress);

        $resultVar = $parameters[0];
        $source = &Helper::resolve($environment, $parameters[1]);
        $limit = Helper::resolve($environment, $parameters[2]);

        Helper::assert(Helper::isStream($source) &&
            Helper::isNumber($limit));

        $limitedContent = stream_get_contents($source,$limit);

        //streamify
        $resStream = fopen('php://memory','r+'); //creating the stream
        fwrite($resStream, $limitedContent);//writing string on stream
        rewind($resStream);//rewind the pointer.

//        $resStream = new LimitedReadableStream($source, $limit);

       $environment->setVariable($resultVar, $resStream);
    }


}