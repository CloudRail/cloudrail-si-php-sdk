<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 20/02/18
 * Time: 17:48
 */

namespace CloudRail\ServiceCode\Command\debug;
use CloudRail\ServiceCode\Command;
use CloudRail\ServiceCode\Sandbox;
use CloudRail\ServiceCode\Helper;

class Out implements Command {

    public function getIdentifier(): string {
        return "debug.out";
    }

    public function execute(Sandbox &$environment, array $parameters) {

        Helper::assert(count($parameters) >= 1);

        $str = "";

        foreach ($parameters as $key => $value){
            $part = Helper::resolve($environment, $value);

            if (Helper::isArray($part)) $part = json_encode($parameters, JSON_PRETTY_PRINT);
            $str .= strval($part);
        }

        echo $str;
    }
}