<?php

/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 12/03/18
 * Time: 04:19
 */

namespace CloudRail\Interfaces;
use CloudRail\Type\MessagingAttachment;

interface Messaging {


    public function sendMessage(string $receiverId, string $message);

    public function sendImage(
                    string $receiverId,
                    string $message,
                    string $identifier,
                    resource $imageStream,
                    string $previewUrl,
                    string $fileName);

    public function sendVideo(
                    string $receiverId,
                    string $message,
                    string $identifier,
                    resource $videoStream,
                    string $previewUrl,
                    int $size);

    public function sendAudio(
                    string $receiverId,
                    string $message,
                    string $identifier,
                    resource $audioStream,
                    string $previewUrl,
                    string $fileName,
                    int $size);

    public function sendFile(
                    string $receiverId,
                    string $message,
                    string $identifier,
                    resource $fileStream,
                    string $previewUrl,
                    string $fileName,
                    int $size);

    public function parseReceivedMessages(resource $httpRequest);

    public function downloadContent(MessagingAttachment $attachment);

    public function sendCarousel(string $receiverId, array $messageItems);

}