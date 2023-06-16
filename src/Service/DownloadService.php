<?php

namespace CommonGateway\CoreBundle\Service;

use App\Entity\Template;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Twig\Environment;

/**
 * Handles incoming notification api-calls by finding or creating a synchronization and synchronizing an object.
 *
 * @Author Robert Zondervan <robert@conduction.nl>, Wilco Louwerse <wilco@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Service
 */
class DownloadService
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
     * @var Environment
     */
    private Environment $twig;


    /**
     * The constructor sets al needed variables.
     *
     * @param EntityManagerInterface $entityManager The EntityManager
     * @param LoggerInterface        $requestLogger The Logger
     * @param Environment            $twig          Twig
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        LoggerInterface $requestLogger,
        Environment $twig
    ) {
        $this->entityManager = $entityManager;
        $this->logger        = $requestLogger;
        $this->twig          = $twig;

    }//end __construct()


    /**
     * Renders a pdf.
     *
     * @param array $data The data to render.
     *
     * @return string The content rendered.
     */
    public function render(array $data): string
    {
        $criteria = Criteria::create()->where(Criteria::expr()->memberOf("supportedSchemas", $data['_self']['schema']['id']));

        $templates = new ArrayCollection($this->entityManager->getRepository('App:Template')->findAll());
        $templates = $templates->matching($criteria);

        if ($templates->count() === 0) {
            $this->logger->error('There is no render template for this type of object.');
            throw new BadRequestException('There is no render template for this type of object.', 406);
        } else if ($templates->count() > 1) {
            $this->logger->warning('There are more than 1 templates for this object, resolving by rendering the first template found.');
        }

        $template = $templates[0];
        if ($template instanceof Template !== true) {
            return '';
        }

        $twigTemplate = $this->twig->createTemplate($template->getContent());
        $content      = $twigTemplate->render(['object' => $data]);

        return $content;

    }//end render()


    /**
     * Downloads a pdf.
     *
     * @param array $data The data to render for this pdf.
     *
     * @return string The pdf as string output.
     */
    public function downloadPdf(array $data): string
    {
        $raw = $this->render($data);

        $pdfWriter = new Dompdf();
        $pdfWriter->setPaper('A4', 'portrait');
        $pdfWriter->loadHtml($raw);
        $pdfWriter->render();

        return $pdfWriter->output();

    }//end downloadPdf()


}//end class
