<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 18/12/17
 * Time: 17:49
 */

namespace CloudRail\Type;

class CloudRailError extends SandboxObject {

    /**
     * @var string The error message
     */
    private $message;

    /**
     * @var string the type of the error
     */
    private $type;

    public  const ILLEGAL_ARGUMENT = 'IllegalArgument';
    public  const AUTHENTICATION = 'Authentication';
    public  const NOT_FOUND = 'NotFound';
    public  const HTTP = 'Http';
    public  const SERVICE_UNAVAILABLE = 'ServiceUnavailable';
    public  const NONE = "";

    public function __construct(string $message = null, string $type = null) {
        $this->message = $message;
        $this->type = $type;
    }

    public function getMessage():string {
    return $this->message;
    }

    public function setMessage(string $message) {
        $this->message = $message;
    }

    public function getType():string {
        return $this->type;
    }

    public function setType(string $type) {
        $this->type = $type;
    }

    public function toString():string {
        return $this->message;
    }

    public function getErrorType():int {
        return ErrorType::getValueOf($this->type);
    }
}