<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 13/02/18
 * Time: 04:23
 */

namespace CloudRail\Type;

use \Error as Error;
class AdvancedRequestResponse extends SandboxObject {

    /**
     * @var resource Stream readable
     */
    private $body;

    /**
     * @var string[] Associative array of strings
     */
    private $headers;

    /**
     * @var integer
     */
    private $status;

    /**
     * @var string
     */
    private $stringBody;

    /**
     * Returns the response's body as a readable stream
     *
     * @return {stream.Readable} The response's body
     */
    public function getBodyAsStream():resource {
        if (!$this->stringBody) {
            return $this->body;
        } else {
            throw new Error("Response stream was already read");
        }
    }

    /**
     * Returns the response's body as a string
     *
     * @return string The response's body
     * @throws Error
     */
    public function getBodyAsString():string {

        if (!$this->stringBody) {
            return stream_get_contents($this->body);
        } else {
            throw new Error("Error while reading body stream.");
        }
    }

    /**
     * Returns the response's body as an object resulting from JSON parsing it.
     *
     * @return array The response's body
     * @throws Error
     */
    public function getBodyJsonParsed():array {
        $rawString = $this->getBodyAsString();
        if (!$rawString) {
            throw new Error("Error while reading body stream.");
        } else {
            json_decode($rawString,true);
        }
    }

    /**
     * Returns the response headers as an object
     *
     * @return string[] The response's headers
     */
    public function getHeaders():array {
        return $this->headers;
    }

    /**
     * Returns the response status code as a number
     *
     * @return integer The response's status code
     */
    public function getStatus():integer {
        return $this->status;
    }

}