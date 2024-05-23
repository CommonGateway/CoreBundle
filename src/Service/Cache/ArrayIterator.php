<?php

namespace CommonGateway\CoreBundle\Service\Cache;

class ArrayIterator extends \ArrayIterator
{
    public function toArray(): array
    {
        return iterator_to_array($this);
    }
}
