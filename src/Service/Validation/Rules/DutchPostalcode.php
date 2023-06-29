<?php

namespace CommonGateway\CoreBundle\Service\Validation\Rules;

use Respect\Validation\Rules\AbstractRule;

/**
 * @author Wilco Louwerse <wilco@conduction.nl>
 */
final class DutchPostalcode extends AbstractRule
{
    /**
     * @inheritDoc
     *
     * @param mixed $input The input.
     *
     * @return bool True if the input is a valid Dutch PC4 postal code. False if not.
     */
    public function validate($input): bool
    {
        $dutchPc4List = $this->getDutchPC4List();

        foreach ($dutchPc4List as $dutchPc4) {
            if ($dutchPc4 === $input) {
                return true;
            }
        }

        return false;

    }//end validate()

    /**
     * Gets the list of Dutch PC4 postal codes.
     *
     * @return array The list of Dutch PC4 postal codes.
     */
    private function getDutchPC4List(): array
    {
        $file = fopen(dirname(__FILE__).'../../../csv/dutch_pc4.csv', 'r');

        $i            = 0;
        $dutchPc4List = [];
        while (!feof($file)) {
            $line = fgetcsv($file);
            if ($i === 0) {
                $i++;
                continue;
            }

            if (isset($line[1]) === true) {
                $dutchPc4List[] = $line[1];
            }

            $i++;
        }

        return $dutchPc4List;

    }//end getDutchPC4List()
}//end class
