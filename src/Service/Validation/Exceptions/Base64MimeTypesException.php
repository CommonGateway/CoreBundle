<?php

namespace CommonGateway\CoreBundle\Service\Validation\Exceptions;

use Respect\Validation\Exceptions\ValidationException;

/**
 * @author Wilco Louwerse <wilco@conduction.nl>
 */
final class Base64MimeTypesException extends ValidationException
{
    
    /**
     * https://respect-validation.readthedocs.io/en/latest/custom-rules/#custom-rules
     * {@inheritDoc}
     *
     * @var string[][]
     */
    protected $defaultTemplates = [
        self::MODE_DEFAULT  => [self::STANDARD => '{{name}} must be one of the following mime types: {{allowedTypes}}'],
        self::MODE_NEGATIVE => [self::STANDARD => '{{name}} must not be one of the following mime types: {{allowedTypes}}'],
    ];
}//end class
