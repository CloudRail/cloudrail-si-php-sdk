<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 15/11/17
 * Time: 14:04
 */

namespace CloudRail\ServiceCode;

use CloudRail\Error\InternalError;
use CloudRail\Error\UserError;
use CloudRail\Type\CloudRailError;
use \Exception as Exception;

//IMPORTING COMMANDS
use CloudRail\ServiceCode\Command\AwaitCodeRedirect;
use CloudRail\ServiceCode\Command\CallFunc;
use CloudRail\ServiceCode\Command\Create;
use CloudRail\ServiceCode\Command\Get;
use CloudRail\ServiceCode\Command\Set;
use CloudRail\ServiceCode\Command\Size;
use CloudRail\ServiceCode\Command\Push;
use CloudRail\ServiceCode\Command\ThrowError;
use CloudRail\ServiceCode\Command\JumpRel;
use CloudRail\ServiceCode\Command\ReturnFunction;
use CloudRail\ServiceCode\Command\StatsAdd;
use CloudRail\ServiceCode\Command\Conditional;
use CloudRail\ServiceCode\Command\GetMimeType;
use CloudRail\ServiceCode\Command\Delete;
use CloudRail\ServiceCode\Command\math\MathCombine;
use CloudRail\ServiceCode\Command\math\Floor;
use CloudRail\ServiceCode\Command\debug\Out;
use CloudRail\ServiceCode\Command\object\GetKeyArray;
use CloudRail\ServiceCode\Command\object\GetKeyValueArrays;
use CloudRail\ServiceCode\Command\string\Substring;
use CloudRail\ServiceCode\Command\string\Substr;
use CloudRail\ServiceCode\Command\string\LastIndexOf;
use CloudRail\ServiceCode\Command\string\IndexOf;
use CloudRail\ServiceCode\Command\string\ChunkSplit;
use CloudRail\ServiceCode\Command\string\Format;
use CloudRail\ServiceCode\Command\string\Split;
use CloudRail\ServiceCode\Command\string\StringTransform;
use CloudRail\ServiceCode\Command\string\Concat;
use CloudRail\ServiceCode\Command\string\URLEncode;
use CloudRail\ServiceCode\Command\stream\StreamToString;
use CloudRail\ServiceCode\Command\stream\StringToStream;
use CloudRail\ServiceCode\Command\stream\MakeLimitedStream;
use CloudRail\ServiceCode\Command\stream\DataToStream;
use CloudRail\ServiceCode\Command\stream\StreamToData;
use CloudRail\ServiceCode\Command\stream\MakeJoinedStream;
use CloudRail\ServiceCode\Command\http\RequestCall;
use CloudRail\ServiceCode\Command\json\Parse;
use CloudRail\ServiceCode\Command\json\Stringify;
use CloudRail\ServiceCode\Command\xml\XMLParse;
use CloudRail\ServiceCode\Command\xml\XMLStringify;
use CloudRail\ServiceCode\Command\crlist\DataToUint8;
use CloudRail\ServiceCode\Command\crlist\Uint8ToData;
use CloudRail\ServiceCode\Command\crlist\Sort;
use CloudRail\ServiceCode\Command\crlist\Uint8ToBase64;
use CloudRail\ServiceCode\Command\crypt\Hmac;
use CloudRail\ServiceCode\Command\crypt\Sign;
use CloudRail\ServiceCode\Command\crypt\Hash;


class Interpreter{
    /**
     * @var Sandbox
     */
    public $sandbox;

    public function __construct( $sandbox){
        $this->sandbox = $sandbox;
        Interpreter::instantiateCommandList();
        Interpreter::instantiateCommandIdentifiers();
    }

    public function callFunctionSync(string $functionName, array &$parameters){

        //For each nested function create a stack level
        $this->sandbox->createNewStackLevel($functionName, 0);

        //Adding the function parameters to the stack level
        $parametersStack = &$this->sandbox->currentParameters();
        $this->sandbox->setCurrentParameters(array_merge($parametersStack,$parameters));

        //If there is no function with that name throw
        if($this->sandbox->currentFunctionCode() === null){
            $errorMessage = "Service code error: function '" . $functionName . "' not found";
            throw new InternalError($errorMessage);
        }
        //run the function
        $this->runSync(); //cant return void
    }


    public function runSync(){

        try {

            //While the current line is less than the total lines of the current function AND
            // the current line is greater than 0, iterate
            while ($this->sandbox->currentServiceCodeLine() < count($this->sandbox->currentFunctionCode()) && $this->sandbox->currentServiceCodeLine() >= 0) {

                //Get the current line command
                $command = $this->sandbox->currentFunctionCode()[$this->sandbox->currentServiceCodeLine()];

                //check if the command is valid
                if (Interpreter::$COMMANDS[$command[0]] == null || !isset(Interpreter::$COMMANDS[$command[0]])) {
                    throw new InternalError("Unknown command: ".$command[0]);
                }

                $commandParameters = Interpreter::decodeCommandParameters($command);

                $commandRet = Interpreter::$COMMANDS[$command[0]]->execute($this->sandbox, $commandParameters);

                if ($commandRet != null && $commandRet instanceof Promise) {
                    throw new InternalError("Attempt to synchronously execute an asynchronous command");
                }

                if ($this->sandbox->thrownError != null) {
                    return;
                }

                $this->sandbox->incrementCurrentServiceCodeLine(1);

                while (($this->sandbox->currentServiceCodeLine() >= count($this->sandbox->currentFunctionCode()) ||
                        $this->sandbox->currentServiceCodeLine() < 0)
                    && count($this->sandbox->codeFunctionNameStack) > 1) {
                    $this->sandbox->returnFromFunction();
                }

            }
        } catch (Exception $e) {
            if ($e instanceof UserError) throw $e;

            $errorMessage = "Service code error in function " .
                $this->sandbox->currentFunctionName() .
                " at line " .
                $this->sandbox->currentServiceCodeLine() .
                " with message: " .
                $e->getMessage();

            throw new Exception($errorMessage);
        }
    }

    /**
     * @return CloudRailError
     */
    public function getError() {
        return $this->sandbox->thrownError;
    }

    public function getParameter(int $idx) {
        return $this->sandbox->getParameter($idx, 0);
    }

    public static function decodeCommandParameters(array $command):array{
        $commandParameters = array_slice($command,1,count($command)-1);
        for ($i = 0; $i < count($commandParameters); $i++) {
            if (is_string($commandParameters[$i])) {
                if (strpos($commandParameters[$i],"$") === 0) {
                $commandParameters[$i] = new VarAddress(substr($commandParameters[$i],1));
                } else if (strpos($commandParameters[$i],"\\$") === 0) {
                    $commandParameters[$i] = substr($commandParameters[$i],1);
                }
            }
        }

        return $commandParameters;
    }


    /**
     * @return string The state containing the necessary values to restore auth previous state
     */
    public function saveAsString():string{
        return json_encode($this->sandbox->persistentStorage);
    }

    /**
     * @param string $state The state containing the necessary values to restore auth previous state. See saveAsString().
     */
    public function loadAsString(string $state){
        $this->sandbox->persistentStorage = json_decode($state,true);
    }

    //COMMAND LIST

    /***
     * @global array<Command> $COMMAND_LIST with the instantiation with all the Command
     */
    private static $COMMAND_LIST;

    private static function instantiateCommandList(){
        if (is_null(Interpreter::$COMMAND_LIST) || !isset(Interpreter::$COMMAND_LIST)){
            Interpreter::$COMMAND_LIST = [
                new CallFunc(),
                new Create(),
                new Delete(),
                new Get(),
                new JumpRel(),
                new Push(),
                new ReturnFunction(),
                new Set(),
                new Size(),
                new ThrowError(),
                new Uint8ToBase64(),
                new Hash("hash.md5", "md5"),
                new Hash("hash.sha1", "sha1"),
                new Hash("hash.sha256", "sha256"),
                new Hmac("crypt.hmac.sha1", "sha1"),
                new Hmac("crypt.hmac.sha256", "sha256"),
                new Sign("crypt.rsa.sha256", "RSA-SHA256"),
                new StreamToString(),
                new StringToStream(),
                new MakeJoinedStream(),
                new MakeLimitedStream(),
                new RequestCall(),
                new Out(),
                new AwaitCodeRedirect(),
                new GetMimeType(),
                new Conditional("if==than",false),
                new Conditional("if>=than",true),
                new Conditional("if>than",true),
                new Conditional("if<=than",true),
                new Conditional("if<than", true),
                new Conditional("if!=than",false),
                new Parse(),
                new Stringify(),
                new MathCombine("math.add"),
                new MathCombine("math.multiply"),
                new MathCombine("math.max"),
                new MathCombine("math.min"),
                new MathCombine("math.subtract"),
                new MathCombine("math.divide"),
                new Floor(),
                new GetKeyArray(),
                new GetKeyValueArrays(),
                new Concat(),
                new Format(),
                new IndexOf(),
                new LastIndexOf(),
                new Split(),
                new ChunkSplit(),
                new Substr(),
                new Substring(),
                new StringTransform("string.lowerCase", function(string $str){ return strtolower($str);}),
                new StringTransform("string.upperCase", function(string $str){ return strtoupper($str);}),
                new StringTransform("string.urlDecode", function(string $str){ return urldecode($str);}),
                new StringTransform("string.base64encode", function(string $str){ return base64_encode($str);}),
                new StringTransform("string.base64decode", function(string $str){ return base64_decode($str,true);}),
                new URLEncode(),
                new StreamToData(),
                new DataToStream(),
                new XMLParse(),
                new XMLStringify(),
                new Uint8ToData(),
                new DataToUint8(),
                new Sort(),
                new StatsAdd()
            ];
        }
    }

    /**
     * @var array List with the code of all Command available
     */
    private static $COMMANDS;

    private static function instantiateCommandIdentifiers(){
        if (is_null(Interpreter::$COMMANDS) || !isset(Interpreter::$COMMANDS)){
            Interpreter::$COMMANDS = [];
            foreach (Interpreter::$COMMAND_LIST as $key => $value) {
                /**
                 *  @var $value Command
                 */
                Interpreter::$COMMANDS[$value->getIdentifier()] = $value;
            }
        }
    }
}
