<?php

namespace CommonGateway\Corebundle\ActionHandler;

interface ActionHandlerInterface
{
    public function getConfiguration();

    public function run(array $data, array $configuration);
}
