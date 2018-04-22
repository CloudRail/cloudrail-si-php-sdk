<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 13/02/18
 * Time: 04:21
 */

namespace CloudRail\Type;

class Attachment extends SandboxObject{
    /**
     * @var resource
     */
    public $content;

    /**
     * @var string
     */
    public $type;

    /**
     * @var string
     */
    public $filename;
}