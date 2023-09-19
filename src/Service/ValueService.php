<?php
/**
 * A service to run automated mutations on values.
 *
 * @author Robert Zondervan (robert@conduction.nl)
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 */
namespace CommonGateway\CoreBundle\Service;

use App\Entity\ObjectEntity;
use App\Entity\Synchronization;
use App\Entity\Value;
use App\Service\SynchronizationService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\ORM\NonUniqueResultException;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class ValueService
{

    /**
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var SynchronizationService
     */
    private SynchronizationService $syncService;

    /**
     * @var ParameterBagInterface
     */
    private ParameterBagInterface $parameterBag;

    /**
     * @var CacheService The cache service.
     */
    private CacheService $cacheService;

    /**
     * @param EntityManagerInterface $entityManager The entity manager.
     * @param LoggerInterface        $objectLogger  The logger.
     * @param SynchronizationService $syncService   The synchronization service.
     * @param ParameterBagInterface  $parameterBag  The parameter bag.
     * @param CacheService           $cacheService  The Cache Service
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        LoggerInterface $objectLogger,
        SynchronizationService $syncService,
        ParameterBagInterface $parameterBag,
        CacheService $cacheService
    ) {
        $this->entityManager = $entityManager;
        $this->logger        = $objectLogger;
        $this->syncService   = $syncService;
        $this->parameterBag  = $parameterBag;
        $this->cacheService  = $cacheService;

    }//end __construct()

    /**
     * Defines the events that the subscriber should subscribe to.
     *
     * @return array The subscribed events
     */
    public function getSubscribedEvents(): array
    {
        return [
            Events::preUpdate,
            Events::prePersist,
            Events::preRemove,
        ];

    }//end getSubscribedEvents()

    /**
     * Gets a subobject by uuid.
     *
     * @param string $uuid        The id of the subobject
     * @param Value  $valueObject The valueObject to add the subobject to
     *
     * @return ObjectEntity|null The found subobject
     */
    public function getSubObjectById(string $uuid, Value $valueObject): ?ObjectEntity
    {
        $parentObject = $valueObject->getObjectEntity();
        $subObject    = $this->entityManager->find(ObjectEntity::class, $uuid);
        if ($subObject === null) {
            try {
                $subObject = $this->entityManager->getRepository(ObjectEntity::class)->findByAnyId($uuid);
            } catch (NonUniqueResultException $exception) {
                $this->logger->error("Found more than one ObjectEntity with uuid = '$uuid' or with a synchronization with sourceId = '$uuid'");

                return null;
            }
        }

        if ($subObject instanceof ObjectEntity === false) {
            $this->logger->error(
                "No subObjectEntity found with uuid ($uuid) or with a synchronization with sourceId = uuid for ParentObject",
                [
                    'uuid'         => $uuid,
                    'ParentObject' => [
                        'id'     => $parentObject->getId()->toString(),
                        'entity' => $parentObject->getEntity() !== null ? [
                            'id'   => $parentObject->getEntity()->getId()->toString(),
                            'name' => $parentObject->getEntity()->getName(),
                        ] : null,
                        '_self'  => $parentObject->getSelf(),
                        'name'   => $parentObject->getName(),
                    ],
                ]
            );

            return null;
        }//end if

        return $subObject;

    }//end getSubObjectById()

    /**
     * Gets a subobject by url.
     *
     * @param string $url         The url of the subobject
     * @param Value  $valueObject The value object to add the subobject to
     *
     * @return ObjectEntity|null The resulting subobject
     */
    public function getSubObjectByUrl(string $url, Value $valueObject): ?ObjectEntity
    {
        // First check if the object is already being synced.
        foreach ($this->entityManager->getUnitOfWork()->getScheduledEntityInsertions() as $insertion) {
            if ($insertion instanceof Synchronization === true && $insertion->getSourceId() === $url) {
                return $insertion->getObject();
            }
        }

        // Then check if the url is internal.
        $self         = str_replace(rtrim($this->parameterBag->get('app_url'), '/'), '', $url);
        $objectEntity = $this->entityManager->getRepository('App:ObjectEntity')->findOneBy(['self' => $self]);
        if ($objectEntity !== null) {
            return $objectEntity;
        }

        // Finally, if we really don't have the object, get it from the source.
        $synchronization = $this->entityManager->getRepository('App:Synchronization')->findOneBy(['sourceId' => $url]);
        if ($synchronization instanceof Synchronization === true) {
            return $synchronization->getObject();
        }

        return $this->syncService->aquireObject($url, $valueObject->getAttribute()->getObject());

    }//end getSubObjectByUrl()

    /**
     * Finds subobjects by identifiers.
     *
     * @param string $identifier  The identifier to find the object for
     * @param Value  $valueObject The value object to add objects to
     *
     * @return ObjectEntity|null The found object
     */
    public function findSubobject(string $identifier, Value $valueObject): ?ObjectEntity
    {
        if (Uuid::isValid($identifier) === true) {
            return $this->getSubObjectById($identifier, $valueObject);
        } else if (filter_var($identifier, FILTER_VALIDATE_URL) !== false) {
            return $this->getSubObjectByUrl($identifier, $valueObject);
        }

        return $identifier;

    }//end findSubobject()

    /**
     * Adds object resources from identifier.
     *
     * @param Value $value The value to find subobjects for.
     *
     * @return void
     */
    public function connectSubObjects(Value $value): void
    {
        if ($value->getArrayValue() !== []) {
            foreach ($value->getArrayValue() as $identifier) {
                $subobject = $this->findSubobject($identifier, $value);
                if ($subobject !== null) {
                    $value->addObject($subobject);
                }
            }

            $value->setArrayValue([]);
        } else if ((Uuid::isValid($value->getStringValue()) === false || filter_var($value->getStringValue(), FILTER_VALIDATE_URL)) === true && $identifier = $value->getStringValue()) {
            foreach ($value->getObjects() as $object) {
                $value->removeObject($object);
            }

            $subobject = $this->findSubobject($identifier, $value);
            if ($subobject !== null) {
                $value->addObject($subobject);
            }
        }

        if ($value->getObjectEntity() instanceof ObjectEntity) {
            $value->getObjectEntity()->setDateModified(new \DateTime());
        }

        $this->entityManager->persist($value);
        $this->entityManager->persist($value->getObjectEntity());
        $this->entityManager->flush();
        $this->cacheService->cacheObject($value->getObjectEntity());

    }//end connectSubObjects()
}//end class
