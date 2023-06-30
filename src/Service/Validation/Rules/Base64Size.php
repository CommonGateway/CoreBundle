<?php

namespace CommonGateway\CoreBundle\Service\Validation\Rules;

use Exception;
use Respect\Validation\Rules\AbstractRule;

/**
 * @author Wilco Louwerse <wilco@conduction.nl>
 */
final class Base64Size extends AbstractRule
{
    // Todo: Getter setter.

    /**
     * The minimum size.
     *
     * @var integer|null
     */
    private ?int $minSize;

    // Todo: Getter setter.

    /**
     * The maximum size.
     *
     * @var integer|null
     */
    private ?int $maxSize;

    /**
     * It is recommended to use the Base64String validation rule before using this rule.
     *
     * @param int|null $minSize Minimum allowed size.
     * @param int|null $maxSize Maximum allowed size.
     */
    public function __construct(?int $minSize, ?int $maxSize)
    {
        $this->minSize = $minSize;
        $this->maxSize = $maxSize;

    }//end __construct()

    /**
     * @inheritDoc
     *
     * @param  mixed $input The input.
     * @return bool True if the size is allowed (in min/max range).
     */
    public function validate($input): bool
    {
        // Todo: We could just move and merge all validations from here to the Base64String rule?
        $size = $this->getBase64Size($input);

        return $this->isValidSize($size);

    }//end validate()

    /**
     * Gets the memory size of a base64 file in bytes.
     *
     * @param mixed $base64 A base64 string.
     *
     * @return Exception|float|int
     */
    private function getBase64Size($base64)
    {
        // Return memory size in B (KB, MB).
        try {
            $size_in_bytes = (int) (strlen(rtrim($base64, '=')) * 3 / 4);
            // Be careful when changing this!
            // $size_in_kb = $size_in_bytes / 1024;
            // $size_in_mb = $size_in_kb / 1024;
            return $size_in_bytes;
        } catch (Exception $e) {
            return $e;
        }

    }//end getBase64Size()

    /**
     * Checks if given size is a valid size compared to the min & max size.
     *
     * @param int $size The size to check.
     *
     * @return bool True if size is allowed, false if not.
     */
    private function isValidSize(int $size): bool
    {
        if ($this->minSize !== null && $this->maxSize !== null) {
            return $size >= $this->minSize && $size <= $this->maxSize;
        }

        if ($this->minSize !== null) {
            return $size >= $this->minSize;
        }

        return $size <= $this->maxSize;

    }//end isValidSize()
}//end class
