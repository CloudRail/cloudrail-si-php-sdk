<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 25/01/18
 * Time: 14:08
 */

namespace CloudRail\ServiceCode\Command;
use CloudRail\ServiceCode\Command;
use CloudRail\ServiceCode\Sandbox;
use CloudRail\ServiceCode\Helper;
use \Error;

class ThrowError implements Command{


    public function getIdentifier(): string {
        return "throwError";
    }

    public function execute(Sandbox &$environment, array $parameters) {
        Helper::assert(count($parameters) < 2);

        $errorObj = new Error();

        if (count($parameters) > 0) {
            $errorObj = Helper::resolve($environment, $parameters[0]);
        }

        $environment->thrownError = $errorObj;
    }

}