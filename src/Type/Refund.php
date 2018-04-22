<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 22/01/18
 * Time: 10:44
 */

namespace CloudRail\Type;

use CloudRail\Error\InternalError;

class Refund extends SandboxObject {

    /**
     * @var float
     */
    public $amount;

    /**
     * @var string
     */
    public $chargeID;

    /**
     * @var int
     */
    public $created;

    /**
     * @var string
     */
    public $id;

    /**
     * @var string
     */
    public $state;

    /**
     * @var string
     */
    public $currency;

    /**
     * Refund constructor.
     * @param array ...$addressValues
     * @throws InternalError
     */
    public function __construct( ...$addressValues) {
        if (count($addressValues) == 0 ){
            return $this;
        }
        //if there are parameters, they need to fill all the properties, they also need to be in order
        if (count($addressValues) == count(get_object_vars($this))) {
            $this->amount = $addressValues[0];
            $this->chargeID = $addressValues[1];
            $this->created = $addressValues[2];
            $this->id = $addressValues[3];
            $this->state = $addressValues[4];
            $this->currency = $addressValues[5];
        } else {
            throw new InternalError("Constructor needs all arguments to be initialized in class " . get_class($this));
        }
        return $this;
    }

    /**
     * @param float $amount
     * @param string $chargeID
     * @param int $created
     * @param string $id
     * @param string $state
     * @param string $currency
     * @return Refund
     */
    public static function new(float $amount, string $chargeID,
       int $created, string $id, string $state,
        string $currency) {
        $newObject = new Refund();
        $newObject->amount = $amount;
        $newObject->chargeID = $chargeID;
        $newObject->id = $id;
        $newObject->created = $created;
        $newObject->state = $state;
        $newObject->currency = $currency;
        return $newObject;
    }
}