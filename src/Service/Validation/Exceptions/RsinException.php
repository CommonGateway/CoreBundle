<?php

namespace CommonGateway\CoreBundle\Service\Validation\Exceptions;

use Respect\Validation\Exceptions\ValidationException;

/**
 * Copy from BSN Exception.
 *
 * @author Wilco Louwerse <wilco@conduction.nl>
 */
final class RsinException extends ValidationException
{
    
    /**
     * https://respect-validation.readthedocs.io/en/latest/custom-rules/#custom-rules
     * {@inheritDoc}
     *
     * @var string[][]
     */
    protected $defaultTemplates = [
        self::MODE_DEFAULT  => [self::STANDARD => '{{name}} must be a RSIN'],
        self::MODE_NEGATIVE => [self::STANDARD => '{{name}} must not be a RSIN'],
    ];
}//end class
