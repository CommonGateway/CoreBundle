<?php

namespace CommonGateway\CoreBundle\Service\Validation\Exceptions;

use Respect\Validation\Exceptions\ValidationException;

/**
 * @author Wilco Louwerse <wilco@conduction.nl>
 */
final class FilenameException extends ValidationException
{

    /**
     * https://respect-validation.readthedocs.io/en/latest/custom-rules/#custom-rules
     * {@inheritDoc}
     *
     * @var string[][]
     */
    protected $defaultTemplates = [
        self::MODE_DEFAULT  => [self::STANDARD => '{{name}} must be a filename. ({{name}} must be a string and match the following regex: {{regex}} )'],
        self::MODE_NEGATIVE => [self::STANDARD => '{{name}} must not be a filename. ({{name}} must not be string and not match the following regex: {{regex}} )'],
    ];
}//end class
