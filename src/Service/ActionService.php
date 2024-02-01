<?php

namespace CommonGateway\CoreBundle\Service;

use App\Entity\Entity;
use App\Entity\ObjectEntity;
use App\Event\ActionEvent;
use App\Exception\AsynchronousException;
use App\Kernel;
use App\Message\ActionMessage;
use DateTime;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use JWadhams\JsonLogic;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Messenger\MessageBusInterface;

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
     * @var ContainerInterface The service container.
     */
    private ContainerInterface $container;

    /**
     * @var LoggerInterface The action logger.
     */
    private LoggerInterface $logger;

    /**
     * @param EventDispatcherInterface $eventDispatcher The event dispatcher.
     * @param EntityManagerInterface   $entityManager   The entity manager.
     * @param RequestStack             $requestStack    The request stack.
     */
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly EntityManagerInterface $entityManager,
        RequestStack $requestStack,
        Kernel $kernel,
        LoggerInterface $actionLogger,
        private readonly MessageBusInterface $messageBus
    ) {
        $this->session   = $requestStack->getSession;
        $this->logger    = $actionLogger;
        $this->container = $kernel->getContainer;

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

    /**
     * Runs a single action.
     *
     *  After running this function, even if it returns an exception, currentActionUserId should always be removed from cache.
     *
     * @param Action $action       The Action.
     * @param array  $data         The data used to run the action.
     * @param string $currentThrow If we got here through CronjobCommand or not true/false.
     *
     * @return array The updated data array after running the action.
     */
    public function runFunction(Action $action, array $data, string $currentThrow): array
    {
        // Is the action is lockable we need to lock it
        if ($action->getIsLockable()) {
            $action->setLocked(new DateTime());
            $this->entityManager->persist($action);
            $this->entityManager->flush();
            if (isset($this->io)) {
                $this->io->text("Locked Action {$action->getName()} at {$action->getLocked()->format('Y-m-d H:i:s')}");
            }
        }

        // Keep track of the user used for running this Action.
        // After runFunction() is done, even if it returns an exception, currentActionUserId should be removed from cache (outside this function)
        $this->session->remove('currentActionUserId');
        if ($action->getUserId() !== null && Uuid::isValid($action->getUserId()) === true) {
            $user = $this->entityManager->getRepository('App:User')->find($action->getUserId());
            if ($user instanceof User === true) {
                $this->session->set('currentActionUserId', $action->getUserId());
            }
        }

        $class  = $action->getClass();
        $object = $this->container->get($class);

        // timer starten
        $startTimer = microtime(true);
        if (isset($this->io)) {
            $this->io->text("Run ActionHandlerInterface \"{$action->getClass()}\"");
            $this->io->newLine();
        }

        $actionRanGood = true;

        try {
            $data = $object->run($data, array_merge($action->getConfiguration(), ['actionConditions' => $action->getConditions()]));
        } catch (AsynchronousException $exception) {
            // Do not stop the execution when the asynchronousError is thrown, but throw at the end
            // Something went wrong
            $actionRanGood = false;
        }

        // timer stoppen
        $stopTimer = microtime(true);

        // Is the action is lockable we need to unlock it
        if ($action->getIsLockable()) {
            $action->setLocked(null);
            if (isset($this->io)) {
                $now = new DateTime();
                $this->io->text("Unlocked Action {$action->getName()} at {$now->format('Y-m-d H:i:s')}");
            }
        }

        $totalTime = ($stopTimer - $startTimer);

        // Let's set some results
        $action->setLastRun(new DateTime());
        $action->setLastRunTime($totalTime);
        $action->setStatus($actionRanGood);

        $this->entityManager->persist($action);
        $this->entityManager->flush();

        $this->handleActionThrows($action, $data, $currentThrow);

        if (isset($exception)) {
            throw $exception;
        }

        return $data;

    }//end runFunction()

    public function handleAction(Action $action, ActionEvent $event): ActionEvent
    {
        // Let's see if the action prefents concurency
        if ($action->getIsLockable()) {
            // bijwerken uit de entity manger
            $this->entityManager->refresh($action);

            if ($action->getLocked()) {
                if (isset($this->io)) {
                    $this->io->info("Action {$action->getName()} is lockable and locked = {$action->getLocked()->format(DateTimeInterface::ISO8601)}");
                }

                return $event;
            }
        }

        if (JsonLogic::apply($action->getConditions(), $event->getData()) && $action->getIsEnabled() == true) {
            $currentCronJobThrow = $this->handleActionIoStart($action, $event);

            if (!$action->getAsync()) {
                try {
                    $event->setData($this->runFunction($action, $event->getData(), $currentCronJobThrow));
                } catch (AsynchronousException $exception) {
                }

                $this->session->remove('currentActionUserId');
            } else {
                $data = $event->getData();
                unset($data['httpRequest']);
                $this->messageBus->dispatch(new ActionMessage($action->getId(), $data, $currentCronJobThrow));
            }

            $this->handleActionIoFinish($action, $currentCronJobThrow);

            // throw events for this Action
        }

        return $event;

    }//end handleAction()

    /**
     * Throws Events for the Action if it has any Throws configured.
     *
     * @param Action      $action
     * @param ActionEvent $event
     * @param bool        $currentCronJobThrow
     *
     * @return void
     */
    private function handleActionThrows(Action $action, array $data, bool $currentCronJobThrow)
    {
        if (isset($this->io)) {
            $totalThrows = $action->getThrows() ? count($action->getThrows()) : 0;
            $ioMessage   = "Found $totalThrows Throw".($totalThrows !== 1 ? 's' : '').' for this Action.';
            $currentCronJobThrow ? $this->io->block($ioMessage) : $this->io->text($ioMessage);
            if ($totalThrows !== 0) {
                $extraDashesStr = $currentCronJobThrow ? '-' : '';
                $this->io->text("0/$totalThrows -$extraDashesStr Start looping through all Throws of this Action...");
                !$currentCronJobThrow ?: $this->io->newLine();
            } else {
                $currentCronJobThrow ?: $this->io->newLine();
            }
        }

        foreach ($action->getThrows() as $key => $throw) {
            // Throw event
            $this->dispatchEvent('commongateway.action.event', $data, $throw);

            if (isset($this->io) && isset($totalThrows) && isset($extraDashesStr)) {
                if ($key !== array_key_last($action->getThrows())) {
                    $keyStr = ($key + 1);
                    $this->io->text("$keyStr/$totalThrows -$extraDashesStr Looping through Throws of this Action \"{$action->getName()}\"...");
                    !$currentCronJobThrow ?: $this->io->newLine();
                }
            }
        }

        if (isset($this->io) && isset($totalThrows) && $totalThrows !== 0 && isset($extraDashesStr)) {
            $this->io->text("$totalThrows/$totalThrows -$extraDashesStr Finished looping through all Throws of this Action \"{$action->getName()}\"");
            $this->io->newLine();
        }

    }//end handleActionThrows()

    /**
     * If we got here through CronjobCommand, write user feedback to $this->io before handling an Action.
     *
     * @param Action      $action
     * @param ActionEvent $event
     *
     * @return bool
     */
    private function handleActionIoStart(Action $action, ActionEvent $event): bool
    {
        $currentCronJobThrow = false;
        if (isset($this->io)
            && $this->session->get('currentCronJobThrow')
            && $this->session->get('currentCronJobThrow') == $event->getType()
            && $this->session->get('currentCronJobSubThrow') == $event->getSubType()
        ) {
            $currentCronJobThrow = true;
            $this->io->block("Found an Action with matching conditions: [{$this->gatewayOEService->implodeMultiArray($action->getConditions())}]");
            $this->io->definitionList(
                'The conditions of the following Action match with the ActionEvent data',
                new TableSeparator(),
                ['Id' => $action->getId()->toString()],
                ['Name' => $action->getName()],
                ['Description' => $action->getDescription()],
                ['UserId' => $action->getUserId()],
                ['Listens' => implode(', ', $action->getListens())],
                ['Throws' => implode(', ', $action->getThrows())],
                ['Class' => $action->getClass()],
                ['Priority' => $action->getPriority()],
                ['Async' => is_null($action->getAsync()) ? null : ($action->getAsync() ? 'True' : 'False')],
                ['IsLockable' => is_null($action->getIsLockable()) ? null : ($action->getIsLockable() ? 'True' : 'False')],
                ['LastRun' => $action->getLastRun() ? $action->getLastRun()->format('Y-m-d H:i:s') : null],
                ['LastRunTime' => $action->getLastRunTime()],
                ['Status' => is_null($action->getStatus()) ? null : ($action->getStatus() ? 'True' : 'False')],
            );
            $this->io->block("The configuration of this Action: [{$this->gatewayOEService->implodeMultiArray($action->getConfiguration())}]");
        }//end if

        // Commented out this log, to avoid log creation overload. Only add back for debug reasons.
        // else if (isset($this->io)) {
        // $this->io->text("The conditions of the Action {$action->getName()} match with the 'sub'-ActionEvent data");
        // }//end if
        return $currentCronJobThrow;

    }//end handleActionIoStart()

    /**
     * If we got here through CronjobCommand, write user feedback to $this->io after handling an Action.
     *
     * @param Action $action
     * @param bool   $currentCronJobThrow
     *
     * @return void
     */
    private function handleActionIoFinish(Action $action, bool $currentCronJobThrow)
    {
        if (isset($this->io) && $currentCronJobThrow) {
            $this->io->definitionList(
                'Finished handling the following Action that matched the ActionEvent data',
                new TableSeparator(),
                ['Id' => $action->getId()->toString()],
                ['Name' => $action->getName()],
                ['LastRun' => $action->getLastRun() ? $action->getLastRun()->format('Y-m-d H:i:s') : null],
                ['LastRunTime' => $action->getLastRunTime()],
                ['Status' => is_null($action->getStatus()) ? null : ($action->getStatus() ? 'True' : 'False')],
            );
        }

        // Commented out this log, to avoid log creation overload. Only add back for debug reasons.
        // else if (isset($this->io)) {
        // $this->io->text("Finished handling the Action {$action->getName()} that matched the 'sub'-ActionEvent data");
        // }
    }//end handleActionIoFinish()

    /**
     * If we got here through CronjobCommand, write user feedback to $this->io before handling Actions.
     *
     * @param ActionEvent $event
     *
     * @return bool currentCronJobThrow. True if the throw of the current Cronjob matches the type of the ActionEvent.
     */
    private function handleEventIo(ActionEvent $event): bool
    {
        if ($this->session->get('io')) {
            $this->io = $this->session->get('io');
            if ($this->session->get('currentCronJobThrow')
                && $this->session->get('currentCronJobThrow') == $event->getType()
                && $this->session->get('currentCronJobSubThrow') == $event->getSubType()
            ) {
                // Commented out this log, to avoid log creation overload. Only add back for debug reasons.
                // $this->io->section("Handle ActionEvent \"{$event->getType()}\"".($event->getSubType() ? " With SubType: \"{$event->getSubType()}\"" : ''));
                $this->logger->info("Handle ActionEvent \"{$event->getType()}\"".($event->getSubType() ? " With SubType: \"{$event->getSubType()}\"" : ''));

                return true;
            } else {
                // Commented out this log, to avoid log creation overload. Only add back for debug reasons.
                // $this->io->text("Handle 'sub'-ActionEvent \"{$event->getType()}\"".($event->getSubType() ? " With SubType: \"{$event->getSubType()}\"" : ''));
                $this->logger->info("Handle 'sub'-ActionEvent \"{$event->getType()}\"".($event->getSubType() ? " With SubType: \"{$event->getSubType()}\"" : ''));
            }
        }

        return false;

    }//end handleEventIo()

    public function handleEvent(ActionEvent $event): ActionEvent
    {
        $currentCronJobThrow = $this->handleEventIo($event);

        // Normal behaviour is using the $event->getType(), but if $event->getSubType() is set, use that one instead.
        $listeningToThrow = !$event->getSubType() ? $event->getType() : $event->getSubType();

        $actions      = $this->entityManager->getRepository('App:Action')->findByListens($listeningToThrow);
        $totalActions = is_countable($actions) ? count($actions) : 0;

        $this->logger->info('Handling actions for event: '.$listeningToThrow.', found '.$totalActions.' listening actions');

        $ioMessage = "Found $totalActions Action".($totalActions !== 1 ? 's' : '')." listening to \"$listeningToThrow\"";
        // Commented out this log, to avoid log creation overload. Only add back for debug reasons.
        // if (isset($this->io)) {
        // $currentCronJobThrow ? $this->io->block($ioMessage) : $this->io->text($ioMessage);
        // if ($totalActions !== 0) {
        // $extraDashesStr = $currentCronJobThrow ? '--' : '';
        // $this->io->text("0/$totalActions --$extraDashesStr Start looping through all Actions listening to \"$listeningToThrow\"...");
        // !$currentCronJobThrow ?: $this->io->newLine();
        // } else {
        // $currentCronJobThrow ?: $this->io->newLine();
        // }
        // }
        $this->logger->debug($ioMessage);

        foreach ($actions as $key => $action) {
            // Handle Action
            $this->session->set('action', $action->getId()->toString());
            $this->logger->debug('Handling action : '.$action->getName().'('.$action->getId().')');
            $this->handleAction($action, $event);

            // Commented out this log, to avoid log creation overload. Only add back for debug reasons.
            // if (isset($this->io) && isset($totalActions) && isset($extraDashesStr)) {
            // if ($key !== array_key_last($actions)) {
            // $keyStr = ($key + 1);
            // $this->io->text("$keyStr/$totalActions --$extraDashesStr Looping through all Actions listening to \"$listeningToThrow\"...");
            // $this->logger->debug("$keyStr/$totalActions -- Looping through all Actions listening to \"$listeningToThrow\"...");
            // !$currentCronJobThrow ?: $this->io->newLine();
            // }
            // }
            $this->session->remove('action');
        }

        if (isset($this->io) && isset($totalActions) && $totalActions !== 0 && isset($extraDashesStr)) {
            $this->io->text("$totalActions/$totalActions -- Finished looping all Actions listening to \"$listeningToThrow\"");
            $this->io->newLine();
        }

        $this->logger->info("$totalActions/$totalActions -- Finished looping all Actions listening to \"$listeningToThrow\"");

        return $event;

    }//end handleEvent()
}//end class
