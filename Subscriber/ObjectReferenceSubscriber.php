<?php

namespace CommonGateway\CoreBundle\Subscriber;

use App\Entity\Attribute;
use App\Entity\Entity;
use CommonGateway\CoreBundle\Service\EavService;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;

/**
 * Todo.
 *
 * @Author Robert Zondervan <robert@conduction.nl>, Ruben van der Linde <ruben@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Subscriber
 */
class ObjectReferenceSubscriber implements EventSubscriberInterface
{
    /**
     * @var EavService The eav service.
     */
    private EavService $eavService;

    /**
     * @var EntityManagerInterface The entity manager.
     */
    private EntityManagerInterface $entityManager;

    /**
     * @param EntityManagerInterface $entityManager The entity manager.
     * @param EavService             $eavService    The eav service.
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        EavService $eavService
    ) {
        $this->entityManager = $entityManager;
        $this->eavService = $eavService;
    }//end __construct()

    /**
     * Gets the subscribed events.
     *
     * @return array an array containing the subscribed events.
     */
    public function getSubscribedEvents(): array
    {
        return [
            Events::prePersist,
            Events::preUpdate,
        ];
    }//end getSubscribedEvents()

    /**
     * Checks whether we should check attributes and entities for connections before we insert an object into the database.
     *
     * @param LifecycleEventArgs $args LifecycleEventArgs.
     *
     * @return void This function doesn't return anything.
     */
    public function prePersist(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();

        // Let see if we need to hook an attribute to an entity.
        if ($object instanceof Attribute === true // It's an attribute.
            && empty($object->getSchema()) === false // It has a reference.
            && empty($object->getObject()) === true // It isn't currently connected to a schema.
        ) {
            $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['reference' => $object->getSchema()]);
            if ($entity) {
                $object->setObject($entity);
            }

            return;
        }
        if ($object instanceof Entity === true // Is it an entity.
            && empty($object->getReference()) === false // Does it have a reference.
        ) {
            $attributes = $this->entityManager->getRepository('App:Attribute')->findBy(['schema' => $object->getReference()]);
            foreach ($attributes as $attribute) {
                if ($attribute instanceof Attribute === false) {
                    continue;
                }
                $attribute->setObject($object);
                if (empty($attribute->getInversedByPropertyName()) === false && empty($attribute->getInversedBy()) === true) {
                    $attribute->setInversedBy($object->getAttributeByName($attribute->getInversedByPropertyName()));
                }
            }

            return;
        }
    }//end prePersist()

    /**
     * Checks whether we should check attributes and entities for connections before we update an object in the database.
     *
     * @param LifecycleEventArgs $args LifecycleEventArgs.
     *
     * @return void This function doesn't return anything.
     */
    public function preUpdate(LifecycleEventArgs $args): void
    {
        // We do the same stuff as during pre persist so we can just call that.
        $this->prePersist($args);
    }//end preUpdate()
}
