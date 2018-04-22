<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 13/02/18
 * Time: 05:26
 */

namespace CloudRail\Type;

use CloudRail\Error\InternalError;


class Charge extends SandboxObject {

    /**
     * @var double amount
     */
    private $amount;

    /**
     * @var integer
     */
    private $created;

    /**
     * @var string
     */
    private $currency;

    /**
     * @var string
     */
    private $id;

    /**
     * @var boolean
     */
    private $refunded;

    /**
     * @var CreditCard
     */
    private $source;

    /**
     * @var string
     */
    private $status;


    /**
     * Charge constructor.
     * @param string[] ...$values
     * @throws InternalError
     */
    public function __construct(string ...$values) {
        if (count($values) == 0 ){
            return $this;
        }
        //if there are parameters, they need to fill all the properties, they also need to be in order
        if (count($values) == count(get_object_vars($this))) {
            $this->amount = $values[0];
            $this->created = $values[1];
            $this->currency = $values[2];
            $this->id = $values[3];
            $this->refunded = $values[4];
            $this->source = $values[5];
            $this->status = $values[6];
        } else {
            throw new InternalError("Constructor needs all arguments to be initialized in class " . get_class($this));
        }
        return $this;
    }


    /**
     * @param float $amount
     * @param int $created
     * @param string $currency
     * @param string $id
     * @param bool $refunded
     * @param CreditCard $source
     * @param string $status
     * @return Charge
     */
    public static function new(float $amount,
                               integer $created,
                               string $currency,
                               string $id,
                               bool $refunded,
                               CreditCard $source,
                               string $status){
        $newObject = new Charge();
        $newObject->amount = $amount;
        $newObject->created = $created;
        $newObject->currency = $currency;
        $newObject->id = $id;
        $newObject->refunded = $refunded;
        $newObject->source = $source;
        $newObject->status = $status;
        return $newObject;
    }

    public function getId():string {
        return $this->id;
    }

    public function getAmount():number {
        return $this->amount;
    }

    public function getCurrency():string {
        return $this->currency;
    }

    public function getSource():CreditCard {
        return $this->source;
    }

    public function getCreated():number {
        return $this->created;
    }

    public function getStatus():string {
        return $this->status;
    }

    public function getRefunded():boolean {
        return $this->refunded;
        }
}