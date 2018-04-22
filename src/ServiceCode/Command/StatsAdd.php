<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 12/03/18
 * Time: 07:36
 */

namespace CloudRail\ServiceCode\Command;
use CloudRail\ServiceCode\Command;
use CloudRail\ServiceCode\Sandbox;

class StatsAdd implements Command{

    public function getIdentifier(): string {
        return "stats.add";
    }

    public function execute(Sandbox &$environment, array $parameters)
    {
    }
}