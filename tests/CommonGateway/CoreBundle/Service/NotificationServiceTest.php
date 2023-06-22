<?php

namespace App\Tests\CommonGateway\CoreBundle\Service;

use App\Entity\Entity;
use App\Entity\Gateway as Source;
use App\Entity\Synchronization;
use App\Service\SynchronizationService;
use CommonGateway\CoreBundle\Service\GatewayResourceService;
use CommonGateway\CoreBundle\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;

class NotificationServiceTest extends TestCase
{
    private $entityManager;
    private $logger;
    private $syncService;
    private $resourceService;
    private $notificationService;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->syncService = $this->createMock(SynchronizationService::class);
        $this->resourceService = $this->createMock(GatewayResourceService::class);

        $this->notificationService = new NotificationService(
            $this->entityManager,
            $this->logger,
            $this->syncService,
            $this->resourceService
        );
    }

    /**
     * Tests the notificationHandler with get method.
     *
     * @return void
     */
    public function testNotificationHandler_WithGetMethod_ReturnsData()
    {
        // Arrange
        $data = ['method' => 'GET'];
        $configuration = [];

        // Act
        $result = $this->notificationService->notificationHandler($data, $configuration);

        // Assert
        $this->assertSame($data, $result);
    }

    /**
     * Tests the notificationHandler with post method finding and syncing an object.
     *
     * @return void
     */
    public function testNotificationHandler_WithPostMethod_FindsSyncAndSynchronizesObject()
    {
        // Arrange
        $data = [
            'method'      => 'POST',
            'url'         => 'https://example.com/api/v1/objects/1'
        ];
        $configuration = [
            'entity' => 'https://example.com/example.schema.json',
            'urlLocation' => 'url',
        ];
        $synchronization = $this->createMock(Synchronization::class);
        $source          = $this->createMock(Source::class);
        $schema          = $this->createMock(Entity::class);


        $this->resourceService->expects($this->once())
            ->method('findSourcesForUrl')
            ->with($data['url'])
            ->willReturn([$source]);

        $this->resourceService->expects($this->once())
            ->method('getSchema')
            ->with($configuration['entity'])
            ->willReturn($schema);

        $this->syncService->expects($this->once())
            ->method('findSyncBySource')
            ->with($source, $schema, '1')
            ->willReturn($synchronization);

        $this->syncService->expects($this->once())
            ->method('synchronize')
            ->with($synchronization);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $expectedResponse = new Response(json_encode(['Message' => 'Notification received, object synchronized']), 200, ['Content-type' => 'application/json']);
        $data['response'] = $expectedResponse;

        // Act
        $result = $this->notificationService->notificationHandler($data, $configuration);

        // Assert
        $this->assertEquals($expectedResponse->getContent(), $result['response']->getContent());
        $this->assertEquals($expectedResponse->getStatusCode(), $result['response']->getStatusCode());
        $this->assertSame($expectedResponse->headers->all(), $result['response']->headers->all());
    }

    /**
     * Tests the notificationHandler resulting in an exception, returns the error response.
     *
     * @return void
     */
    public function testNotificationHandler_WithException_ReturnsErrorResponse()
    {
        // Arrange
        // Arrange
        $data = [
            'method'      => 'POST',
            'url'         => 'https://example.com/api/v1/objects/1'
        ];
        $configuration = [
            'entity' => 'https://example.com/example.schema.json',
            'urlLocation' => 'url',
        ];
        $synchronization = $this->createMock(Synchronization::class);
        $source          = $this->createMock(Source::class);

        $this->resourceService->expects($this->once())
            ->method('findSourcesForUrl')
            ->with($data['url'])
            ->willReturn([$source]);

        $this->resourceService->expects($this->once())
            ->method('getSchema')
            ->with($configuration['entity'])
            ->willReturn(null);

        $errorMessage = "Could not find an Entity with this reference: {$configuration['entity']}";
        $errorCode = 500;
        $exception = new Exception($errorMessage, $errorCode);

        $this->logger->expects($this->once())
            ->method('error')
            ->with($errorMessage);

        $expectedResponse = new Response(json_encode(['Message' => $errorMessage]), $errorCode, ['Content-type' => 'application/json']);

        // Act
        $result = $this->notificationService->notificationHandler($data, $configuration);

        // Assert
        $this->assertEquals($expectedResponse->getContent(), $result['response']->getContent());
        $this->assertEquals($expectedResponse->getStatusCode(), $result['response']->getStatusCode());
        $this->assertSame($expectedResponse->headers->all(), $result['response']->headers->all());
    }

    /**
     * Tests the findSource function without an existing source, throwing an exception.
     *
     * @return void
     * @throws Exception
     */
    public function testFindSource_WithNoSource_ThrowsException()
    {
        // Arrange
        $url = 'http://example.com/object/123';

        $this->resourceService->expects($this->once())
            ->method('findSourcesForUrl')
            ->with($url, 'commongateway/corebundle')
            ->willReturn([]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Could not find a Source with this url: $url");
        $this->expectExceptionCode(400);

        // Act
        $this->notificationService->findSource($url);
    }

    /**
     * Tests the findSource function finding multiple sources, throwing an exception.
     *
     * @return void
     * @throws Exception
     */
    public function testFindSource_WithMultipleSources_ThrowsException()
    {
        // Arrange
        $url = 'http://example.com/object/123';
        $sources = [$this->createMock(Source::class), $this->createMock(Source::class)];

        $this->resourceService->expects($this->once())
            ->method('findSourcesForUrl')
            ->with($url, 'commongateway/corebundle')
            ->willReturn($sources);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Found more than one Source (2) with this url: $url");
        $this->expectExceptionCode(400);

        // Act
        $this->notificationService->findSource($url);
    }

    /**
     * Tests the findSource function returing a single source, returning the source.
     *
     * @return void
     * @throws Exception
     */
    public function testFindSource_WithSingleSource_ReturnsSource()
    {
        // Arrange
        $url = 'http://example.com/object/123';
        $source = $this->createMock(Source::class);
        $sources = [$source];

        $this->resourceService->expects($this->once())
            ->method('findSourcesForUrl')
            ->with($url, 'commongateway/corebundle')
            ->willReturn($sources);

        // Act
        $result = $this->notificationService->findSource($url);

        // Assert
        $this->assertSame($source, $result);
    }
}
