<?php

namespace CommonGateway\CoreBundle\Service\Validation\Exceptions;

use Respect\Validation\Exceptions\NestedValidationException;

/**
 * @author Wilco Louwerse <wilco@conduction.nl>
 */
final class Base64SizeException extends NestedValidationException
{
    public const BOTH    = 'both';
    public const LOWER   = 'lower';
    public const GREATER = 'greater';

    /**
     * https://respect-validation.readthedocs.io/en/latest/custom-rules/#custom-rules
     * {@inheritDoc}
     *
     * @var string[][]
     */
    protected $defaultTemplates = [
        self::MODE_DEFAULT  => [
            self::BOTH    => '{{name}} must be a file size between {{minSize}} bytes and {{maxSize}} bytes',
            self::LOWER   => '{{name}} is to small, file size must be greater than {{minSize}} bytes',
            self::GREATER => '{{name}} is to big, file size must be lower than {{maxSize}} bytes',
        ],
        self::MODE_NEGATIVE => [
            self::BOTH    => '{{name}} must not be a file size between {{minSize}} bytes and {{maxSize}} bytes',
            self::LOWER   => '{{name}} is to big, file size must not be greater than {{minSize}} bytes',
            self::GREATER => '{{name}} is to small, file size must not be lower than {{maxSize}} bytes',
        ],
    ];

    /**
     * {@inheritDoc}
     *
     * @return string both, greater or lower.
     */
    protected function chooseTemplate(): string
    {
        if (empty($this->getParam('minSize')) === true) {
            return self::GREATER;
        }

        if (empty($this->getParam('maxSize')) === true) {
            return self::LOWER;
        }

        return self::BOTH;

    }//end chooseTemplate()
}//end class
