<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 19/11/17
 * Time: 23:45
 */

namespace CloudRail\ServiceCode;

class VarAddress{
    /**
     * @var string
     */
    public $addressString;

    public function __construct(string $addressString){
        $this->addressString = $addressString;
    }

}