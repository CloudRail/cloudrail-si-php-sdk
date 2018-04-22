<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 12/03/18
 * Time: 04:21
 */

namespace CloudRail\Interfaces;

interface Social
{
    /**
     * Creates a new post/update to the currently logged in user's wall/stream/etc.
     * Throws an exception if the content is too long for the service instance.
     * @param content The post's content
     */
    public function postUpdate(string $content);

    /**
     * Retrieves a list of connection/friend/etc. IDs.
     * The IDs are compatible with those returned by Profile.getIdentifier().
     * @return A (possibly empty) list of IDs
     */
    public function getConnections();

    /**
     * Creates a new post/update to the currently logged in user's wall/stream/etc posting an
     * image and a message.
     * Error is set if the message is too long for the service instance.
     *
     * @param string $message The message that shall be posted together with the image.
     * @param resource  $image Stream containing the image content.
     */
    public function postImage(string $message,  $image);

    /**
     * Creates a new post/update to the currently logged in user's wall/stream/etc posting a
     * video and a message.
     * Error is set if the message is too long for the service instance.
     *
     * @param message The message that shall be posted together with the video.
     * @param video Stream containing the video content.
     * @param size The size of the video in bytes.
     * @param mimeType The mime type of the video, for instance video/mp4.
     */
    public function postVideo(string $message,  $video, int $size, string $mimeType);
}