<?php
namespace CommonGateway\CoreBundle\Service;

use App\Entity\Action;
use App\Entity\Endpoint;
use App\Entity\Entity;
use App\Entity\Gateway as Source;
use App\Entity\Mapping;
use App\Repository\ActionRepository;
use App\Repository\EndpointRepository;
use App\Repository\EntityRepository;
use App\Repository\GatewayRepository;
use App\Repository\MappingRepository;
use CommonGateway\CoreBundle\Service\GatewayResourceService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class GatewayResourceServiceTest extends TestCase
{
    /**
     * @var EntityManagerInterface|MockObject
     */
    private $entityManager;

    /**
     * @var LoggerInterface|MockObject
     */
    private $pluginLogger;

    /**
     * @var GatewayResourceService
     */
    private GatewayResourceService $gatewayResourceService;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->pluginLogger = $this->createMock(LoggerInterface::class);
        $this->gatewayResourceService = new GatewayResourceService($this->entityManager, $this->pluginLogger);
    }

    /**
     * Test the 'getSchema' method with an existing entity.
     */
    public function testGetSchemaWithExistingEntity(): void
    {
        $reference = 'test-reference';
        $pluginName = 'test-plugin';
        $entity = $this->createMock(Entity::class);

        $entityRepositoryMock = $this->createMock(EntityRepository::class);
        $entityRepositoryMock->expects($this->once())
            ->method('findOneBy')
            ->with(['reference' => $reference])
            ->willReturn($entity);

        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->with('App:Entity')
            ->willReturn($entityRepositoryMock);

        $result = $this->gatewayResourceService->getSchema($reference, $pluginName);

        $this->assertSame($entity, $result);
    }

    /**
     * Test the 'getSchema' method with a non-existing entity.
     */
    public function testGetSchemaWithNonExistingEntity(): void
    {
        $reference = 'test-reference';
        $pluginName = 'test-plugin';


        $entityRepositoryMock = $this->createMock(EntityRepository::class);
        $entityRepositoryMock->expects($this->once())
            ->method('findOneBy')
            ->with(['reference' => $reference])
            ->willReturn(null);

        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->with('App:Entity')
            ->willReturn($entityRepositoryMock);

        $this->pluginLogger->expects($this->once())
            ->method('error')
            ->with("No entity found for $reference.", ['plugin' => $pluginName]);

        $result = $this->gatewayResourceService->getSchema($reference, $pluginName);

        $this->assertNull($result);
    }

    /**
     * Test the 'getMapping' method with an existing mapping.
     */
    public function testGetMappingWithExistingMapping(): void
    {
        $reference = 'test-reference';
        $pluginName = 'test-plugin';
        $mapping = $this->createMock(Mapping::class);


        $mappingRepositoryMock = $this->createMock(MappingRepository::class);
        $mappingRepositoryMock->expects($this->once())
            ->method('findOneBy')
            ->with(['reference' => $reference])
            ->willReturn($mapping);

        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->with('App:Mapping')
            ->willReturn($mappingRepositoryMock);

        $result = $this->gatewayResourceService->getMapping($reference, $pluginName);

        $this->assertSame($mapping, $result);
    }

    /**
     * Test the 'getMapping' method with a non-existing mapping.
     */
    public function testGetMappingWithNonExistingMapping(): void
    {
        $reference = 'test-reference';
        $pluginName = 'test-plugin';

        $mappingRepositoryMock = $this->createMock(MappingRepository::class);
        $mappingRepositoryMock->expects($this->once())
            ->method('findOneBy')
            ->with(['reference' => $reference])
            ->willReturn(null);

        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->with('App:Mapping')
            ->willReturn($mappingRepositoryMock);

        $this->pluginLogger->expects($this->once())
            ->method('error')
            ->with("No mapping found for $reference.", ['plugin' => $pluginName]);

        $result = $this->gatewayResourceService->getMapping($reference, $pluginName);

        $this->assertNull($result);
    }

    /**
     * Test the 'getSource' method with an existing source.
     */
    public function testGetSourceWithExistingSource(): void
    {
        $reference = 'test-reference';
        $pluginName = 'test-plugin';
        $source = $this->createMock(Source::class);

        $sourceRepositoryMock = $this->createMock(GatewayRepository::class);
        $sourceRepositoryMock->expects($this->once())
            ->method('findOneBy')
            ->with(['reference' => $reference])
            ->willReturn($source);

        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->with('App:Gateway')
            ->willReturn($sourceRepositoryMock);

        $result = $this->gatewayResourceService->getSource($reference, $pluginName);

        $this->assertSame($source, $result);
    }

    /**
     * Test the 'getSource' method with a non-existing source.
     */
    public function testGetSourceWithNonExistingSource(): void
    {
        $reference = 'test-reference';
        $pluginName = 'test-plugin';

        $sourceRepositoryMock = $this->createMock(GatewayRepository::class);
        $sourceRepositoryMock->expects($this->once())
            ->method('findOneBy')
            ->with(['reference' => $reference])
            ->willReturn(null);

        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->with('App:Gateway')
            ->willReturn($sourceRepositoryMock);

        $this->pluginLogger->expects($this->once())
            ->method('error')
            ->with("No source found for $reference.", ['plugin' => $pluginName]);

        $result = $this->gatewayResourceService->getSource($reference, $pluginName);

        $this->assertNull($result);
    }

    /**
     * Test the 'findSourcesForUrl' method with matching sources.
     */
//    public function testFindSourcesForUrlWithMatchingSources(): void
//    {
//        $url = 'http://example.com';
//        $pluginName = 'test-plugin';
//        $source1 = $this->createMock(Source::class);
//        $source2 = $this->createMock(Source::class);
//        $allSources = [$source1, $source2];
//
//        $this->entityManager->expects($this->once())
//            ->method('getRepository')
//            ->with('App:Gateway')
//            ->willReturnSelf();
//
//        $this->entityManager->expects($this->once())
//            ->method('findAll')
//            ->willReturn($allSources);
//
//        $result = $this->gatewayResourceService->findSourcesForUrl($url, $pluginName);
//
//        $this->assertSame($allSources, $result);
//    }
//
//    /**
//     * Test the 'findSourcesForUrl' method with no matching sources.
//     */
//    public function testFindSourcesForUrlWithNoMatchingSources(): void
//    {
//        $url = 'http://example.com';
//        $pluginName = 'test-plugin';
//        $allSources = [];
//
//        $this->entityManager->expects($this->once())
//            ->method('getRepository')
//            ->with('App:Gateway')
//            ->willReturnSelf();
//
//        $this->entityManager->expects($this->once())
//            ->method('findAll')
//            ->willReturn($allSources);
//
//        $this->pluginLogger->expects($this->once())
//            ->method('error')
//            ->with("No sources found for $url.", ['plugin' => $pluginName]);
//
//        $result = $this->gatewayResourceService->findSourcesForUrl($url, $pluginName);
//
//        $this->assertNull($result);
//    }

    /**
     * Test the 'getEndpoint' method with an existing endpoint.
     */
    public function testGetEndpointWithExistingEndpoint(): void
    {
        $reference = 'test-reference';
        $pluginName = 'test-plugin';
        $endpoint = $this->createMock(Endpoint::class);

        $endpointRepositoryMock = $this->createMock(EndpointRepository::class);
        $endpointRepositoryMock->expects($this->once())
            ->method('findOneBy')
            ->with(['reference' => $reference])
            ->willReturn($endpoint);

        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->with('App:Endpoint')
            ->willReturn($endpointRepositoryMock);

        $result = $this->gatewayResourceService->getEndpoint($reference, $pluginName);

        $this->assertSame($endpoint, $result);
    }

    /**
     * Test the 'getEndpoint' method with a non-existing endpoint.
     */
    public function testGetEndpointWithNonExistingEndpoint(): void
    {
        $reference = 'test-reference';
        $pluginName = 'test-plugin';

        $endpointRepositoryMock = $this->createMock(EndpointRepository::class);
        $endpointRepositoryMock->expects($this->once())
            ->method('findOneBy')
            ->with(['reference' => $reference])
            ->willReturn(null);

        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->with('App:Endpoint')
            ->willReturn($endpointRepositoryMock);

        $this->pluginLogger->expects($this->once())
            ->method('error')
            ->with("No endpoint found for $reference.", ['plugin' => $pluginName]);

        $result = $this->gatewayResourceService->getEndpoint($reference, $pluginName);

        $this->assertNull($result);
    }

    /**
     * Test the 'getAction' method with an existing action.
     */
    public function testGetActionWithExistingAction(): void
    {
        $reference = 'test-reference';
        $pluginName = 'test-plugin';
        $action = $this->createMock(Action::class);

        $actionRepositoryMock = $this->createMock(ActionRepository::class);
        $actionRepositoryMock->expects($this->once())
            ->method('findOneBy')
            ->with(['reference' => $reference])
            ->willReturn($action);

        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->with('App:Action')
            ->willReturn($actionRepositoryMock);

        $result = $this->gatewayResourceService->getAction($reference, $pluginName);

        $this->assertSame($action, $result);
    }

    /**
     * Test the 'getAction' method with a non-existing action.
     */
    public function testGetActionWithNonExistingAction(): void
    {
        $reference = 'test-reference';
        $pluginName = 'test-plugin';

        $actionRepositoryMock = $this->createMock(ActionRepository::class);
        $actionRepositoryMock->expects($this->once())
            ->method('findOneBy')
            ->with(['reference' => $reference])
            ->willReturn(null);

        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->with('App:Action')
            ->willReturn($actionRepositoryMock);

        $this->pluginLogger->expects($this->once())
            ->method('error')
            ->with("No action found for $reference.", ['plugin' => $pluginName]);

        $result = $this->gatewayResourceService->getAction($reference, $pluginName);

        $this->assertNull($result);
    }
}
