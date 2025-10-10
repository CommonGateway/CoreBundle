<?php

namespace CommonGateway\CoreBundle\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CallExtension extends AbstractExtension
{


    public function getFunctions()
    {
        return [
            new TwigFunction('call', [CallRuntime::class, 'call']),
        ];

    }//end getFunctions()


}//end class
