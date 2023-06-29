<?php

namespace CommonGateway\CoreBundle\Service\Validation\Exceptions;

use Respect\Validation\Exceptions\ValidationException;

/**
 * @author Wilco Louwerse <wilco@conduction.nl>
 */
final class Base64ExtensionException extends ValidationException
{

    /**
     * https://respect-validation.readthedocs.io/en/latest/custom-rules/#custom-rules
     * {@inheritDoc}
     *
     * @var string[][]
     */
    protected $defaultTemplates = [
        self::MODE_DEFAULT  => [self::STANDARD => '{{name}} extension ({{extension}}) should match mime type ({{mime_type}})'],
        self::MODE_NEGATIVE => [self::STANDARD => '{{name}} extension ({{extension}}) should not match mime type ({{mime_type}})'],
    ];
}//end class
