<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 10/12/17
 * Time: 17:21
 */

namespace CloudRail\ServiceCode;

interface Command {


    public function getIdentifier():string;

    public function execute(Sandbox &$environment, array $parameters);

}