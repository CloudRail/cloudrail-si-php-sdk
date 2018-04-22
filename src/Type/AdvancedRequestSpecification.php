<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 10/02/18
 * Time: 04:36
 */

namespace CloudRail\Type;

use CloudRail\Error\InternalError;
use CloudRail\Error\IllegalArgumentError;
use CloudRail\ServiceCode\Helper;

class AdvancedRequestSpecification extends SandboxObject {

    /**
     * @var string
     */
    public $url;

    /**
     * @var string
     */
    public $method = "GET";

    /**
     * @var resource
     */
    public $body;

    /**
     * @var array
     */
    public $headers = [];

    /**
     * @var bool
     */
    public $appendAuthorization = true;

    /**
     * @var bool
     */
    public $checkErrors = true;

    /**
     * @var bool
     */
    public $appendBaseUrl = true;



    public function __construct(string ...$addressValues) {
        if (count($addressValues) == 0 ){
            $this->url = null;
        } else
            if (count($addressValues) == 1 ){
                $this->url = $addressValues[0];
            } else {
                throw new InternalError("Constructor needs all arguments to be initialized in class " . get_class($this));
            }

        return $this;
    }


    /**
     * @param string $url
     * @return AdvancedRequestSpecification
     */
    public static function new(string $url) {
        $newObject = new AdvancedRequestSpecification();
        $newObject->url = $url;
        return $newObject;
    }

    /**
     * Specify the request's method, default is GET
     * @param string $method - The HTTP method
     * @throws IllegalArgumentError
     */
    public function setMethod(string $method):void {
        if (array_search($method,Helper::HTTP_METHODS) !== false) {
            throw new IllegalArgumentError("Request method must be one of " . join(". ",Helper::HTTP_METHODS));
        }
        $this->method = $method;
    }


    /**
     * Specify the body to sent with this request providing a readable stream
     * @param resource $body - The request's body
     * @throws IllegalArgumentError
     */
    public function setBodyAsStream(resource $body):void {
        if (!Helper::isStream($body)) {
            throw new IllegalArgumentError("Request body must be a readable stream");
        }
        $this->body = $body;
    }

    /**
     * Specify the body to sent with this request providing a string
     * @param string $body
     * @throws IllegalArgumentError
     */
    public function setBodyAsString(string $body):void {
        if (!Helper::isString($body)) {
            throw new IllegalArgumentError("Request body must be a string");
        }
        //streamify
        $stream = fopen('php://memory','r+'); //creating the stream
        fwrite($stream, $body);//writing string on stream
        rewind($stream);//rewind the pointer.
        $this->body = $stream;
    }

    /**
    * Specify the body to sent with this request providing an object that will be converted to JSON.
    * @param array $body
    * @throws IllegalArgumentError
    */
    public function setBodyStringifyJson(array $body):void {
        if ($body == null) {
            throw new IllegalArgumentError("Request body may not be null/undefined if set");
        }
        $jsonString = json_encode($body);
        $this->setBodyAsString($jsonString);
    }
}