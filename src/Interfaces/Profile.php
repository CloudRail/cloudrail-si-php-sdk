<?php

namespace CloudRail\Interfaces;
use CloudRail\Type\DateOfBirth;

interface Profile
{
    /**
     * @return string A unique identifier for the authenticated user. All services provide this value. Useful for "Login with ...". Prefixed with the lowercased service name and a minus.
     */
    public function getIdentifier();

    /**
     * @return string The user's full name or null if not present
     */
    public function getFullName();

    /**
     * @return string The user's email address or null if not present
     */
    public function getEmail();

    /**
     * @return string The user's gender, normalized to be one of "female", "male", "other" or null if not present
     */
    public function getGender();

    /**
     * @return string The description the user has given themselves or null if not present
     */
    public function getDescription();

    /**
     * @return DateOfBirth The date of birth in a special format, see {@link DateOfBirth}
     */
    public function getDateOfBirth();

    /**
     * @return string The locale/language setting of the user, e.g. "en", "de" or null if not present
     */
    public function getLocale();

    /**
     * @return string The URL of the user's profile picture or null if not present
     */
    public function getPictureURL();
}