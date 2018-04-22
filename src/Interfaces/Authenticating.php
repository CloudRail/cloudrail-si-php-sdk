<?php

namespace CloudRail\Interfaces;

interface Authenticating {

    /**
     * Optional! Explicitly triggers user authentication.
     * Allows better control over the authentication process.
     * Optional because all methods that require prior authentication will trigger it automatically,
     * unless this method has been called before.
     */
    public function login();

    /**
     * Optional! Revokes the current authentication.
     */
    public function logout();
}