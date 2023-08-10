<?php

namespace CommonGateway\CoreBundle\Service;

use App\Entity\Attribute;
use App\Entity\Entity;
use App\Entity\Gateway as Source;
use App\Entity\ObjectEntity;
use App\Entity\Synchronization;
use App\Service\SynchronizationService;
use Doctrine\ORM\EntityManagerInterface;

/**
 * This class hydrates objects and sets synchronisations for (child/sub-)objects if applicable.
 *
 * @author Conduction BV <info@conduction.nl>, Robert Zondervan <robert@conduction.nl>
 *
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
     * @param array  $object                The object array ready for hydration.
     * @param Source $source                The source the objects need to be connected to.
     * @param Entity $entity                The entity of the (sub)object.
     * @param bool   $unsafeHydrate         If we should hydrate unsafely or not (when true it will unset non given properties).
     * @param bool   $returnSynchronization If we should return the Synchronization of the main object instead of the ObjectEntity/array.
     *
     * @return array|ObjectEntity|Synchronization The resulting object, Synchronization or array.
     */
    public function searchAndReplaceSynchronizations(array $object, Source $source, Entity $entity, bool $flush = true, bool $unsafeHydrate = false, bool $returnSynchronization = false)
    {
        foreach ($object as $key => $value) {
            // Check if we are dealing with an array of subobjects.
            if (is_array($value) === true) {
                $subEntity = $entity;
                if ($entity->getAttributeByName($key) !== false
                    && $entity->getAttributeByName($key) !== null
                    && $entity->getAttributeByName($key)->getObject() !== null
                ) {
                    $subEntity = $entity->getAttributeByName($key)->getObject();
                }

                $object[$key] = $this->searchAndReplaceSynchronizations($value, $source, $subEntity, $flush);

                continue;
            }

            // If we are dealing with the $key _sourceId prioritise it over the $key = 'id'
            if ($key === '_sourceId') {
                // If the value in _sourceId = null we can't create a Synchronization for it.
                if ($value === null) {
                    return [];
                    // todo: ?
                }

                $synchronization = $this->syncService->findSyncBySource($source, $entity, $value);
                unset($object['_sourceId']);

                continue;
            }

            // By default, if we find an id field we use that to create a synchronization.
            if (($key === 'id' || $key === '_id') && isset($synchronization) === false) {
                $synchronization = $this->syncService->findSyncBySource($source, $entity, $value);
            }
        }//end foreach

        // Todo: here we want to do the default syncToGateway synchronization, without creating extra/duplicate objects though...
        if (isset($synchronization) === true) {
            if ($synchronization->getObject() instanceof ObjectEntity === false) {
                $synchronization->setObject(new ObjectEntity($entity));
            }

            $synchronization->getObject()->hydrate($object, $unsafeHydrate);
            $this->entityManager->persist($synchronization->getObject());
            $this->entityManager->persist($synchronization);

            if ($flush === true) {
                $this->entityManager->flush();
                $this->entityManager->flush();
            }

            if ($returnSynchronization === true) {
                return $synchronization;
            }

            return $synchronization->getObject();
        }

        return $object;

    }//end searchAndReplaceSynchronizations()
}//end class
