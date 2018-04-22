<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 22/01/18
 * Time: 10:51
 */

namespace CloudRail\Type;

use CloudRail\Error\InternalError;
use CloudRail\Error\IllegalArgumentError;

class CreditCard extends SandboxObject
{
    public $cvc;
    public $expire_month;
    public $expire_year;
    public $number;
    public $type;
    public $firstName;
    public $lastName;
    public $address;

    /**
     * CreditCard constructor.
     * @param array ...$addressValues used in the same order as the static constructor
     * @throws InternalError
     */
    public function __construct( ...$addressValues) {
        if (count($addressValues) == 0 ){
            return $this;
        } else
        //if there are parameters, they need to fill all the properties, they also need to be in order
        if (count($addressValues) == count(get_object_vars($this))) {
            $this->cvc = $addressValues[0];
            $this->expire_month = $addressValues[1];
            $this->expire_year = $addressValues[2];
            $this->number = $addressValues[3];
            $this->type = $addressValues[4];
            $this->firstName = $addressValues[5];
            $this->lastName = $addressValues[6];
            $this->address = $addressValues[7];
        } else {
            throw new InternalError("Constructor needs all arguments to be initialized in class " . get_class($this));
        }
        return $this;
    }

    /**
     * @param string $cvc
     * @param int $expire_month
     * @param int $expire_year
     * @param string $number
     * @param string $type
     * @param string $firstName
     * @param string $lastName
     * @param Address $address
     * @return CreditCard
     */
    public static function new(string $cvc, int $expire_month, int $expire_year,
                               string $number, string $type, string $firstName,
                               string $lastName, Address $address) {
        $newObject = new CreditCard();

        $newObject->cvc = $cvc;
        $newObject->expire_month = $expire_month;
        $newObject->expire_year = $expire_year;
        $newObject->number = $number;
        $newObject->type = $type;
        $newObject->firstName = $firstName;
        $newObject->lastName = $lastName;
        $newObject->address = $address;
        return $newObject;
    }

    //GETTERS AND SETTERS

    public function getExpire_month(){
        return $this->expire_month;
    }

    public function setExpire_month(integer $value){
        if ($value == null)
            throw new IllegalArgumentError("Expiration month shouldn't be null");
        if ($value <= 0 || $value > 12) {
            throw new IllegalArgumentError("Expiration month needs to be between 1 and 12.");
        }
        $this->expire_month = $value;
    }

    public function getExpire_year():number {
        return $this->expire_year;
    }

    public function setExpire_year(int $value) {
        if ($value == null)
            throw new IllegalArgumentError("Expiration year shouldn't be null");
        if ($value < 1970 || strlen(strval($value)) !== 4) {
            throw new IllegalArgumentError("Expiration year needs to be a four digit number.");
        }
        $this->expire_year = $value;
    }


    public function getNumber():string {
        return $this->number;
    }

    public function setNumber(string $value) {
        if ($value == null) {
            throw new IllegalArgumentError("Card number is not allowed to be null.");
        }
        $this->number = $value;
    }

    public function getType():string {
        return $this->type;
    }

    public function setType( string $value) {
    if ($value == null) {
        throw new IllegalArgumentError("Card type is not allowed to be null.");
    } else if (array_search($value, ["visa", "mastercard", "discover", "amex"]) === false) {
        throw new IllegalArgumentError("Unknown card type. Allowed values are: 'visa', 'mastercard', 'discover' or 'amex'.");
    }
        $this->type = $value;
    }


}