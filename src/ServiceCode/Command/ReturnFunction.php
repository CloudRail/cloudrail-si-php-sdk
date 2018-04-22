<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 26/01/18
 * Time: 15:16
 */

namespace CloudRail\ServiceCode\Command;
use CloudRail\ServiceCode\Command;
use CloudRail\ServiceCode\Sandbox;
use CloudRail\ServiceCode\Helper;

class ReturnFunction implements Command {

    public function getIdentifier():string {
        return 'return';
    }

    public function execute(Sandbox &$environment, array $parameters) {

        Helper::assert(count($parameters) == 0);

        $environment->codeLineStack[count($environment->codeLineStack) - 1] = PHP_INT_MAX;
    }
}