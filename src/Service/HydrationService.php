<?php

namespace CommonGateway\CoreBundle\Service;

use App\Entity\Entity;
use App\Entity\Gateway as Source;
use App\Entity\ObjectEntity;
use App\Service\SynchronizationService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;

/**
 * This class hydrates objects and sets synchronisations for objects if applicable.
 *
 * @author  Conduction BV <info@conduction.nl>, Robert Zondervan <robert@conduction.nl>
 * @license EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @category Service
 */
class HydrationService
{

    /**
     * The synchronization service.
     *
     * @var SynchronizationService
     */
    private SynchronizationService $syncService;

    /**
     * The entity manager.
     *
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;

    /**
     * The constructor of the service.
     *
     * @param SynchronizationService $syncService
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(SynchronizationService $syncService, EntityManagerInterface $entityManager)
    {
        $this->syncService   = $syncService;
        $this->entityManager = $entityManager;

    }//end __construct()

    /**
     * Recursively loop through an object, check if a synchronisation exists or create one (if necessary).
     *
     * @param array  $object        The object array ready for hydration.
     * @param Source $source        The source the objects need to be connected to.
     * @param Entity $entity        The entity of the (sub)object.
     * @param bool   $unsafeHydrate If we should hydrate unsafely or not (when true it will unset non given properties).
     *
     * @return array|ObjectEntity The resulting object or array.
     */
    public function searchAndReplaceSynchronizations(array $object, Source $source, Entity $entity, bool $flush = true, bool $unsafeHydrate = false)
    {
        foreach ($object as $key => $value) {
            if (is_array($value) === true) {
                $subEntity = $entity;
                if ($entity->getAttributeByName($key) !== false
                    && $entity->getAttributeByName($key) !== null
                    && $entity->getAttributeByName($key)->getObject() !== null
                ) {
                    $subEntity = $entity->getAttributeByName($key)->getObject();
                }

                $object[$key] = $this->searchAndReplaceSynchronizations($value, $source, $subEntity, $flush);
            } else if ($key === '_sourceId') {
                $synchronization = $this->syncService->findSyncBySource($source, $entity, $value);
                if (key_exists('_onlySetIfPreExisting', $object) === true
                    && $object['_onlySetIfPreExisting'] === 'true'
                    && $this->entityManager->getUnitOfWork()->isEntityScheduled($synchronization) === true
                ) {
                    $this->entityManager->remove($synchronization);
                    return null;
                }
            }//end if
        }//end foreach

        if (isset($synchronization) === true) {
            if ($synchronization->getObject() instanceof ObjectEntity === false) {
                $synchronization->setObject(new ObjectEntity($entity));
            }

            $synchronization->getObject()->hydrate($object, $unsafeHydrate);
            $synchronization->setLastChecked(new DateTime());
            $synchronization->setLastSynced(new DateTime());
            $this->entityManager->persist($synchronization->getObject());
            $this->entityManager->persist($synchronization);

            if ($flush === true) {
                $this->entityManager->flush();
                $this->entityManager->flush();
            }

            return $synchronization->getObject();
        }

        return $object;

    }//end searchAndReplaceSynchronizations()
}//end class
