<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 15/11/17
 * Time: 14:02
 */

namespace CloudRail\ServiceCode;

use CloudRail\Error\InternalError;
use CloudRail\Type\CloudRailError;
use CloudRail\Type\SandboxObject;

use \stdClass as stdClass;

class Sandbox{

    private static $LIST_MAX_ADD_JUMP_ALLOWED = 32;
    private static $JSON_AWARE_MARKER = "@JSONAware/";

    /**
     * @var array[]
     */
    public $serviceCode;

    /**
     * @var array
     */
    public $localVariablesStack;

    /**
     * @var array
     */
    public $parametersStack;

    /**
     * @var array
     */
    public $persistentStorage;

    /**
     * @var string[]
     */
    public $codeFunctionNameStack;

    /**
     * @var int[]
     */
    public $codeLineStack;

    /**
     * @var CloudRailError
     */
    public $thrownError = null;

    /**
     * @var array
     */
    public $instanceDependencyStorage;


    /**
     * Sandbox constructor.
     * @param array $serviceCode
     * @param array $persistentStorage
     * @param array $instanceDependencyStorage
     */
    public function __construct(array $serviceCode ,array &$persistentStorage,array &$instanceDependencyStorage) {
        $this->localVariablesStack = [];
        $this->parametersStack = [];
        $this->codeFunctionNameStack = [];
        $this->codeLineStack = [];
        $this->persistentStorage = &$persistentStorage;
        $this->serviceCode = $serviceCode;
        $this->instanceDependencyStorage = &$instanceDependencyStorage;
    }

    /**
     * @param string $functionName
     * @param int $codeLine
     */
    public function createNewStackLevel(string $functionName, int $codeLine){
        array_push($this->localVariablesStack, []);
        array_push($this->parametersStack, []);
        array_push($this->codeFunctionNameStack, $functionName);
        array_push($this->codeLineStack, $codeLine);
    }

    /**
     * @return null|array
     */
    public function &currentParameters() {
        if (count($this->parametersStack) === 0) return null;
        else return $this->parametersStack[count($this->parametersStack) - 1];
    }

    public function setCurrentParameters(array $parameters){
        $this->parametersStack[count($this->parametersStack) - 1] = $parameters;
    }

    /**
     * @return null|string
     */
    public function currentFunctionName(){
        if (count($this->codeFunctionNameStack) === 0) return null;
        else return $this->codeFunctionNameStack[count($this->codeFunctionNameStack) - 1];
    }

    /**
     * @return array
     */
    public function currentFunctionCode() {
        return $this->serviceCode[$this->currentFunctionName()];
    }


    /**
     * @return int
     */
    public function currentServiceCodeLine() {
        if (count($this->codeLineStack) === 0) return -1;
        else return $this->codeLineStack[count($this->codeLineStack) - 1];
    }

    /**
     * @param int $amount
     */
    public function incrementCurrentServiceCodeLine(int $amount) {
        if (count($this->codeLineStack) === 0) return;
        else $this->codeLineStack[count($this->codeLineStack) - 1] = $this->codeLineStack[count($this->codeLineStack) - 1] + $amount;
    }





    public function returnFromFunction() {
        if (count($this->codeFunctionNameStack) <= 1) return;
        $currentStackLevel = count($this->codeFunctionNameStack) - 1;
        $callFunctionCommandParameters = Interpreter::decodeCommandParameters(
            $this->serviceCode[$this->codeFunctionNameStack[$currentStackLevel - 1]]
            [$this->codeLineStack[$currentStackLevel - 1]]
            );

        for ($i = 0; $i < count($callFunctionCommandParameters); $i++) {

            $paramterVar = $callFunctionCommandParameters[$i];

                if ($paramterVar instanceof VarAddress) {
                    $value = $this->parametersStack[$currentStackLevel][$i - 1];
                    $this->setVariable($paramterVar, $value, $currentStackLevel - 1);
                    unset($value);//This is necessary to wipe the reference holder without destroying the value
                }
        }

        array_splice($this->codeFunctionNameStack,$currentStackLevel,1);
        array_splice($this->codeLineStack,$currentStackLevel,1);
        array_splice($this->localVariablesStack,$currentStackLevel,1);
        array_splice($this->parametersStack,$currentStackLevel,1);

        $this->incrementCurrentServiceCodeLine(1);
    }

    /**
     * @param $varAddress
     * @param $value
     * @param int $stackLevel
     * @return bool
     * @throws InternalError
     */
    public function setVariable( $varAddress, &$value, int $stackLevel = -1):bool {

        //Default value of the stack level is the current one ( top most )
        if ($stackLevel === -1){
            $stackLevel = count($this->localVariablesStack)-1;
        }

        /**
         * @var array Declaration ov the exploded into array varAddress (if it is not exploded already)
         */
        $varAddressParts = null;

        if (gettype($varAddress) == "object" && $varAddress instanceof VarAddress){
            $varAddressParts = Sandbox::decodeVariableAddress($varAddress); // if it is a Var Address the break it into parts array
        }
        else{
            $varAddressParts = $varAddress;
        }

        /**
         * @var array Getting the proper stack of the variable to be set (P,S or L)
         */
        $variables = &$this->getStackForAddressPart($varAddressParts[0], $stackLevel);


        //Determine the index of the stack to be accessed.
        /**
         * @var int Getting what variable ( or index) of the stack to access.
         */
        $varIdx = $varAddressParts[1];


        if (count($varAddressParts)<= 2) {
            if (count($variables) === $varIdx){
                $reference = $value;
                array_push($variables, $reference);
            } else if (count($variables) > $varIdx) {
                $variables[$varIdx] = &$value;
            } else if (count($variables) + Sandbox::$LIST_MAX_ADD_JUMP_ALLOWED > $varIdx) {
                $sizeAux = count($variables);
                for ($i = 0; $i < ($varIdx - $sizeAux + 1); $i++) {array_push($variables, null);} // do we need curly braces here?
                $variables[$varIdx] = &$value;
            } else {
                throw new InternalError("Could not decode variable " . join(':',  $varAddressParts));
            }

            $this->setStackForAddressPart($varAddressParts[0], $stackLevel,$variables);
            return true;
        }

        $slices = array_slice($varAddressParts,2,count($varAddressParts));
        return $this->setEntry($variables[$varIdx], $slices, $value);
    }


    public function &getVariable($varAddress, int $stackLevel = -1, bool $emptyIsNull = null) {
        //Default value from expression
        if ($stackLevel === -1){
            $stackLevel = count($this->localVariablesStack)-1;
        }

        if ($emptyIsNull === null){ $emptyIsNull = false;}
        $varAddressParts = null;
        if ($varAddress instanceof VarAddress){
            $varAddressParts = Sandbox::decodeVariableAddress($varAddress);
        }
        else{
            $varAddressParts = $varAddress;
        }

        $variables = &$this->getStackForAddressPart($varAddressParts[0], $stackLevel);

        //if it is more than already instantiated and returnNull==null
        if ($emptyIsNull && $varAddressParts[1] >= count($variables)) {
            $nullReturn = null;
            return $nullReturn;
        }

        $localEntry = &$variables[$varAddressParts[1]];
        if (count($varAddressParts) <= 2) {
            return $localEntry;
        };

        $slices = array_slice($varAddressParts,2,count($varAddressParts));
        $entry = &$this->getEntry($localEntry,$slices, $emptyIsNull);
        return $entry;
    }


    /**
     * @param array $varAddressParts
     * @param int|null $stackLevel
     * @return bool
     */
    public function  deleteVariable(array $varAddressParts, int $stackLevel = null):bool {
        if (null === $stackLevel){
            $stackLevel = count($this->localVariablesStack)-1;
        }

        $variables = $this->getStackForAddressPart($varAddressParts[0], $stackLevel);
        $varIdx = $varAddressParts[1];

        if (count($varAddressParts) <= 2) {
            if ($varIdx < count($variables)) {
                $variables[$varIdx] = null;
            }
            return true;
        }

        return $this->deleteEntry($variables[$varIdx], array_slice($varAddressParts,2));

    }

    /**
     * @param string $part
     * @param int $stackLevel
     * @return array
     * @throws InternalError
     */
    public function &getStackForAddressPart(string $part, int $stackLevel):array {

        if ($part === "L") {
            $variables = &$this->localVariablesStack[$stackLevel];
        } else if ($part === "P") {
            $variables = &$this->parametersStack[$stackLevel];
        } else if ($part === "S") {
            $variables = &$this->persistentStorage;
        } else {
             throw new InternalError("Could not attribute variable part" . $part);
        }

        return $variables;
    }

    public function setStackForAddressPart(string $part, int $stackLevel, array $variables) {
        if ($part === "L") {
            $this->localVariablesStack[$stackLevel] = $variables;
        } else if ($part === "P") {
            $this->parametersStack[$stackLevel] = $variables;
        } else if ($part === "S") {
           $this->persistentStorage = $variables;
        } else {
            throw new InternalError("Could not attribute variable part" . $part);
        }
    }


    public static function  decodeVariableAddress(VarAddress $varAddress):array{
        $decAdr = [];
        string:$adr = $varAddress->addressString;

        Helper::assert($adr[0] !== "$");

        if ($adr[0] < '0' || $adr[0] > '9') {
            array_push($decAdr, $adr[0]);
            $adr = substr($adr,1);
        }

        $adrParts = explode(".", $adr);//adr.split(".");

        for ($i = 0; $i < count($adrParts); $i++) {
            $part = $adrParts[$i];

            if ( is_string($part) && is_numeric($part) ) {
                array_push($decAdr, intval($part));
            } else {
                array_push($decAdr,$part);
            }
        }

        return $decAdr;
    }


    /**
     * @param $baseContainer
     * @param $varAddress
     * @param $value
     * @return bool
     */
    public function setEntry(&$baseContainer , &$varAddress, &$value):bool {

        /** @var SandboxObject $container */
        $container = null;

        if (count($varAddress) > 1) {

            $container = &$this->getEntry($baseContainer, array_slice($varAddress,0, count($varAddress)-1), false);
        } else {
            $container = &$baseContainer;
        }

        $varAddressPart = $varAddress[count($varAddress) - 1];

        if (is_array($container)) {
            if (!is_string($varAddressPart)) {
                $varAddressPart = strval($varAddressPart);
            }
            $container[$varAddressPart] = &$value;
        } else if ($container instanceof SandboxObject) {
            if (!is_string($varAddressPart)) {
                $varAddressPart = strval($varAddressPart);
            }
            $container->set($varAddressPart, $value);
        } else {
            throw new InternalError("Unrecognized object type when setting or creating on:" .
                $this->currentFunctionName() . " " . $this->currentServiceCodeLine());
        }
        return false;
    }

    /**
     * @param SandboxObject $container
     * @param array $varAddress
     * @param bool|null $emptyIsNull
     * @return
     */
    public function &getEntry(&$container, array $varAddress, bool $emptyIsNull = null){
        if ($emptyIsNull == null){ $emptyIsNull = false;}

        $entry = new stdClass();
        $varAddressPart = $varAddress[0];

        if (is_array($container)) {// is object não vale aqui
            if (!is_string($varAddressPart)) {
                $varAddressPart = strval($varAddressPart);
            }

            if (!array_key_exists($varAddressPart,$container) && $emptyIsNull == true) {
                $nullReturn = null;
                return $nullReturn;
            }

            if (!array_key_exists($varAddressPart,$container)) {
                $container[$varAddressPart] = null;// throw exception??
            }
            $entry = &$container[$varAddressPart];


        } else if (get_class($container) === "CaseProxy") {
            if (!is_string($varAddressPart)) {
                $varAddressPart = strval($varAddressPart);
            }
            $entry = $container->get($varAddressPart);
        } else if ($container instanceof SandboxObject) {
            if (!is_string($varAddressPart)) {
                $varAddressPart = strval($varAddressPart);
            }
            $entry = $container->get($varAddressPart);
        }
        //If there is more path to follow, continue recursively
        if (count($varAddress) > 1) {
            return $this->getEntry($entry, array_slice($varAddress,1), $emptyIsNull);
        }

        return $entry;
    }

    /**
     * @param SandboxObject $container
     * @param $varAddress
     * @return bool
     */

    public function deleteEntry($container, array $varAddress):bool {
        if (count($varAddress) > 1) {

            $nextContainer = $this->getEntry($container, array_slice($varAddress,count($varAddress)-1), false);
            return $this->deleteEntry($nextContainer, array_slice($varAddress,1,count($varAddress)));
        }

        $varAddressPart = $varAddress[0];

        if (is_array($container)) {
            if (is_numeric($varAddressPart) && is_string($varAddressPart)) {
                $varAddressPart = intval($varAddressPart);
            }
            if (!is_numeric($varAddressPart) || $varAddressPart >= count($container)) {
                throw new InternalError("Invalid index while indexing into an array");
            }
            /*
             * @var int
             */
            $idx = $varAddressPart;

            $list = $container;
            if ($idx < count($list)) {

                array_splice($list,$idx,1);
            }
            while (count($list) > 0 && $list[count($list) - 1] == null) {
                array_pop($list);
            }
        } else if (is_object($container)) {
            $varAddressPart = strval($varAddressPart);
            unset($container[$varAddressPart]);
        } else if (get_class($container) === 'SandboxObject') {
            $varAddressPart = strval($varAddressPart);
            $container->set($varAddressPart, null);
        }

        return false;
    }

    /**
     * @param string $functionName
     * @param array $parameters
     */
    public function callFunction(string $functionName, array $parameters){
        $this->createNewStackLevel($functionName, -1);
        $parameterStack = &$this->currentParameters();
        $parameterStack = array_merge($parameterStack, $parameters);
    }

    public function compareVariables($aObj, $bObj, $commandID, bool $typeCheck):int {
        $aObj = Helper::resolve($this, $aObj, false);
        $bObj = Helper::resolve($this, $bObj, false);

        if (!$typeCheck && ($aObj == null || $bObj == null)) {
            if ($aObj == null && $bObj == null) {
                return 0;
            }
            return -1;
        }

        if (Helper::getTypeOrClass($aObj) != Helper::getTypeOrClass($bObj)) {
            if (!$typeCheck) throw new InternalError("Command '" . $commandID . "' compares arguments of different types");
        }

        if (method_exists($aObj,"compareTo")) { // if it has function compareTo use it!
            return $aObj->compareTo($bObj);
        } else { // if not try to use the helper one!
            return Helper::compare($aObj, $bObj);
        }
    }

    /**
     * @return string saved state
     */
    public function saveStateToString():string{
        /** @var array $savelist */
        $savelist = [];

        array_push($savelist,$this->codeFunctionNameStack);
        array_push($savelist,$this->codeLineStack);
        array_push($savelist,$this->localVariablesStack);
        array_push($savelist,$this->parametersStack);
        array_push($savelist,$this->persistentStorage);

        return json_encode($savelist);
    }

    public function getParameter(int $idx, int $stacklevel) {
        if (count($this->parametersStack) === 0 ||
            $stacklevel >= count($this->parametersStack) ||
            $idx >= count($this->parametersStack[$stacklevel])){
            return null;
        }
        return $this->parametersStack[$stacklevel][$idx];
    }
}
