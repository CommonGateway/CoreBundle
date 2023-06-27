<?php

namespace CommonGateway\CoreBundle\Service\Validation\Rules;

use Exception;
use JWadhams\JsonLogic as jsonLogicLib;
use Respect\Validation\Rules\AbstractRule;

/**
 * @author Wilco Louwerse <wilco@conduction.nl>
 */
final class JsonLogic extends AbstractRule
{
    // Todo: Getter setter.

    /**
     * @var mixed
     */
    private $jsonLogic;

    /**
     * @param mixed $jsonLogic This should be a string or an array. When using a string {{input}} can be used to add the input anywhere in the string, You might need to surround this with quotation marks like this: "{{input}}"
     */
    public function __construct($jsonLogic)
    {
        $this->jsonLogic = $jsonLogic;

    }//end __construct()

    /**
     * @inheritDoc
     *
     * @param $input
     *
     * @throws Exception
     *
     * @return bool True if jsonLogic rules are met. False if not.
     */
    public function validate($input): bool
    {
        if (is_string($this->jsonLogic) === true) {
            // Todo: what if we can't cast $input to string? maybe use try catch?
            $this->jsonLogic = str_replace('{{input}}', (string) $input, $this->jsonLogic);
            $this->jsonLogic = json_decode($this->jsonLogic, true);
            $input           = null;
        }

        if (is_array($this->jsonLogic) === true && empty(jsonLogicLib::apply($this->jsonLogic, $input)) === false) {
            return true;
        }

        return false;

    }//end validate()

    /*
     * Examples of how to use this Rule:
     *
     * With $jsonLogic as a string, in this example $input should be equal to "apples"
     * new CommonGateway\CoreBundle\Service\Validation\Rules\JsonLogic('{"==":["apples", "{{input}}"]}');
     *
     * With $jsonLogic as an array, in this example $input should be an array that has the key "int" with the value 12
     * new CommonGateway\CoreBundle\Service\Validation\Rules\JsonLogic(["==" => [ ["var" => "int"], 12 ]);
     * Input like this wil result in true:
     * {
     *   "test": "someRandomValue"
     *   "int": 12
     * }
     * Input like this wil result in false:
     * {
     *   "int": 11
     * }
     */
}//end class
