<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 17/01/18
 * Time: 01:42
 */

namespace CloudRail\ServiceCode\Command;
use CloudRail\ServiceCode\Command;
use CloudRail\ServiceCode\Sandbox;
use CloudRail\ServiceCode\Helper;

class Conditional implements Command {

    /**
     * @var string
     */
    private $identifier;

    /**
     * @var bool
     */
    private $typeCheck;

    public function __construct(string $identifier, bool $typeCheck) {
        $this->identifier = $identifier;
        $this->typeCheck = $typeCheck;
    }

    public function getIdentifier():string {
        return $this->identifier;
    }

    public function execute(Sandbox &$environment, array $parameters) {
        Helper::assert(count($parameters) == 3);

        $aObj = $parameters[0];
        $bObj = $parameters[1];

        $relativeEndPos = Helper::resolve($environment, $parameters[2]);
        Helper::assert(Helper::isNumber($relativeEndPos));

        $compare = $environment->compareVariables($aObj, $bObj, $this->getIdentifier(), $this->typeCheck);

        $result = false;
        switch ($this->getIdentifier()) {
            case "if==than":
                if (($compare == 0)) $result = true;
                break;
            case "if>=than":
                if (($compare >= 0)) $result = true;
                break;
            case "if>than":
                if (($compare > 0)) $result = true;
                break;
            case "if<=than":
                if (($compare <= 0)) $result = true;
                break;
            case "if<than":
                if (($compare < 0)) $result = true;
                break;
            case "if!=than":
                if (($compare != 0)) $result = true;
                break;
            default:
                $result = null;
        }

        if ( !is_null($result) && !$result) $environment->incrementCurrentServiceCodeLine($relativeEndPos);

    }
}