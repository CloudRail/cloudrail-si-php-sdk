<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 22/01/18
 * Time: 10:48
 */

namespace CloudRail\Type;

class DateOfBirth extends SandboxObject {
    /**
     * @var int
     */
    public $day;

    /**
     * @var int
     */
    public $month;

    /**
     * @var int
     */
    public $year;

    /**
     * DateOfBirth constructor.
     * @param string[] ...$addressValues
     * @throws InternalError
     */
    public function __construct(string ...$addressValues) {
        if (count($addressValues) == 0 ){
            return $this;
        }
        //if there are parameters, they need to fill all the properties, they also need to be in order
        if (count($addressValues) == count(get_object_vars($this))) {
            $this->day = $addressValues[0];
            $this->month = $addressValues[1];
            $this->year = $addressValues[2];
        } else {
            throw new InternalError("Constructor needs all arguments to be initialized in class " . get_class($this));
        }
        return $this;
    }

    /**
     * @param int $day
     * @param int $month
     * @param int $year
     * @return DateOfBirth
     */
    public static function new(int $day, int $month, int $year) {
        $newObject = new DateOfBirth();
        $newObject->day = $day;
        $newObject->month = $month;
        $newObject->year = $year;
        return $newObject;
    }
}