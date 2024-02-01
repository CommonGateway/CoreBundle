<?php

namespace CommonGateway\CoreBundle\Service;

use App\Entity\Entity;
use App\Entity\ObjectEntity;
use App\Event\ActionEvent;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * This service handles logic regarding actions, sending as well as processing action events.
 *
 * @author Conduction <info@conduction.nl>, Robert Zondervan <robert@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Service
 */
class ActionService
{

    /**
     * @var SymfonyStyle IO driver found in session.
     */
    private SymfonyStyle $io;

    /**
     * @var SessionInterface The session.
     */
    private SessionInterface $session;

    /**
     * @param EventDispatcherInterface $eventDispatcher The event dispatcher.
     * @param EntityManagerInterface   $entityManager   The entity manager.
     * @param RequestStack             $requestStack    The request stack.
     */
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly EntityManagerInterface   $entityManager,
        RequestStack                              $requestStack
    ) {
        $this->session = $requestStack->getSession;

    }//end __construct()

    /**
     * Follow-up function of checkTriggerParentEvents() function, that actually dispatches the put events for parent objects.
     *
     * @param ObjectEntity    $object
     * @param ArrayCollection $triggerParentAttributes
     * @param array           $data
     * @param array           $triggeredParentEvents   An array used to keep track of objects we already triggered parent events for. To prevent endless loops.
     *
     * @return void
     */
    private function dispatchTriggerParentEvents(ObjectEntity $object, ArrayCollection $triggerParentAttributes, array $data, array $triggeredParentEvents): void
    {
        foreach ($triggerParentAttributes as $triggerParentAttribute) {
            // Get the parent value & parent object using the attribute with triggerParentEvents = true.
            $parentValues = $object->findSubresourceOf($triggerParentAttribute);
            foreach ($parentValues as $parentValue) {
                $parentObject = $parentValue->getObjectEntity();
                // Create a data array for the parent Object data. (Also add entity) & dispatch event.
                if (isset($this->io)) {
                    $this->io->text("Trigger event for parent object ({$parentObject->getId()->toString()}) of object with id = {$data['response']['id']}");
                    $this->io->text('Dispatch ActionEvent for Throw: commongateway.object.update');
                    $this->io->newLine();
                }

                // Make sure we set dateModified of the parent object before dispatching an event so the synchronization actually happens.
                $now = new DateTime();
                $parentObject->setDateModified($now);
                $this->dispatchEvent(
                    'commongateway.object.update',
                    [
                        'response' => $parentObject->toArray(),
                        'entity'   => $parentObject->getEntity()->getId()->toString(),
                    ],
                    null,
                    $triggeredParentEvents
                );
            }//end foreach
        }//end foreach

    }//end dispatchTriggerParentEvents()

    /**
     * Checks if the given Entity has parent attributes with TriggerParentEvents = true.
     * And will dispatch put events for each parent object found for these parent attributes.
     *
     * @param Entity $entity
     * @param array  $data
     * @param array  $triggeredParentEvents An array used to keep track of objects we already triggered parent events for. To prevent endless loops.
     *
     * @return void
     */
    private function checkTriggerParentEvents(Entity $entity, array $data, array $triggeredParentEvents): void
    {
        $parentAttributes        = $entity->getUsedIn();
        $triggerParentAttributes = $parentAttributes->filter(
            function ($parentAttribute) {
                return $parentAttribute->getTriggerParentEvents();
            }
        );
        if (isset($this->io) && count($triggerParentAttributes) > 0) {
            $count = count($triggerParentAttributes);
            $this->io->text("Found $count attributes with triggerParentEvents = true for this entity: {$entity->getName()} ({$entity->getId()->toString()})");
            $this->io->newLine();
        }

        if (isset($data['response']['id'])) {
            // Get the object that triggered the initial PUT dispatchEvent.
            $object = $this->entityManager->getRepository('App:ObjectEntity')->find($data['response']['id']);
            if ($object instanceof ObjectEntity and !in_array($data['response']['id'], $triggeredParentEvents)) {
                // Prevent endless loop of dispatching events.
                $triggeredParentEvents[] = $data['response']['id'];
                $this->dispatchTriggerParentEvents($object, $triggerParentAttributes, $data, $triggeredParentEvents);
            }
        }

    }//end checkTriggerParentEvents()

    /**
     * Dispatches an event for CRUD actions.
     *
     * @param string $type The type of event to dispatch
     * @param array  $data The data that should in the event
     */
    public function dispatchEvent(string $type, array $data, $subType = null, array $triggeredParentEvents = []): void
    {
        if ($this->session->get('io')) {
            $this->io = $this->session->get('io');
            $this->io->text("Dispatch ActionEvent for Throw: \"$type\"".($subType ? " and SubType: \"$subType\"" : ''));
            $this->io->newLine();
        }

        $event = new ActionEvent($type, $data, null);
        if ($subType) {
            $event->setSubType($subType);
        }

        $this->eventDispatcher->dispatch($event, $type);

        if (array_key_exists('entity', $data)
            && ($type === 'commongateway.object.update' || $subType === 'commongateway.object.update')
        ) {
            $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['id' => $data['entity']]);
            if ($entity instanceof Entity) {
                $this->checkTriggerParentEvents($entity, $data, $triggeredParentEvents);

                return;
            }

            if (isset($this->io)) {
                $this->io->warning(
                    "Trying to look if we need to trigger parent events for Throw: \"$type\"".($subType ? " and SubType: \"$subType\"" : '')." But couldn't find an Entity with id: \"{$data['entity']}\""
                );
            }
        }

    }//end dispatchEvent()
}//end class
