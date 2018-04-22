<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 21/02/18
 * Time: 20:28
 */

namespace CloudRail\ServiceCode\Command\crypt;
use CloudRail\ServiceCode\Command;
use CloudRail\ServiceCode\Sandbox;
use CloudRail\ServiceCode\Helper;
use CloudRail\ServiceCode\VarAddress;

class Hmac implements Command {

    public $identifier;
    public $hashMethod;

    public function __construct(string $identifier, string $hashMethod) {
        $this->identifier = $identifier;
        $this->hashMethod = $hashMethod;
    }

    public function getIdentifier(): string {
        return $this->identifier;
    }

    public function execute(Sandbox &$environment, array $parameters) {
        Helper::assert(count($parameters) == 3 &&
            $parameters[0] instanceof VarAddress);

        $resultVar = $parameters[0];
        $key = Helper::resolve($environment, $parameters[1]);
        $message = Helper::resolve($environment, $parameters[2]);

        if (Helper::isArray($key)) {
            $key = pack("C*", ...$key);
        }

        $hashedRawData = hash_hmac($this->hashMethod,$message,$key,true);
        $uint8Array = unpack('C*', $hashedRawData);
        array_unshift($uint8Array,-1);
        array_shift($uint8Array);

        $environment->setVariable($resultVar, $uint8Array);
    }

}
