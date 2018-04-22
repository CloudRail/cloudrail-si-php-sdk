<?php

namespace CloudRail\Interfaces;

use CloudRail\Type\Bucket;

interface BusinessCloudStorage {

    /**
     * Get a list of all buckets within your account.
     *
     * @return Bucket[] List of buckets. This might be an empty list if there are no buckets.
     */
    public function listBuckets();

    /**
     * Creates a new empty bucket.
     *
     * @param string $name The name of the new bucket.
     * @return Bucket The newly created bucket.
     * @error IllegalArgumentError Is set if a bucket with the same name already exists.
     */
    public function createBucket(string $name);

    /**
     * Deletes the specified bucket with all its content.
     *
     * @param Bucket $bucket The bucket which will be deleted.
     * @error NotFoundError Is set if the bucket does not exist.
     */
    public function deleteBucket(Bucket $bucket);

    /**
     * Get a list of files contained in the specified bucket.
     *
     * @param Bucket $bucket The bucket containing the files.
     * @error NotFoundError Is set if the specified bucket does not exist.
     */
    public function listFiles(Bucket $bucket);

    /**
     * Uploads a new file into a bucket or replaces the file if it is already present.
     *
     * @param Bucket    $bucket  The bucket into which the file shall be put.
     * @param string    $name    The name of the file.
     * @param resource  $content The file content as a readable stream.
     * @param int       $size    The amount of bytes that the file contains.
     */
    public function uploadFile(Bucket $bucket, string $name,  $content, int $size);

    /**
     * Deletes a file within a bucket.
     *
     * @param Bucket $bucket   The bucket that contains the file.
     * @param string $fileName The name of the file.
     * @error NotFoundError Is set if there is no file with the given
     *                      name inside the bucket.
     */
    public function deleteFile(string $fileName, Bucket $bucket);

    /**
     * Downloads a file from a bucket.
     *
     * @param Bucket $bucket   The bucket which contains the file.
     * @param string $fileName The name of the file.
     * @return resource The content of the file as a readable stream.
     * @error NotFoundError Is set if there is no file with the given
     *                      name inside the bucket.
     */
    public function downloadFile(string $fileName, Bucket $bucket);

    /**
     * Get metadata of a file containing the name, the size and the last
     * modified date.
     *
     * @param Bucket $bucket   The bucket where the file is located.
     * @param string $fileName The name of the file.
     */
    public function getFileMetadata(Bucket $bucket, string $fileName);
}