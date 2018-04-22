<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 27/01/18
 * Time: 18:00
 */

namespace CloudRail\ServiceCode\Command\math;

use CloudRail\ServiceCode\Command;
use CloudRail\ServiceCode\Sandbox;
use CloudRail\ServiceCode\Helper;
use CloudRail\ServiceCode\VarAddress;

class MathCombine implements Command
{
    /**
     * @var string
     */
    private $identifier;

    public function __construct(string $identifier) {
        $this->identifier = $identifier;
    }

    public function getIdentifier():string {
        return $this->identifier;
    }

    public function execute(Sandbox &$environment, array $parameters) {
        Helper::assert(count($parameters) >= 2 &&
            $parameters[0] instanceof VarAddress);

        $resultVar = $parameters[0];

        /**
         * @var float[] array for the operation
         */
        $elements = [];

        $res = null;

        for ($i = 1; $i < count($parameters); $i++) {
            $resolved = Helper::resolve($environment,$parameters[$i]);
            if (Helper::isString($resolved)) $resolved = floatval($resolved);
            Helper::assert(Helper::isNumber($resolved));
            array_push($elements,$resolved);
        }

        switch ($this->getIdentifier()) {
            case "math.add":
               $res =  array_sum($elements);
                break;
            case "math.multiply":
                $res = array_product($elements);
                break;
            case "math.subtract":
                //$res = $elements[0];
                $res = 0;
                foreach($elements as $number){
                    $res -= $number;
                }
                $res = $res + (2*$elements[0]); // replace the negative first index
                break;
            case "math.divide":
                //$res = $elements[0];
                $res = $elements[0];
                array_shift($elements);
                foreach($elements as $number){
                    $res /= $number;
                }
                break;
            case "math.min":
                $res = min($elements);
                break;
            case "math.max":
                $res = max($elements);
                break;
        }

        $environment->setVariable($resultVar, $res);
    }

}