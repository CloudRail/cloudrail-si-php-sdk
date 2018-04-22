<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 18/12/17
 * Time: 10:51
 */

namespace CloudRail\ServiceCode\Command\http;

use CloudRail\ServiceCode\Command;
use CloudRail\ServiceCode\Sandbox;
use CloudRail\ServiceCode\Helper;
use CloudRail\ServiceCode\VarAddress;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Client;

class RequestCall implements Command {

    public function getIdentifier(): string
    {
        return "http.requestCall";
    }

    public function execute(Sandbox &$environment, array $parameters)
    {
        Helper::assert(count($parameters)== 2 &&
            $parameters[0] instanceof VarAddress &&
            $parameters[1] instanceof VarAddress);

        $resultVar = $parameters[0];
        $options = Helper::resolve($environment, $parameters[1]);

        $url = $options["url"]; //Request URL
        $method = $options["method"]; //Request METHOD

        //Request Headers
        $requestHeaders = [];
        if(array_key_exists("requestHeaders",$options)){
            $requestHeaders = $options["requestHeaders"];
        }

        //Request Body
        if(array_key_exists("requestBody",$options)){
            $requestBody = $options["requestBody"];
            $requestBodyCropped = fopen('php://memory','r+');
            $copied = stream_copy_to_stream($requestBody,$requestBodyCropped);
            rewind($requestBodyCropped);
        } else {
            $requestBody = null;
            $requestBodyCropped = null;
        }

        //Assert essential parameters.
        Helper::assert(Helper::isString($url) &&
            Helper::isString($method));
        Helper::assert($requestHeaders == null ||
            Helper::isArray($requestHeaders));
        Helper::assert($requestBodyCropped == null ||
            Helper::isStream($requestBodyCropped));

        if ($method === "POST" &&
            !array_key_exists("Content-Type",$requestHeaders) &&
            array_key_exists("Content-Length",$requestHeaders)){
            $requestHeaders["Content-Type"] = "application/octet-stream";
        }

        //Creating Client and Request objects
        $client = new Client(['base_uri' => $url, 'http_errors' => false,]);
        $request = new Request($method, $url, $requestHeaders, $requestBodyCropped);

        //Sending request
        $responseRaw = $client->send($request, [/* array of options*/]);

        //Workaround responseheaders format
        $responseHeaders = [];
        foreach($responseRaw->getHeaders() as $key => $value){
            if (count($value) == 1){
                $responseHeaders[$key] = $value[0];
            } else {
                $responseHeaders[$key] = join(", ", $value);
            }
        }

        //if there is no error, get the code, header, body, message
        $response = [
            "code" => $responseRaw->getStatusCode(),
            "reason" => $responseRaw->getReasonPhrase(),
            "responseHeaders" => $responseHeaders,
            "responseBody" => $responseRaw->getBody()->detach()
        ];

        $environment->setVariable($resultVar, $response);
        return $response;
    }
}
