<?php

/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 10/12/17
 * Time: 22:43
 */

namespace CloudRail\Error;

use \Error as Error;

class UserError extends Error {

    public function __construct(string $message) {
        Error::__construct("An error occured that you should be able to fix. The error message is:\n" . $message);
    }

}
