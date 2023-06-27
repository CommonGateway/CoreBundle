<?php

namespace CommonGateway\CoreBundle\Service\Validation\Rules;

use Exception;
use Respect\Validation\Rules\AbstractRule;
use Respect\Validation\Validatable;

/**
 * @author Wilco Louwerse <wilco@conduction.nl>
 */
final class Base64MimeTypes extends AbstractRule
{

    /**
     * @var array
     */
    private array $allowedTypes;

    /**
     * Used for Base64StringException.
     *
     * @var string|null
     */
    private ?string $exceptionMessage = null;

    /**
     * It is recommended to use the Base64String validation rule before using this rule.
     *
     * @param array $allowedTypes
     */
    public function __construct(array $allowedTypes)
    {
        $this->allowedTypes = $allowedTypes;

    }//end __construct()

    /**
     * @inheritDoc
     *
     * @param mixed $input The input.
     *
     * @return bool True if mime type is allowed, false if it is not allowed or if we catched an exception.
     */
    public function validate($input): bool
    {
        // Todo: We could just move and merge all validations from here to the Base64String rule?
        // Get base64 from this input string
        $exploded_input = explode(',', $input);
        $base64         = end($exploded_input);

        try {
            // Use the base64 to open a file and get the mimeType
            $fileData = \Safe\base64_decode($base64);
            $f        = finfo_open();
            $mimeType = finfo_buffer($f, $fileData, FILEINFO_MIME_TYPE);
            finfo_close($f);
        } catch (Exception $exception) {
            $this->setExceptionMessage($exception->getMessage());

            return false;
        }

        return in_array($mimeType, $this->allowedTypes);

    }//end validate()

    /**
     * @return array|null
     */
    public function getAllowedTypes(): ?array
    {
        return $this->allowedTypes;

    }//end getAllowedTypes()

    /**
     * @param array $allowedTypes
     *
     * @return Validatable
     */
    public function setAllowedTypes(array $allowedTypes): Validatable
    {
        $this->allowedTypes = $allowedTypes;

        return $this;

    }//end setAllowedTypes()

    /**
     * @return string|null
     */
    public function getExceptionMessage(): ?string
    {
        return $this->exceptionMessage;

    }//end getExceptionMessage()

    /**
     * @param string $exceptionMessage
     *
     * @return Validatable
     */
    public function setExceptionMessage(string $exceptionMessage): Validatable
    {
        $this->exceptionMessage = $exceptionMessage;

        return $this;

    }//end setExceptionMessage()
}//end class
