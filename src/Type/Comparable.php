<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 17/01/18
 * Time: 01:34
 */

namespace CloudRail\Type;

interface Comparable
{
    /**
     * @param object $object
     * @return int numeric negative value if $this < $object, positive if $this > $object, 0 otherwise (if objects are considered equal)
     * @throws ComparatorException if objects are not comparable to each other
     */
    public function compareTo($object): int;
}