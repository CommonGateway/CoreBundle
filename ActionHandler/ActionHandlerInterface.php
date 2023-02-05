<?php

namespace CommonGateway\CoreBundle\ActionHandler;

interface ActionHandlerInterface
{

    /*
     * All action handles should be able to return there configuration requirements and options as a (json-schema)[https://json-schema.org/understanding-json-schema/reference/object.html] object
     */
    public function getConfiguration();

    /*
     * All action handlers should implement the run function that is called when the action is triggerd
     */
    public function run(array $data, array $configuration);
}
