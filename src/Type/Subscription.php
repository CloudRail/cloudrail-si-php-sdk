<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 22/01/18
 * Time: 10:43
 */

namespace CloudRail\Type;

use CloudRail\Error\InternalError;

class Subscription extends SandboxObject {
    /**
     * @var integer
     */
    public $created;

    /**
     * @var string
     */
    public $description;

    /**
     * @var string
     */
    public $id;

    /**
     * @var integer
     */
    public $lastCharge;

    /**
     * @var string
     */
    public $name;

    /**
     * @var integer
     */
    public $nextCharge;

    /**
     * @var CreditCard
     */
    public $creditCard;

    /**
     * @var string
     */
    public $state;

    /**
     * @var string
     */
    public $subscriptionPlanID;


    /**
     * Subscription constructor.
     * @param string[] ...$addressValues
     * @throws InternalError
     */
    public function __construct(string ...$addressValues) {
        if (count($addressValues) == 0 ){
            return $this;
        }
        //if there are parameters, they need to fill all the properties, they also need to be in order
        if (count($addressValues) == count(get_object_vars($this))) {
            $this->created = $addressValues[0];
            $this->description = $addressValues[1];
            $this->id = $addressValues[2];
            $this->lastCharge = $addressValues[3];
            $this->name = $addressValues[4];
            $this->nextCharge = $addressValues[5];
            $this->creditCard = $addressValues[6];
            $this->state = $addressValues[7];
            $this->subscriptionPlanID = $addressValues[8];

        } else {
            throw new InternalError("Constructor needs all arguments to be initialized in class " . get_class($this));
        }
        return $this;
    }

    /**
     * @param int $created
     * @param string $description
     * @param string $id
     * @param int $lastCharge
     * @param string $name
     * @param int $nextCharge
     * @param CreditCard $creditCard
     * @param string $state
     * @param string $subscriptionPlanID
     * @throws IllegalArgumentError
     * @return Subscription
     */
    public static function new(integer $created, string $description, string $id, integer $lastCharge, string $name, integer $nextCharge,
                               CreditCard $creditCard, string $state, string $subscriptionPlanID){

        if ($description == null || $id == null || $name == null || $creditCard == null || $state == null || $subscriptionPlanID == null) {
            throw new IllegalArgumentError("At least one of the parameters is undefined.");
        } else if (array_search($state,["active", "cancelled"] ) === false) {
            throw new IllegalArgumentError("Unknown state. Allowed values are: 'active' or 'canceled'.");
        }


        $newObject = new Subscription();
        $newObject->created = $created;
        $newObject->description = $description;
        $newObject->id = $id;
        $newObject->lastCharge = $lastCharge;
        $newObject->name = $name;
        $newObject->nextCharge = $nextCharge;
        $newObject->creditCard = $creditCard;
        $newObject->state = $state;
        $newObject->subscriptionPlanID = $subscriptionPlanID;
        return $newObject;
    }
}