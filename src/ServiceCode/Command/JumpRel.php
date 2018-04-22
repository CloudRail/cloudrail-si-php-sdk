<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 25/01/18
 * Time: 16:08
 */

namespace CloudRail\ServiceCode\Command;
use CloudRail\ServiceCode\Command;
use CloudRail\ServiceCode\Sandbox;
use CloudRail\ServiceCode\Helper;

class JumpRel implements Command {

    public function getIdentifier():string {
        return 'jumpRel';
    }

    public function execute(Sandbox &$environment, array $parameters) {
        Helper::assert(count($parameters) === 1);

        $relativeEndPos = Helper::resolve($environment, $parameters[0]);
        Helper::assert(Helper::isNumber($relativeEndPos));

        $environment->incrementCurrentServiceCodeLine($relativeEndPos);
    }
}