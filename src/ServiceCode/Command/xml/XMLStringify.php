<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 20/02/18
 * Time: 18:23
 */

namespace CloudRail\ServiceCode\Command\xml;

use CloudRail\ServiceCode\Command;
use CloudRail\ServiceCode\Sandbox;
use CloudRail\ServiceCode\Helper;
use CloudRail\ServiceCode\VarAddress;

class XMLStringify implements Command {

    public function getIdentifier(): string {
        return "xml.stringify";
    }

    public function execute(Sandbox &$environment, array $parameters)
    {
        Helper::assert(count($parameters) === 2 &&
            $parameters[0] instanceof VarAddress);

        $resultVar = $parameters[0];
        $input = Helper::resolve($environment, $parameters[1]);

        Helper::assert(Helper::isArray($input));

        $xmlString =  XMLStringify::arrayToXMLString($input);     //   $xmlElement->asXML();
        $environment->setVariable($resultVar, $xmlString);
    }

    private static function arrayToXMLString(array $source):string{

        //OPEN BEGIN TAG
        $result = "<" . $source["name"];

        //Fill attributes
        if (array_key_exists("attributes", $source) && count($source["attributes"]) > 0){
            foreach ($source["attributes"] as $key => $value){
                if (Helper::isString($value))  $result .= " ". "$key=\"$value\"";
                else  $result .= " ". "$key=$value";
            }
        }

        //CLOSE BEGIN TAG
        $result .=">";

        //FILLING THE TAG - Recursive
        if(array_key_exists("children", $source) && $source["children"] > 0 ){
            foreach ($source["children"] as $key => $value){
                $resolvedChild = XMLStringify::arrayToXMLString($value);
                $result .= $resolvedChild;
            }
        } else {
                $result .= $source["text"];
        }

        //APPEND END TAG
        $result .= "</". $source["name"] .">";
        return $result;
    }
}