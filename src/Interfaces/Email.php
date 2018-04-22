<?php

namespace CloudRail\Interfaces;

interface Email {

    /**
     * Sends an email. Used like sendEmail("info@cloudrail.com", "CloudRail", Arrays.asList("foo@bar.com", "bar@foo.com"), "Welcome", "Hello from CloudRail", null, null, null).
     * Throws if an error occurs.
     *
     * @param fromAddress Mandatory. The sender email address. Must normally be registered with the corresponding service.
     * @param fromName Mandatory. The from name to be displayed to the recipient(s).
     * @param string[] toAddresses Mandatory. A list of recipient email addresses.
     * @param subject Mandatory. The email's subject line.
     * @param textBody The email's body plain text part. Either this and/or the htmlBody must be specified.
     * @param htmlBody The email's body HTML part. Either this and/or the textBody must be specified.
     * @param ccAddresses Optional. A list of CC recipient email addresses.
     * @param bccAddresses Optional. A list of BCC recipient email addresses.
     * @param Attachment[] Optional. A list of attachments.
     */
    public function sendEmail(string $fromAddress,
                                string $fromName,
                                array $toAddresses,
                                string $subject,
                                string $textBody,
                                string $htmlBody,
                                array $ccAddresses,
                                array $bccAddresses,
                                array $attachments);

}