<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 10/02/18
 * Time: 04:06
 */

namespace CloudRail\Error;

use \Error as Error;

class AuthenticationError extends Error
{
    public function __construct(string $message) {
        Error::__construct("Authentication Error:\n" . $message);
    }
}