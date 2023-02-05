<?php

namespace CommonGateway\CoreBundle\ActionHandler;

/**
 * This interface is used to auto detect action handlers for the gateway.
 */
interface ActionHandlerInterface
{
    /**
     * All action handles should be able to return there configuration requirements and options as a (json-schema)[https://json-schema.org/understanding-json-schema/reference/object.html] object
     */
    public function getConfiguration();

    /**
     * All action handlers should implement the run function that is called when the action is triggerd
     * 
     * @param array $data
     * @param array $configuration
     * @return mixed
     */
    public function run(array $data, array $configuration);
}
