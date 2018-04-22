<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 21/02/18
 * Time: 20:23
 */

namespace CloudRail\ServiceCode\Command\crypt;
use CloudRail\ServiceCode\Command;
use CloudRail\ServiceCode\Sandbox;

class Sign implements Command {

    public $identifier;
    public $signMethod;

    public function __construct(string $identifier, string $signMethod) {
        $this->identifier = $identifier;
        $this->signMethod = $signMethod;
    }

    public function getIdentifier(): string {
        return $this->identifier;
    }

    public function execute(Sandbox &$environment, array $parameters) {
//        Helper.assert(parameters.length == 3 && parameters[0] instanceof VarAddress);
//
//        let resultVar = parameters[0];
//        let key = Helper.resolve(environment, parameters[2]);
//        let message = Helper.resolve(environment, parameters[1]);
//
//        Helper.assert(Helper.isData(key) && Helper.isData(message));
//
//        let keyString = '-----BEGIN PRIVATE KEY-----\n' + Base64Encode.encode(key, true, false) + '\n-----END PRIVATE KEY-----\n';
//
//        let sign = crypto.createSign(this.signMethod);
//        sign.update(message);
//        let buf = sign.sign(keyString);
//
//        environment.setVariable(resultVar, buf);
    }
}