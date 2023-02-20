<?php

namespace CommonGateway\CoreBundle\ActionHandler;

/**
 * This interface is used to auto detect action handlers for the gateway.
 */
interface ActionHandlerInterface
{
    /**
     * All action handles should be able to return there configuration requirements and options as a (json-schema)[https://json-schema.org/understanding-json-schema/reference/object.html] object.
     *
     * @return array a [json-schema](https://json-schema.org/) that this  action should comply to
     */
    public function getConfiguration();

    /**
     * All action handlers should implement the run function that is called when the action is triggerd.
     *
     * @param array $data          The data passed to the runnen
     * @param array $configuration The configuration of the used action
     *
     * @return mixed Any valid result
     */
    public function run(array $data, array $configuration);
}//end interface
