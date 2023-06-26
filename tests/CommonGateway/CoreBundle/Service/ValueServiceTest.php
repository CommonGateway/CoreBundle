<?php

namespace CommonGateway\CoreBundle\Service;

use App\Entity\Attribute;
use App\Entity\Coupler;
use App\Entity\ObjectEntity;
use App\Entity\Synchronization;
use App\Entity\Value;
use App\Repository\ObjectEntityRepository;
use App\Service\SynchronizationService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\ORM\UnitOfWork;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class ValueServiceTest extends TestCase
{
    private $entityManager;
    private $logger;
    private $synchronizationService;
    private $parameterBag;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->synchronizationService = $this->createMock(SynchronizationService::class);
        $this->parameterBag = $this->createMock(ParameterBagInterface::class);
    }

    public function testGetSubObjectById()
    {
        // Mock dependencies
        $valueObject = $this->createMock(Value::class);
        $objectEntity = $this->createMock(ObjectEntity::class);

        // Mock entity manager behavior
        $this->entityManager->expects($this->once())
            ->method('find')
            ->with(ObjectEntity::class, 'uuid')
            ->willReturn(null);

        $repository = $this->createMock(ObjectEntityRepository::class);
        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->with(ObjectEntity::class)
            ->willReturn($repository);

        $repository->expects($this->once())
            ->method('findByAnyId')
            ->with('uuid')
            ->willReturn($objectEntity);

        // Create ValueService instance
        $valueService = new ValueService(
            $this->entityManager,
            $this->logger,
            $this->synchronizationService,
            $this->parameterBag
        );

        $subObject = $valueService->getSubObjectById('uuid', $valueObject);

        $this->assertSame($objectEntity, $subObject);
    }

    public function testGetSubObjectByUrl()
    {
        // Mock dependencies
        $valueObject = $this->createMock(Value::class);
        $synchronization = $this->createMock(Synchronization::class);
        $objectEntity = $this->createMock(ObjectEntity::class);

        // Mock entity manager behavior
        $unitOfWork = $this->createMock(UnitOfWork::class);
        $this->entityManager->expects($this->once())
            ->method('getUnitOfWork')
            ->willReturn($unitOfWork);

        $unitOfWork->expects($this->once())
            ->method('getScheduledEntityInsertions')
            ->willReturn([$synchronization]);

        // Mock parameter bag behavior
        $this->parameterBag->expects($this->once())
            ->method('get')
            ->with('app_url')
            ->willReturn('http://example.com');

        // Mock repository behavior
        $repository = $this->createMock(ObjectEntityRepository::class);
        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->with('App:ObjectEntity')
            ->willReturn($repository);

        $repository->expects($this->once())
            ->method('findOneBy')
            ->with(['self' => '/path'])
            ->willReturn($objectEntity);

        // Create ValueService instance
        $valueService = new ValueService(
            $this->entityManager,
            $this->logger,
            $this->synchronizationService,
            $this->parameterBag
        );

        $subObject = $valueService->getSubObjectByUrl('http://example.com/path', $valueObject);

        $this->assertSame($objectEntity, $subObject);
    }

    public function testFindSubobjectWithUuid()
    {
        $uuid = Uuid::uuid4()->toString();

        // Mock dependencies
        $valueObject = $this->createMock(Value::class);
        $objectEntity = $this->createMock(ObjectEntity::class);

        // Mock entity manager behavior
        $this->entityManager->expects($this->once())
            ->method('find')
            ->with(ObjectEntity::class, $uuid)
            ->willReturn(null);

        $repository = $this->createMock(ObjectEntityRepository::class);
        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->with(ObjectEntity::class)
            ->willReturn($repository);

        $repository->expects($this->once())
            ->method('findByAnyId')
            ->with($uuid)
            ->willReturn($objectEntity);

        // Create ValueService instance
        $valueService = new ValueService(
            $this->entityManager,
            $this->logger,
            $this->synchronizationService,
            $this->parameterBag
        );

        $subObject = $valueService->findSubobject($uuid, $valueObject);

        $this->assertSame($objectEntity, $subObject);
    }

    public function testFindSubobjectWithUrl()
    {
        // Mock dependencies
        $valueObject = $this->createMock(Value::class);
        $objectEntity = $this->createMock(ObjectEntity::class);
        $synchronization = $this->createMock(Synchronization::class);

        // Mock parameter bag behavior
        $this->parameterBag->expects($this->once())
            ->method('get')
            ->with('app_url')
            ->willReturn('http://example.com');

        // Mock repository behavior
        $repository = $this->createMock(ObjectEntityRepository::class);
        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->with('App:ObjectEntity')
            ->willReturn($repository);

        $repository->expects($this->once())
            ->method('findOneBy')
            ->with(['self' => '/path'])
            ->willReturn($objectEntity);

        // Mock entity manager behavior
        $unitOfWork = $this->createMock(UnitOfWork::class);
        $this->entityManager->expects($this->once())
            ->method('getUnitOfWork')
            ->willReturn($unitOfWork);

        $unitOfWork->expects($this->once())
            ->method('getScheduledEntityInsertions')
            ->willReturn([$synchronization]);

        // Mock parameter bag behavior
        $this->parameterBag->expects($this->once())
            ->method('get')
            ->with('app_url')
            ->willReturn('http://example.com');

        // Mock repository behavior
        $repository = $this->createMock(ObjectEntityRepository::class);
        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->with('App:ObjectEntity')
            ->willReturn($repository);

        // Create ValueService instance
        $valueService = new ValueService(
            $this->entityManager,
            $this->logger,
            $this->synchronizationService,
            $this->parameterBag
        );

        $subObject = $valueService->findSubobject('http://example.com/path', $valueObject);

        $this->assertSame($objectEntity, $subObject);
    }

    public function testGetInverses()
    {
        // Mock dependencies
        $coupler = $this->createMock(Coupler::class);
        $value = $this->createMock(Value::class);
        $objectEntity = $this->createMock(ObjectEntity::class);
        $attribute = $this->createMock(Attribute::class);
        $inversedBy = $this->createMock(Attribute::class);

        $subObjectId = Uuid::uuid4();
        $mainObjectId = Uuid::uuid4();

        // Mock entity manager behavior
        $this->entityManager->expects($this->once())
            ->method('find')
            ->with('App:ObjectEntity', $subObjectId)
            ->willReturn($objectEntity);

        // Mock inverseValue behavior
        $inverseValue = $this->createMock(Value::class);
        $objectEntity->expects($this->once())
            ->method('getValueObject')
            ->with($inversedBy)
            ->willReturn($inverseValue);

        // Mock ArrayCollection behavior
        $inverses = $this->createMock(ArrayCollection::class);
        $inverseValue->expects($this->once())
            ->method('getObjects')
            ->willReturn($inverses);

        $inverses->expects($this->exactly(2))
            ->method('toArray')
            ->willReturn([]);

        $coupler->expects($this->once())
            ->method('getObjectId')
            ->willReturn($subObjectId->toString());

        $value->expects($this->once())
            ->method('getObjectEntity')
            ->willReturn($objectEntity);

        $objectEntity->expects($this->once())
            ->method('getId')
            ->willReturn($mainObjectId);


        // Mock value behavior
        $value->expects($this->exactly(1))
            ->method('getAttribute')
            ->willReturn($attribute);

        // Mock attribute behavior
        $attribute->expects($this->exactly(1))
            ->method('getInversedBy')
            ->willReturn($inversedBy);

        // Create ValueService instance
        $valueService = new ValueService(
            $this->entityManager,
            $this->logger,
            $this->synchronizationService,
            $this->parameterBag
        );

        $result = $valueService->getInverses($coupler, $value, $object);

        $this->assertSame($inverses->toArray(), $result->toArray());
    }

    public function testInverseRelation()
    {
        // Mock dependencies
        $value = $this->createMock(Value::class);
        $coupler = $this->createMock(Coupler::class);
        $object = $this->createMock(ObjectEntity::class);
        $objectEntity = $this->createMock(ObjectEntity::class);
        $inverseValue = $this->createMock(Value::class);
        $attribute = $this->createMock(Attribute::class);
        $inversedBy = $this->createMock(Attribute::class);

        $objects        = new ArrayCollection([$coupler]);
        $inverseObjects = new ArrayCollection([]);

        $subObjectId = Uuid::uuid4();
        $mainObjectId = Uuid::uuid4();

        // Mock value behavior
        $value->expects($this->exactly(3))
            ->method('getAttribute')
            ->willReturn($attribute);

        $value->expects($this->once())
            ->method('getObjects')
            ->willReturn($objects);

        $value->expects($this->exactly(2))
            ->method('getObjectEntity')
            ->willReturn($objectEntity);

        $objectEntity->expects($this->exactly(2))
            ->method('getId')
            ->willReturn($mainObjectId);

        $object->expects($this->exactly(2))
            ->method('getValueObject')
            ->with($inversedBy)
            ->willReturn($inverseValue);

        $inverseValue->expects($this->once())
            ->method('getObjects')
            ->willReturn($inverseObjects);

        // Mock attribute behavior
        $attribute->expects($this->exactly(3))
            ->method('getInversedBy')
            ->willReturn($inversedBy);

        // Mock EntityManager behavior
        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($inverseValue);

        $coupler->expects($this->once())
            ->method('getObjectId')
            ->willReturn($subObjectId->toString());

        // Mock EntityManager behavior
        $this->entityManager->expects($this->once())
            ->method('find')
            ->with('App:ObjectEntity', $subObjectId)
            ->willReturn($object);

        // Create ValueService instance
        $valueService = new ValueService(
            $this->entityManager,
            $this->logger,
            $this->synchronizationService,
            $this->parameterBag
        );

        $valueService->inverseRelation($value);
    }

    public function testRemoveInverses()
    {

        $subObjectId = Uuid::uuid4();
        $mainObjectId = Uuid::uuid4();

        // Mock dependencies
        $coupler = $this->createMock(Coupler::class);
        $value = $this->createMock(Value::class);
        $objectEntity = $this->createMock(ObjectEntity::class);
        $object = $this->createMock(ObjectEntity::class);

        $objectEntity->expects($this->exactly(2))
            ->method('getId')
            ->willReturn($mainObjectId);

        $inverse = new Coupler($objectEntity);
        $attribute = $this->createMock(Attribute::class);
        $inversedBy = $this->createMock(Attribute::class);
        $inverseValue = $this->createMock(Value::class);

        $inverses = $this->createMock(ArrayCollection::class);
        $inverseObjects = new ArrayCollection([$inverse]);

        $value->expects($this->once())
            ->method('getObjectEntity')
            ->willReturn($objectEntity);

        $coupler->expects($this->once())
            ->method('getObjectId')
            ->willReturn($subObjectId->toString());

        // Mock EntityManager behavior
        $this->entityManager->expects($this->once())
            ->method('remove')
            ->with($inverse);


        // Mock EntityManager behavior
        $this->entityManager->expects($this->once())
            ->method('find')
            ->with('App:ObjectEntity', $subObjectId)
            ->willReturn($object);

        // Mock value behavior
        $value->expects($this->exactly(1))
            ->method('getAttribute')
            ->willReturn($attribute);

        // Mock attribute behavior
        $attribute->expects($this->exactly(1))
            ->method('getInversedBy')
            ->willReturn($inversedBy);

        $object->expects($this->exactly(1))
            ->method('getValueObject')
            ->with($inversedBy)
            ->willReturn($inverseValue);

        $inverseValue->expects($this->once())
            ->method('getObjects')
            ->willReturn($inverseObjects);

        // Create ValueService instance
        $valueService = new ValueService(
            $this->entityManager,
            $this->logger,
            $this->synchronizationService,
            $this->parameterBag
        );

        $valueService->removeInverses($coupler, $value);
    }
}

