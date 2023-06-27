<?php

namespace CommonGateway\CoreBundle\Service\Validation\Rules;

use Respect\Validation\Rules\AbstractRule;
use Respect\Validation\Validator;

/**
 * @author Wilco Louwerse <wilco@conduction.nl>
 */
final class Filename extends AbstractRule
{
    // Todo: Getter setter.

    /**
     * @var string
     */
    private string $regex;

    /**
     * @param string|null $regex The regex used to validate if a filename is a valid filename.
     */
    public function __construct(?string $regex = '/^[\w,\s()~!@#$%^&*_+\-=\[\]{};\'.]{1,255}\.[A-Za-z0-9]{1,5}$/')
    // public function __construct(?string $regex = '/^[^\\/:*?\"<>|]{1,255}\.[A-Za-z0-9]{1,5}$/') #todo:
    {
        $this->regex = $regex;

    }//end __construct()

    /**
     * @inheritDoc
     *
     * @param mixed $input The input.
     *
     * @return bool True if the input is a valid filename. False if not.
     */
    public function validate($input): bool
    {
        if (Validator::stringType()->validate($input) === true
            && Validator::regex($this->regex)->validate($input) === true
        ) {
            return true;
        }

        return false;

    }//end validate()
}//end class
