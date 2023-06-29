<?php

namespace CommonGateway\CoreBundle\Service\Validation\Exceptions;

use Respect\Validation\Exceptions\ValidationException;

/**
 * @author Wilco Louwerse <wilco@conduction.nl>
 */
final class Base64StringException extends ValidationException
{

    /**
     * https://respect-validation.readthedocs.io/en/latest/custom-rules/#custom-rules
     * {@inheritDoc}
     *
     * @var string[][]
     */
    protected $defaultTemplates = [
        self::MODE_DEFAULT  => [self::STANDARD => '{{name}} must be a base64-encoded string. {{exceptionMessage}}'],
        self::MODE_NEGATIVE => [self::STANDARD => '{{name}} must not be a base64-encoded string. {{exceptionMessage}}'],
    ];
}//end class
