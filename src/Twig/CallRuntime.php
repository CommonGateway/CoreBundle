<?php

namespace CommonGateway\CoreBundle\Twig;

use Adbar\Dot;
use CommonGateway\CoreBundle\Service\CallService;
use CommonGateway\CoreBundle\Service\GatewayResourceService;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Extension\RuntimeExtensionInterface;

class CallRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly CallService $callService,
        private readonly GatewayResourceService $resourceService
    ) {

    }//end __construct()

    /**
     * Call source of given id or reference
     *
     * @param array $array The array to turn into a dot array.
     *
     * @return array The dot aray.
     */
    public function call(string $sourceId, string $endpoint, string $method = 'GET', array $configuration = []): array
    {
        $source = $this->resourceService->getSource($sourceId, 'common-gateway/zgw-to-zds-bundle');

        $response = $this->callService->call($source, $endpoint, $method, $configuration);
        return $this->callService->decodeResponse($source, $response);

    }//end call()
}//end class
