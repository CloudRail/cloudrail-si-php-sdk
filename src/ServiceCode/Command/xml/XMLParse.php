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

use \DOMDocument;
use \DOMNode;
use \DOMNamedNodeMap;

class XMLParse implements Command{

    public function getIdentifier(): string {
        return "xml.parse";
    }

    public function execute(Sandbox &$environment, array $parameters) {
        Helper::assert(count($parameters) == 2 &&
            $parameters[0] instanceof VarAddress);

        $resultVar = $parameters[0];
        $input = Helper::resolve($environment, $parameters[1]);

        Helper::assert(Helper::isStream($input) ||
            Helper::isString($input));


        $xmlstring = null;

        if (Helper::isStream($input)) {
            $xmlstring = stream_get_contents($input);
            Helper::isString($xmlstring);
        } else {
            $xmlstring = $input;
        }

        $parser = new DOMDocument();
        $parser->loadXML($xmlstring);

        $obj = XMLParse::parseXML($parser);
        Helper::isArray($obj);

        $environment->setVariable($resultVar, $obj);
    }


    private static function parseXML(DOMNode $element):array{

        //If is root skip and go to single child
        if ($element->nodeType == XML_DOCUMENT_NODE){
            foreach ($element->childNodes as $childNode){
                if (!($childNode->nodeType == XML_COMMENT_NODE) &&
                    !($childNode->nodeType == XML_TEXT_NODE)){
                    return self::parseXML($childNode);
                }
            }
        }

        //Initializing
        $parsedElement = [];
        $parsedElement["attributes"] = [];
        $parsedElement["children"] = [];
        $parsedElement["text"] = "";
        $parsedElement["name"] = "";

        //Name
        $parsedElement["name"] = $element->localName;

        //filling attributes
        if ($element->hasAttributes()) {
            $parsedElement["attributes"] = XMLParse::parseXMLAttributes($element->attributes);
        }


        //If there are nested nodes, recursive call (exclude TEXT and COMMENT nodes)
        /** @var $childNode DOMNode  */
        foreach ($element->childNodes as $childNode){
            if (!($childNode->nodeType == XML_COMMENT_NODE) &&
                !($childNode->nodeType == XML_TEXT_NODE)){
                array_push($parsedElement["children"],self::parseXML($childNode));
            }
        }

        //If there is no children, it must have a text
        if (count($parsedElement["children"]) == 0){
            $parsedElement["text"] = $element->textContent;
        }

        return $parsedElement;
    }

    /**
     * @param DOMNamedNodeMap $attributesMap
     * @return array
     */
    private static function parseXMLAttributes(DOMNamedNodeMap $attributesMap){
        $attributesArray = [];
        foreach ( $attributesMap as $attribute ) {
            $name = $attribute->name;
            $value = $attribute->value;
            $attributesArray[$name] = $value;
        }
        return $attributesArray;
    }
}