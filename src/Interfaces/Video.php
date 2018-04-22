<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 12/03/18
 * Time: 04:21
 */

namespace CloudRail\Interfaces;

interface Video {

    public function uploadVideo(string $title,
                                string $description,
                                 $video,
                                int $size,
                                string $channelId,
                                string $mimeType);

    public function searchVideos(string $query, int $offset, int $limit);

    public function getVideo(string $videoId);

    public function getChannel(string $channelId);

    public function getOwnChannel();

    public function listVideosForChannel(string $channelId, int $offset, int $limit);
}