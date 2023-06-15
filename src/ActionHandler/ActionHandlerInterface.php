<?php

namespace CommonGateway\CoreBundle\ActionHandler;

interface ActionHandlerInterface
{
    
    /**
     * Gest the action configuration.
     */
    public function getConfiguration();
    

    /**
     * Runs the action.
     */
    public function run(array $data, array $configuration);

}//end class
