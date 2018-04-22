<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 26/02/18
 * Time: 03:36
 */

namespace CloudRail\ServiceCode\Command\crypt;
use CloudRail\ServiceCode\Command;
use CloudRail\ServiceCode\Sandbox;
use CloudRail\ServiceCode\Helper;
use CloudRail\ServiceCode\VarAddress;

class Hash implements Command {

    private $identifier;
    private $hashMethod;


    public function __construct(string $identifier, string $hashMethod) {
        $this->identifier = $identifier;
        $this->hashMethod = $hashMethod;
    }

    public function getIdentifier(): string {
        return $this->identifier;
    }


    public function execute(Sandbox &$environment, array $parameters) {
        Helper::assert(count($parameters) === 2 &&
            $parameters[0] instanceof VarAddress);

        $resultVar = $parameters[0];
        $source = Helper::resolve($environment, $parameters[1]);


        $hashedRawData = hash($this->hashMethod,$source,true);
        $uint8Array = unpack('C*', $hashedRawData);
        array_unshift($uint8Array,-1);
        array_shift($uint8Array);

        $environment->setVariable($resultVar, $uint8Array);

    }
}