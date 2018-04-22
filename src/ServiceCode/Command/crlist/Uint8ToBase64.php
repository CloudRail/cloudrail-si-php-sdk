<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 21/02/18
 * Time: 02:06
 */

namespace CloudRail\ServiceCode\Command\crlist;
use CloudRail\ServiceCode\Command;
use CloudRail\ServiceCode\Sandbox;
use CloudRail\ServiceCode\Helper;
use CloudRail\ServiceCode\VarAddress;

class Uint8ToBase64 implements Command {

    public function getIdentifier(): string
    {
        return "array.uint8ToBase64";
    }

    public function execute(Sandbox &$environment, array $parameters)
    {
        Helper::assert(count($parameters) >= 2 &&
            $parameters[0] instanceof VarAddress &&
            $parameters[1] instanceof VarAddress);

        $resultVar = $parameters[0];
        $sourceArray = Helper::resolve($environment, $parameters[1]);
        $urlSafe = false;
        if (count($parameters) > 2) $urlSafe = !!Helper::resolve($environment, $parameters[2]);
        Helper::assert(Helper::isArray($sourceArray));

        //Convert to string first
        $sourceString = pack("C*",...$sourceArray);
        $base64String = null;

        //base64
        if ($urlSafe) {
            $base64String = $this->base64url_encode($sourceString);
        } else {
            $base64String = base64_encode($sourceString);
        }

        $environment->setVariable($resultVar, $base64String);
    }

    function base64url_encode($data):string {
        return strtr(base64_encode($data), '+/', '-_');
//        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');

    }
//    function base64url_decode($data) {
//        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
//    }
}