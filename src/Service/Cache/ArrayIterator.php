<?php
namespace CommonGateway\CoreBundle\Service\Cache;

/**
 * Extension of the standard Array Iterator with an toArray function.
 *
 * @Author Ruben van der Linde <ruben@conduction.nl>, Wilco Louwerse <wilco@conduction.nl>, Robert Zondervan <robert@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category DataStore
 */
class ArrayIterator extends \ArrayIterator
{
    /**
     * Returns the array value of the iterator.
     *
     * @return array
     */
    public function toArray(): array
    {
        return iterator_to_array($this);

    }//end toArray()
}//end class
