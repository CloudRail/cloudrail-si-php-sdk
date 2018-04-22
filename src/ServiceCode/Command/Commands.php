<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 10/01/18
 * Time: 15:44
 */

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