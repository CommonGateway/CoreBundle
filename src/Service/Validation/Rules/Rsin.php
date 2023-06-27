<?php

namespace CommonGateway\CoreBundle\Service\Validation\Rules;

use Respect\Validation\Rules\AbstractRule;

use function ctype_digit;
use function mb_strlen;

/**
 * Copy from BSN Rule.
 *
 * @author Wilco Louwerse <wilco@conduction.nl>
 */
final class Rsin extends AbstractRule
{
    
    /**
     * {@inheritDoc}
     *
     * @param mixed $input The input.
     *
     * @return bool True if input is a valid rsin. False if not.
     */
    public function validate($input): bool
    {
        if (ctype_digit($input) === false) {
            return false;
        }

        if (mb_strlen($input) !== 9) {
            return false;
        }

        $rsinLength = 9;
        $sum        = (-1 * $input[8]);
        for ($i = $rsinLength; $i > 1; $i--) {
            $sum += ($i * $input[($rsinLength - $i)]);
        }

        return $sum !== 0 && ($sum % 11) === 0;

    }//end validate()
}//end class
