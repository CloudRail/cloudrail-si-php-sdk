<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 12/03/18
 * Time: 04:14
 */

namespace CloudRail\Interfaces;

interface SMS {

    /**
     * Sends an SMS message, used like sendSMS("CloudRail", "+4912345678", "Hello from CloudRail").
     * Throws if an error occurs.
     *
     * @param fromName A alphanumeric sender id to identify/brand the sender. Only works in some countries.
     * @param toNumber The recipients phone number in E.164 format, e.g. +4912345678.
     * @param content The message content. Limited to 1600 characters, messages > 160 characters are sent and charged as multiple messages.
     */
    public function sendSMS(string $fromName, string $toNumber, string $content);
}