<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 22/01/18
 * Time: 10:42
 */

namespace CloudRail\Type;

use CloudRail\Error\InternalError;
use CloudRail\Error\IllegalArgumentError;

class SubscriptionPlan extends SandboxObject {

    /**
     * @var integer
     */
    public $amount;

    /**
     * @var integer
     */
    public $created;

    /**
     * @var string
     */
    public $currency;

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
    public $interval;

    /**
     * @var integer
     */
    public $interval_count;

    /**
     * @var integer
     */
    public $nextCharge;

    /**
     * @var string
     */
    public $name;

    /**
     * SubscriptionPlan constructor.
     * @param string[] ...$addressValues
     * @throws InternalError
     */
    public function __construct(string ...$addressValues) {
        if (count($addressValues) == 0 ){
            return $this;
        }
        //if there are parameters, they need to fill all the properties, they also need to be in order
        if (count($addressValues) == count(get_object_vars($this))) {
            $this->amount = $addressValues[0];
            $this->created = $addressValues[1];
            $this->currency = $addressValues[2];
            $this->description = $addressValues[3];
            $this->id = $addressValues[4];
            $this->interval = $addressValues[5];
            $this->interval_count = $addressValues[5];
            $this->name = $addressValues[5];

        } else {
            throw new InternalError("Constructor needs all arguments to be initialized in class " . get_class($this));
        }
        return $this;
    }


    /**
     * @param int $amount
     * @param string $created
     * @param string $currency
     * @param string $description
     * @param string $id
     * @param int $interval
     * @param int $interval_count
     * @param string $name
     * @return SubscriptionPlan
     * @throws IllegalArgumentError
     */
    public static function new(integer $amount, string $created, string $currency, string $description, string $id, integer $interval,
                               integer $interval_count, string $name){

        if ($currency == null || $description == null || $id == null || $interval == null || $name == null) {
            throw new IllegalArgumentError("At least one of the parameters is undefined.");
        } else if ($amount < 0) {
            throw new IllegalArgumentError("Amount can not be less than 0.");
        } else if (count($currency) !== 3) {
            throw new IllegalArgumentError("Passed currency is not a valid three-letter currency code.");
        } else if (array_search($interval,["day", "week", "month", "year"]) === false) {
            throw new IllegalArgumentError("Unknown interval. Allowed values are: 'day', 'week', 'month' or 'year'.");
        }

        $newObject = new SubscriptionPlan();
        $newObject->amount = $amount;
        $newObject->created = $created;
        $newObject->currency = $currency;
        $newObject->description = $description;
        $newObject->id = $id;
        $newObject->interval = $interval;
        $newObject->interval_count = $interval_count;
        $newObject->name = $name;

        return $newObject;
    }
}