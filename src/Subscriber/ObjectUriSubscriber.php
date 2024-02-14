<?php

// src/Subscriber/DatabaseActivitySubscriber.php
namespace CommonGateway\CoreBundle\Subscriber;

use App\Entity\ObjectEntity;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Exception\SessionNotFoundException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Todo: ???
 *
 * @Author Conduction <info@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Subscriber
 */
class ObjectUriSubscriber implements EventSubscriberInterface
{

    /**
     * @var SessionInterface
     */
    private SessionInterface $session;

    /**
     * The constructor sets al needed variables.
     *
     * @param ParameterBagInterface $parameterBag
     * @param SessionInterface      $session
     */
    public function __construct(
        private readonly ParameterBagInterface $parameterBag,
        RequestStack $requestStack
    ) {

        try {
            $this->session = $requestStack->getSession();
        } catch (SessionNotFoundException $exception) {
            $this->session = new Session();
        }

    }//end __construct()

    /**
     * Todo: ???
     *
     * @return array This method can only return the event names;
     * You cannot define a custom method name to execute when each event triggers.
     */
    public function getSubscribedEvents(): array
    {
        return [
            Events::postPersist,
            Events::postUpdate,
        ];

    }//end getSubscribedEvents()

    public function postUpdate(LifecycleEventArgs $args): void
    {
        $this->postPersist($args);

    }//end postUpdate()

    /**
     * Updates the cache whenever an object is put into the database.
     *
     * @param LifecycleEventArgs $args
     *
     * @return void
     */
    public function postPersist(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();
        // if this subscriber only applies to certain entity types,
        if ($object instanceof ObjectEntity) {
            if ($object->getUri() === null || str_contains($object->getUri(), $object->getSelf()) === false) {
                $object->setUri(rtrim($this->parameterBag->get('app_url'), '/').$object->getSelf());
            }

            return;
        }

    }//end postPersist()
}//end class
