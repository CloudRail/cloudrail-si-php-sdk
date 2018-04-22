<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 17/02/18
 * Time: 16:13
 */

namespace CloudRail\ServiceCode\Command\string;

use CloudRail\ServiceCode\Command;
use CloudRail\ServiceCode\Sandbox;
use CloudRail\ServiceCode\Helper;
use CloudRail\ServiceCode\VarAddress;

class StringTransform implements Command {

    /**
     * @var callable
     */
    private $transform;

    /**
     * @var string
     */
    private $identifier;

    public function getIdentifier(): string {
        return $this->identifier;
    }


    public function __construct(string $identifier, callable $transform) {
        $this->identifier = $identifier;
        $this->transform = $transform;
    }


    public function execute(Sandbox &$environment, array $parameters) {

        if ($this->identifier === "string.base64encode"){

            Helper::assert(count($parameters) >= 2 &&
                $parameters[0] instanceof VarAddress);

            $resultVar = $parameters[0];
            $source = Helper::resolve($environment, $parameters[1]);

            Helper::assert(Helper::isString($source));

            $lineBreak = false;
            $webSafe = false;

            if (count($parameters) >= 3) {
                $lineBreak = !!Helper::resolve($environment, $parameters[2]);
            }

            if (count($parameters) >= 4) {
                $webSafe = !!Helper::resolve($environment, $parameters[3]);
            }

            $resultString = base64_encode($source);

            if($webSafe){
                $resultString = rtrim(strtr($resultString, '+/', '-_'), '=');
            }
            if($lineBreak){
                $resultString = chunk_split($resultString, 64);
            }

            $environment->setVariable($resultVar, $resultString);

        } else {
            Helper::assert(count($parameters) === 2 &&
                $parameters[0] instanceof VarAddress);

            $resultVar = $parameters[0];
            $sourceString = Helper::resolve($environment, $parameters[1]);
            Helper::assert(Helper::isString($sourceString));

            $transformFunction = $this->transform;
            $res = $transformFunction($sourceString);
            $environment->setVariable($resultVar, $res);
        }
    }

    private function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64url_decode($data) {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }
}
