<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 08/01/18
 * Time: 15:41
 */

namespace CloudRail\ServiceCode\Command;

use CloudRail\ServiceCode\Command;
use CloudRail\ServiceCode\Sandbox;
use CloudRail\ServiceCode\VarAddress;
use CloudRail\ServiceCode\Helper;
use CloudRail\Error\InternalError;
use CloudRail\Type\Types;

use CloudRail\Type;
//include_once __DIR__ . "/../../Type/Types.php";

class Create implements  Command {

    public function getIdentifier():string {
        return 'create';
    }

    public function execute(Sandbox &$environment, array $parameters) {

        Helper::assert(count($parameters) >= 2 &&
            $parameters[0] instanceof VarAddress &&
            (Helper::isString($parameters[1]) || $parameters[1] instanceof VarAddress));

        $targetId = $parameters[0];
        $type = Helper::resolve($environment, $parameters[1]);

        Helper::assert(Helper::isString($type));

        $targetIdParts = Sandbox::decodeVariableAddress($targetId);

        $newObject = null;

        /**
         * @var array
         */
        $constructorArgs = [];


        for ($i = 2; $i < count($parameters); $i++) {
            array_push($constructorArgs, Helper::resolve($environment,$parameters[$i]));
        }

        if ($type === "String") {
            /**
             * @var string
             */
            $newObject = "";

            foreach ( $constructorArgs as $key => $value) {
                $newObject .= strval($value);
            }

        } else if ($type === "Number") {//comes from service code not the PHP, should be float, double or int
            if (count($constructorArgs) > 1) throw new InternalError("Create Number has too many arguments");

            if (count($constructorArgs) == 1) {
                if (Helper::isNumber($constructorArgs[0])) {
                    $newObject = $constructorArgs[0];
                } else throw new InternalError("Create Number has an invalid argument type");
            } else {
                $newObject = 0;
            }
        } else if ($type === "Object") {
            if (count($constructorArgs) != 0) throw new InternalError("Create Object does not take constructor arguments");
            $newObject = [];
        } else if ($type === "Array") {
            $newObject = [];
            foreach ($constructorArgs as $key => $value){
                array_push($newObject,$value);
            }
        } else {

            $constructorMethodName = Types::$typeMap[$type];
            $constructorMethodName =  '\CloudRail\Type\\' . $constructorMethodName;
            Helper::assert($constructorMethodName != null);
            $newObject = new $constructorMethodName(...$constructorArgs);
        }
        $environment->setVariable($targetIdParts, $newObject);
    }
}