<?php

namespace CloudRail\Interfaces;

use CloudRail\Type\CloudMetaData;
use CloudRail\Type\SpaceAllocation;

interface CloudStorage {

    /**
     * @param string $filePath
     * @throws InvalidArgumentException
     * @throws AuthenticationException
     * @throws NotFoundException
     * @throws HttpException
     * @throws ServiceUnavailableException
     * @throws Exception
     * @return resource
     */
    public function download(string $filePath);

    /**
     * @param string $filePath
     * @param resource $stream
     * @param int $size
     * @param bool $overwrite
     * @throws InvalidArgumentException
     * @throws AuthenticationException
     * @throws NotFoundException
     * @throws HttpException
     * @throws ServiceUnavailableException
     * @throws Exception
     */
    public function upload(string $filePath,  $stream, int $size, bool $overwrite):void;

    /**
     * @param string $filePath
     * @param resource $stream
     * @param int $size
     * @param bool $overwrite
     * @throws InvalidArgumentException
     * @throws AuthenticationException
     * @throws NotFoundException
     * @throws HttpException
     * @throws ServiceUnavailableException
     * @throws Exception
     */
    public function uploadWithContentModifiedDate(string $filePath,  $stream, int $size, bool $overwrite, int $contentModifiedDate):void;

        /**
     * @param string $sourcePath
     * @param string $destinationPath
     * @throws InvalidArgumentException
     * @throws AuthenticationException
     * @throws NotFoundException
     * @throws HttpException
     * @throws ServiceUnavailableException
     * @throws Exception
     */
    public function move(string $sourcePath, string $destinationPath):void;

    /**
     * @param string $filePath
     * @throws InvalidArgumentException
     * @throws AuthenticationException
     * @throws NotFoundException
     * @throws HttpException
     * @throws ServiceUnavailableException
     * @throws Exception
     */
    public function delete(string $filePath):void;

    /**
     * @param string $sourcePath
     * @param string $destinationPath
     * @throws InvalidArgumentException
     * @throws AuthenticationException
     * @throws NotFoundException
     * @throws HttpException
     * @throws ServiceUnavailableException
     * @throws Exception
     */
    public function copy(string $sourcePath, string $destinationPath):void;

    /**
     * @param string $folderPath
     * @throws InvalidArgumentException
     * @throws AuthenticationException
     * @throws NotFoundException
     * @throws HttpException
     * @throws ServiceUnavailableException
     * @throws Exception
     */
    public function createFolder(string $folderPath):void;

    /**
     * @param string $filePath
     * @throws InvalidArgumentException
     * @throws AuthenticationException
     * @throws NotFoundException
     * @throws HttpException
     * @throws ServiceUnavailableException
     * @throws Exception
     * @return CloudMetaData
     */
    public function getMetadata(string $filePath):CloudMetaData;

    /**
     * @param string $folderPath
     * @throws InvalidArgumentException
     * @throws AuthenticationException
     * @throws NotFoundException
     * @throws HttpException
     * @throws ServiceUnavailableException
     * @throws Exception
     * @return array
     */
    public function getChildren(string $folderPath):array;

    /**
     * @param string $path
     * @param int $offset
     * @param int $limit
     * @throws InvalidArgumentException
     * @throws AuthenticationException
     * @throws NotFoundException
     * @throws HttpException
     * @throws ServiceUnavailableException
     * @throws Exception
     * @return array
     */
    public function getChildrenPage(string $path, int $offset, int $limit):array;

    /**
     * @throws InvalidArgumentException
     * @throws AuthenticationException
     * @throws NotFoundException
     * @throws HttpException
     * @throws ServiceUnavailableException
     * @throws Exception
     * @return string
     */
    public function getUserLogin():string;

    /**
     * @throws InvalidArgumentException
     * @throws AuthenticationException
     * @throws NotFoundException
     * @throws HttpException
     * @throws ServiceUnavailableException
     * @throws Exception
     * @return string
     */
    public function getUserName():string;

    /**
     * @param string $path
     * @throws InvalidArgumentException
     * @throws AuthenticationException
     * @throws NotFoundException
     * @throws HttpException
     * @throws ServiceUnavailableException
     * @throws Exception
     * @return string
     */
    public function createShareLink(string $path):string;

    /**
     * @throws InvalidArgumentException
     * @throws AuthenticationException
     * @throws NotFoundException
     * @throws HttpException
     * @throws ServiceUnavailableException
     * @throws Exception
     * @return SpaceAllocation
     */
    public function getAllocation():SpaceAllocation;

    /**
     * @param string $path
     * @throws InvalidArgumentException
     * @throws AuthenticationException
     * @throws NotFoundException
     * @throws HttpException
     * @throws ServiceUnavailableException
     * @throws Exception
     * @return bool
     */
    public function exists(string $path):bool;

    /**
     * @param string $path
     * @throws InvalidArgumentException
     * @throws AuthenticationException
     * @throws NotFoundException
     * @throws HttpException
     * @throws ServiceUnavailableException
     * @throws Exception
     * @return resource
     */
    public function getThumbnail(string $path);

    /**
     * @param string $query
     * @throws InvalidArgumentException
     * @throws AuthenticationException
     * @throws NotFoundException
     * @throws HttpException
     * @throws ServiceUnavailableException
     * @throws Exception
     * @return array
     */
    public function search(string $query):array;

    /**
     * @throws InvalidArgumentException
     * @throws AuthenticationException
     * @throws NotFoundException
     * @throws HttpException
     * @throws ServiceUnavailableException
     * @throws Exception
     */
    public function login():void;

    /**
     * @throws InvalidArgumentException
     * @throws AuthenticationException
     * @throws NotFoundException
     * @throws HttpException
     * @throws ServiceUnavailableException
     * @throws Exception
     */
    public function logout():void;
}