<?php

namespace CommonGateway\CoreBundle\Service;

use Symfony\Component\Security\Core\Security;

/**
 * This service manages the setting of read or unread for a resource, internal or external.
 *
 * @author Ruben van der Linde <ruben@conduction.nl>, Robert Zondervan <robert@conduction.nl>
 */
class ReadUnreadService
{
    /**
     * @var Security
     */
    private Security $security;

    public function __construct(Security $security) {
        $this->security = $security;
    }

    public function getIdentifier(array $data): string
    {
        $endpoint = $data['endpoint'];
        $path     = $data['path'];

        if($endpoint->getProxy() !== null) {
            return rtrim($endpoint->getProxy()->getLocation(), '/').'/'.implode('/', $path);
        } else {
            return end($path);
        }
    }
    public function readHandler(array $data, array $config): array
    {
        $identifier = $this->getIdentifier($data);
        $userId     = $this->security->getUser()->getUserId();

        return $data;
    }
}