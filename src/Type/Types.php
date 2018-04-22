<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 08/01/18
 * Time: 16:25
 */


namespace CloudRail\Type;

class Types {

    /**
     * @var array Indexed array with
     */
    public static $typeMap= [
        "DateOfBirth" => "DateOfBirth",
        "CloudMetaData" => "CloudMetaData",
        "Error" => "CloudRailError",
        "Date" => "CloudRailDate",
        "Address" => "Address",
        "Charge" => "Charge",
        "CreditCard" => "CreditCard",
        "Location" => "Location",
        "Refund" => "Refund",
        "Subscription" => "Subscription",
        "SubscriptionPlan" => "SubscriptionPlan",
        "SpaceAllocation" => "SpaceAllocation",
        "ImageMetaData" => "ImageMetaData",
        "Bucket" => "Bucket",
        "BusinessFileMetaData" => "BusinessFileMetaData",
        "AdvancedRequestSpecification" => "AdvancedRequestSpecification",
        "AdvancedRequestResponse" => "AdvancedRequestResponse",
        "Attachment" => "Attachment",
        "ChannelMetaData" => "ChannelMetaData",
        "VideoMetaData" => "VideoMetaData",
        "Message" => "Message",
        "MessagingAttachment" => "MessagingAttachment",
        "POI" => "POI",
    ];
}