<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 22/01/18
 * Time: 10:49
 */

namespace CloudRail\Type;

use CloudRail\Error\InternalError;
use CloudRail\ServiceCode\Helper;
use \DateTime as DateTime;
use \DateTimeZone as DateTimeZone;

class CloudRailDate extends SandboxObject implements Comparable
{
    /**
     * @var DateTime
     */
    private $date;

    /**
     * @var integer
     */
    private $time;


    /**
     * Address constructor.
     * @param string[] ...$addressValues
     * @throws InternalError
     */
    public function __construct(string ...$addressValues) {
        if (count($addressValues) == 0 ){
            $this->date = new DateTime('now', new DateTimeZone("UTC"));
        } else
        if (count($addressValues) == 1 ){ // only number (timestamp) I assume it initializes the native type in seconds and also returns in seconds
            $this->date =  new DateTime('now', new DateTimeZone("UTC"));
            $this->date->setTimestamp(intval($addressValues[0]));

        } else {
            throw new InternalError("Constructor needs all arguments to be initialized in class " . get_class($this));
        }

        return $this;
    }

    public function getDate(){
        return $this->date;
    }

    public function getTime(){
        return $this->date->getTimestamp();
    }

    public function setTime($timestamp) {
        Helper::assert(Helper::isInteger($timestamp));
        $this->date->setTimestamp($timestamp); // divide by 10^3
    }

    public function getRfcTime2822() {
        return $this->date->format(DATE_RFC2822);
    }

    public function getRfcTime1123() {
        return $this->date->format("D, d M Y H:i:s \G\M\T");
    }

    public function compareTo($obj):int {
        if (!($obj instanceof CloudRailDate)) {
            throw new InternalError("Comparing a Date with a non-Date");
        }

        if ($this->getTime() < $obj->getTime()) return -1;
        else if ($this->getTime() > $obj->getTime()) return 1;
        else if ($this->getTime() === $obj->getTime()) return 0;
        else throw new InternalError("Comparing a Date with a non-Date");
    }
}