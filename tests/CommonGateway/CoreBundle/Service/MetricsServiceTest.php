<?php

namespace CommonGateway\CoreBundle\Service;

use App\Repository\ApplicationRepository;
use App\Repository\EntityRepository;
use App\Repository\ObjectEntityRepository;
use App\Repository\OrganizationRepository;
use App\Repository\UserRepository;
use CommonGateway\CoreBundle\Service\ComposerService;
use CommonGateway\CoreBundle\Service\MetricsService;
use Doctrine\ORM\EntityManagerInterface;
use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\Database;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class MetricsServiceTest extends TestCase
{
    private $clientMock;
    private $composerServiceMock;
    private $entityManagerMock;
    private $parameterBagMock;

    protected function setUp(): void
    {
        $this->clientMock = $this->createMock(Client::class);
        $this->composerServiceMock = $this->createMock(ComposerService::class);
        $this->entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $this->parameterBagMock = $this->createMock(ParameterBagInterface::class);
    }

    /**
     * Tests the getAll function of the metrics service.
     *
     * @return void
     */
    public function testGetAll()
    {
        // Set up test data
        $coreBundle = [
            'version' => '1.0.0',
            'name' => 'Core Bundle',
            'description' => 'Description of Core Bundle',
        ];

        $uuid1 = Uuid::uuid4();
        $uuid2 = Uuid::uuid4();

        $logsCollectionMock = $this->createMock(Collection::class);
        $logsCollectionMock->expects($this->exactly(6))
            ->method('count')
            ->withConsecutive(
                [['level_name' => ['$in' => ['EMERGENCY']]]],
                [['level_name' => ['$in' => ['ALERT']]]],
                [['level_name' => ['$in' => ['CRITICAL']]]],
                [['level_name' => ['$in' => ['ERROR']]]],
                [['level_name' => ['$in' => ['WARNING']]]],
                [['level_name' => ['$in' => ['NOTICE']]]]
            )
            ->willReturnOnConsecutiveCalls(1, 2, 3, 4, 5, 6);

        $logsDatabaseMock = $this->createMock(Database::class);
        $logsDatabaseMock->expects($this->once())
            ->method('__get')
            ->with('logs')
            ->willReturn($logsCollectionMock);

        $jsonCollectionMock = $this->createMock(Collection::class);
        $jsonCollectionMock->expects($this->exactly(3))
            ->method('count')
            ->withConsecutive([], [['_self.schema.id' => $uuid1]], [['_self.schema.id' => $uuid2]])
            ->willReturnOnConsecutiveCalls(10, 1, 1);

        $jsonDatabaseMock = $this->createMock(Database::class);
        $jsonDatabaseMock->expects($this->once())
            ->method('__get')
            ->with('json')
            ->willReturn($jsonCollectionMock);

        $this->clientMock->expects($this->exactly(2))
            ->method('__get')
            ->withConsecutive(['logs'], ['objects'])
            ->willReturnOnConsecutiveCalls($logsDatabaseMock, $jsonDatabaseMock);

        $userRepositoryMock = $this->createMock(UserRepository::class);
        $userRepositoryMock->expects($this->once())
            ->method('count')
            ->willReturn(10);

        $organizationRepositoryMock = $this->createMock(OrganizationRepository::class);
        $organizationRepositoryMock->expects($this->once())
            ->method('count')
            ->willReturn(5);

        $applicationRepositoryMock = $this->createMock(ApplicationRepository::class);
        $applicationRepositoryMock->expects($this->once())
            ->method('count')
            ->willReturn(3);

        $entityRepositoryMock = $this->createMock(EntityRepository::class);
        $entityRepositoryMock->expects($this->once())
            ->method('findAllSelect')
            ->with('e.id, e.name, e.description, e.reference, e.version')
            ->willReturn([
                [
                    'id' => $uuid1,
                    'name' => 'Schema 1',
                    'description' => 'Schema 1 description',
                    'reference' => 'schema1',
                    'version' => '1.0.0',
                ],
                [
                    'id' => $uuid2,
                    'name' => 'Schema 2',
                    'description' => 'Schema 2 description',
                    'reference' => 'schema2',
                    'version' => '2.0.0',
                ],
            ]);

        $objectEntityRepositoryMock = $this->createMock(ObjectEntityRepository::class);
        $objectEntityRepositoryMock->expects($this->once())
            ->method('count')
            ->with([])
            ->willReturn(10);

        $this->composerServiceMock->expects($this->once())
            ->method('getSingle')
            ->with('commongateway/corebundle')
            ->willReturn($coreBundle);

        $this->entityManagerMock->expects($this->exactly(5))
            ->method('getRepository')
            ->withConsecutive(['App:User'], ['App:Organization'], ['App:Application'], ['App:Entity'], ['App:ObjectEntity'])
            ->willReturnOnConsecutiveCalls($userRepositoryMock, $organizationRepositoryMock, $applicationRepositoryMock, $entityRepositoryMock, $objectEntityRepositoryMock);



        // Create the MetricsService instance
        $metricsService = new MetricsService(
            $this->composerServiceMock,
            $this->entityManagerMock,
            $this->parameterBagMock,
            $this->clientMock
        );

        // Execute the method under test
        $metrics = $metricsService->getAll();

        // Assertions
        $this->assertCount(21, $metrics);

        $expectedMetrics = [
            [
                'name' => 'app_version',
                'type' => 'gauge',
                'help' => 'The current version of the application.',
                'value' => '1.0.0',
            ],
            [
                'name' => 'app_name',
                'type' => 'gauge',
                'help' => 'The name of the current version of the application.',
                'value' => 'Core Bundle',
            ],
            [
                'name' => 'app_description',
                'type' => 'gauge',
                'help' => 'The description of the current version of the application.',
                'value' => 'Description of Core Bundle',
            ],
            [
                'name' => 'app_users',
                'type' => 'gauge',
                'help' => 'The current amount of users',
                'value' => 10,
            ],
            [
                'name' => 'app_organisations',
                'type' => 'gauge',
                'help' => 'The current amount of organisations',
                'value' => 5,
            ],
            [
                'name' => 'app_applications',
                'type' => 'gauge',
                'help' => 'The current amount of applications',
                'value' => 3,
            ],
            [
                'name' => 'app_requests',
                'type' => 'counter',
                'help' => 'The total amount of incoming requests handled by this gateway',
                'value' => 0,
            ],
            [
                'name'  => 'app_calls',
                'type'  => 'counter',
                'help'  => 'The total amount of outgoing calls handled by this gateway',
                'value' => 0,
            ],
            [
                'name' => 'app_error_count',
                'type' => 'counter',
                'help' => "The amount of errors, this only counts logs with level_name 'EMERGENCY', 'ALERT', 'CRITICAL' or 'ERROR'.",
                'value' => 10,
            ],
            [
                'name' => 'app_error_list',
                'type' => 'counter',
                'help' => 'The list of errors and their error level/type.',
                'labels' => ['error_level' => 'EMERGENCY'],
                'value' => 1,
            ],
            [
                'name' => 'app_error_list',
                'type' => 'counter',
                'help' => 'The list of errors and their error level/type.',
                'labels' => ['error_level' => 'ALERT'],
                'value' => 2,
            ],
            [
                'name' => 'app_error_list',
                'type' => 'counter',
                'help' => 'The list of errors and their error level/type.',
                'labels' => ['error_level' => 'CRITICAL'],
                'value' => 3,
            ],
            [
                'name' => 'app_error_list',
                'type' => 'counter',
                'help' => 'The list of errors and their error level/type.',
                'labels' => ['error_level' => 'ERROR'],
                'value' => 4,
            ],
            [
                'name' => 'app_error_list',
                'type' => 'counter',
                'help' => 'The list of errors and their error level/type.',
                'labels' => ['error_level' => 'WARNING'],
                'value' => 5,
            ],
            [
                'name' => 'app_error_list',
                'type' => 'counter',
                'help' => 'The list of errors and their error level/type.',
                'labels' => ['error_level' => 'NOTICE'],
                'value' => 6,
            ],
            [
                'name' => 'app_plugins_count',
                'type' => 'gauge',
                'help' => 'The amount of installed plugins',
                'value' => 0,
            ],
            [
                'name' => 'app_objects_count',
                'type' => 'gauge',
                'help' => 'The amount of stored objects',
                'value' => 10,
            ],
            [
                'name'  => 'app_cached_objects_count',
                'type'  => 'gauge',
                'help'  => 'The amount objects in the data layer that are stored in the MongoDB cache',
                'value' => 10,
            ],
            [
                'name' => 'app_schemas_count',
                'type' => 'gauge',
                'help' => 'The amount defined schemas',
                'value' => 2,
            ],
            [
                'name' => 'app_schemas',
                'type' => 'gauge',
                'help' => 'The list of defined schemas and the amount of objects.',
                'labels' => [
                    'schema_name' => 'Schema 1',
                    'schema_description' => 'Schema 1 description',
                    'schema_reference' => 'schema1',
                    'schema_version' => '1.0.0',
                ],
                'value' => 1,
            ],
            [
                'name' => 'app_schemas',
                'type' => 'gauge',
                'help' => 'The list of defined schemas and the amount of objects.',
                'labels' => [
                    'schema_name' => 'Schema 2',
                    'schema_description' => 'Schema 2 description',
                    'schema_reference' => 'schema2',
                    'schema_version' => '2.0.0',
                ],
                'value' => 1,
            ],
        ];

        $this->assertEquals($expectedMetrics, $metrics);
    }


    /**
     * Tests the getErrors function of the metrics service.
     *
     * @return void
     */
    public function testGetErrors()
    {
        // Set up test data
        $logsCollectionMock = $this->createMock(Collection::class);
        $logsCollectionMock->expects($this->exactly(6))
            ->method('count')
            ->withConsecutive(
                [['level_name' => ['$in' => ['EMERGENCY']]]],
                [['level_name' => ['$in' => ['ALERT']]]],
                [['level_name' => ['$in' => ['CRITICAL']]]],
                [['level_name' => ['$in' => ['ERROR']]]],
                [['level_name' => ['$in' => ['WARNING']]]],
                [['level_name' => ['$in' => ['NOTICE']]]]
            )
            ->willReturnOnConsecutiveCalls(1, 2, 3, 4, 5, 6);

        $logsDatabaseMock = $this->createMock(Database::class);
        $logsDatabaseMock->expects($this->once())
            ->method('__get')
            ->with('logs')
            ->willReturn($logsCollectionMock);

        $this->clientMock->expects($this->once())
            ->method('__get')
            ->with('logs')
            ->willReturn($logsDatabaseMock);

        // Create the MetricsService instance
        $metricsService = new MetricsService(
            $this->composerServiceMock,
            $this->entityManagerMock,
            $this->parameterBagMock,
            $this->clientMock
        );

        // Execute the method under test
        $metrics = $metricsService->getErrors();

        // Assertions
        $this->assertCount(7, $metrics);

        $expectedMetrics = [
            [
                'name' => 'app_error_count',
                'type' => 'counter',
                'help' => "The amount of errors, this only counts logs with level_name 'EMERGENCY', 'ALERT', 'CRITICAL' or 'ERROR'.",
                'value' => 10,
            ],
            [
                'name' => 'app_error_list',
                'type' => 'counter',
                'help' => 'The list of errors and their error level/type.',
                'labels' => ['error_level' => 'EMERGENCY'],
                'value' => 1,
            ],
            [
                'name' => 'app_error_list',
                'type' => 'counter',
                'help' => 'The list of errors and their error level/type.',
                'labels' => ['error_level' => 'ALERT'],
                'value' => 2,
            ],
            [
                'name' => 'app_error_list',
                'type' => 'counter',
                'help' => 'The list of errors and their error level/type.',
                'labels' => ['error_level' => 'CRITICAL'],
                'value' => 3,
            ],
            [
                'name' => 'app_error_list',
                'type' => 'counter',
                'help' => 'The list of errors and their error level/type.',
                'labels' => ['error_level' => 'ERROR'],
                'value' => 4,
            ],
            [
                'name' => 'app_error_list',
                'type' => 'counter',
                'help' => 'The list of errors and their error level/type.',
                'labels' => ['error_level' => 'WARNING'],
                'value' => 5,
            ],
            [
                'name' => 'app_error_list',
                'type' => 'counter',
                'help' => 'The list of errors and their error level/type.',
                'labels' => ['error_level' => 'NOTICE'],
                'value' => 6,
            ],
        ];

        $this->assertEquals($expectedMetrics, $metrics);
    }

    /**
     * Tests the getPlugins function of the MetricsService
     *
     * @return void
     */
    public function testGetPlugins()
    {
        // Set up test data
        $plugins = [
            [
                'name' => 'Plugin 1',
                'description' => 'Plugin 1 description',
                'repository' => 'https://github.com/plugin1',
                'version' => '1.0.0',
            ],
            [
                'name' => 'Plugin 2',
                'description' => 'Plugin 2 description',
                'repository' => 'https://github.com/plugin2',
                'version' => '2.0.0',
            ],
        ];

        $this->composerServiceMock->expects($this->once())
            ->method('getAll')
            ->with(['--installed'])
            ->willReturn($plugins);

        $this->parameterBagMock->expects($this->exactly(2))
            ->method('get')
            ->withConsecutive(['cache_url', false], ['cache_url'])
            ->willReturn('mongodb://api-platform:!ChangeMe!@mongodb');

        // Create the MetricsService instance
        $metricsService = new MetricsService(
            $this->composerServiceMock,
            $this->entityManagerMock,
            $this->parameterBagMock
        );

        // Execute the method under test
        $metrics = $metricsService->getPlugins();

        // Assertions
        $this->assertCount(3, $metrics);

        $expectedMetrics = [
            [
                'name' => 'app_plugins_count',
                'type' => 'gauge',
                'help' => 'The amount of installed plugins',
                'value' => 2,
            ],
            [
                'name' => 'app_installed_plugins',
                'type' => 'gauge',
                'help' => 'The list of installed plugins.',
                'labels' => [
                    'plugin_name' => 'Plugin 1',
                    'plugin_description' => 'Plugin 1 description',
                    'plugin_repository' => 'https://github.com/plugin1',
                    'plugin_version' => '1.0.0',
                ],
                'value' => 1,
            ],
            [
                'name' => 'app_installed_plugins',
                'type' => 'gauge',
                'help' => 'The list of installed plugins.',
                'labels' => [
                    'plugin_name' => 'Plugin 2',
                    'plugin_description' => 'Plugin 2 description',
                    'plugin_repository' => 'https://github.com/plugin2',
                    'plugin_version' => '2.0.0',
                ],
                'value' => 1,
            ],
        ];

        $this->assertEquals($expectedMetrics, $metrics);
    }

    /**
     * Tests the getObjects function of the metrics service.
     *
     * @return void
     */
    public function testGetObjects()
    {

        // Set up test data
        $uuid1 = Uuid::uuid4();
        $uuid2 = Uuid::uuid4();

        $jsonCollectionMock = $this->createMock(Collection::class);
        $jsonCollectionMock->expects($this->exactly(3))
            ->method('count')
            ->withConsecutive([], [['_self.schema.id' => $uuid1]], [['_self.schema.id' => $uuid2]])
            ->willReturnOnConsecutiveCalls(10, 1, 1);

        $jsonDatabaseMock = $this->createMock(Database::class);
        $jsonDatabaseMock->expects($this->once())
            ->method('__get')
            ->with('json')
            ->willReturn($jsonCollectionMock);

        $this->clientMock->expects($this->once())
            ->method('__get')
            ->with('objects')
            ->willReturn($jsonDatabaseMock);

        $entityRepositoryMock = $this->createMock(EntityRepository::class);
        $entityRepositoryMock->expects($this->once())
            ->method('findAllSelect')
            ->with('e.id, e.name, e.description, e.reference, e.version')
            ->willReturn([
                [
                    'id' => $uuid1,
                    'name' => 'Schema 1',
                    'description' => 'Schema 1 description',
                    'reference' => 'schema1',
                    'version' => '1.0.0',
                ],
                [
                    'id' => $uuid2,
                    'name' => 'Schema 2',
                    'description' => 'Schema 2 description',
                    'reference' => 'schema2',
                    'version' => '2.0.0',
                ],
            ]);

        $objectEntityRepositoryMock = $this->createMock(ObjectEntityRepository::class);
        $objectEntityRepositoryMock->expects($this->once())
            ->method('count')
            ->with([])
            ->willReturn(10);

        $this->entityManagerMock->expects($this->exactly(2))
            ->method('getRepository')
            ->withConsecutive(['App:Entity'], ['App:ObjectEntity'])
            ->willReturn($entityRepositoryMock, $objectEntityRepositoryMock);

        // Create the MetricsService instance
        $metricsService = new MetricsService(
            $this->composerServiceMock,
            $this->entityManagerMock,
            $this->parameterBagMock,
            $this->clientMock
        );

        // Execute the method under test
        $metrics = $metricsService->getObjects();

        // Assertions
        $this->assertCount(5, $metrics);

        $expectedMetrics = [
            [
                'name' => 'app_objects_count',
                'type' => 'gauge',
                'help' => 'The amount of stored objects',
                'value' => 10,
            ],
            [
                'name'  => 'app_cached_objects_count',
                'type'  => 'gauge',
                'help'  => 'The amount objects in the data layer that are stored in the MongoDB cache',
                'value' => 10,
            ],
            [
                'name' => 'app_schemas_count',
                'type' => 'gauge',
                'help' => 'The amount defined schemas',
                'value' => 2,
            ],
            [
                'name' => 'app_schemas',
                'type' => 'gauge',
                'help' => 'The list of defined schemas and the amount of objects.',
                'labels' => [
                    'schema_name' => 'Schema 1',
                    'schema_description' => 'Schema 1 description',
                    'schema_reference' => 'schema1',
                    'schema_version' => '1.0.0',
                ],
                'value' => 1,
            ],
            [
                'name' => 'app_schemas',
                'type' => 'gauge',
                'help' => 'The list of defined schemas and the amount of objects.',
                'labels' => [
                    'schema_name' => 'Schema 2',
                    'schema_description' => 'Schema 2 description',
                    'schema_reference' => 'schema2',
                    'schema_version' => '2.0.0',
                ],
                'value' => 1,
            ],
        ];

        $this->assertEquals($expectedMetrics, $metrics);
    }
}
