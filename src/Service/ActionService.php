<?php
/**
 * Service to check action validity and run actions.
 *
 * This service provides a guzzle wrapper to work with sources in the common gateway.
 *
 * @Author Ruben van der Linde <ruben@conduction.nl>, Robert Zondervan <robert@conduction.nl>, Sarai Misidjan <sarai@conduction.nl>, Barry Brands <barry@conduction.nl>, Wilco Louwerse <wilco@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Service
 */

namespace CommonGateway\CoreBundle\Service;

use App\Entity\Action;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ActionService
{
    private EntityManagerInterface $entityManager;

    private ?SymfonyStyle $style = null;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Set symfony style in order to output to the console.
     *
     * @param SymfonyStyle $style
     *
     * @return self
     */
    public function setStyle(SymfonyStyle $style): self
    {
        $this->style = $style;

        return $this;

    }//end setStyle()

    public function scanActions(): bool
    {
        $actions = $this->entityManager->getRepository('App:Action')->findAll();

        foreach($actions as $action) {
            if($action instanceof Action === false) {
                continue;
            }

            if(class_exists($action->getClass()) === false) {
                if($this->style instanceof SymfonyStyle === true) {
                    $this->style->writeln("Removing {$action->getName()}");
                }
                $this->entityManager->remove($action);
                $this->entityManager->flush();
            }
        }

        return true;
    }
}
