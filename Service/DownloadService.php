<?php

namespace CommonGateway\CoreBundle\Service;

use App\Entity\Template;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Twig\Environment;

class DownloadService
{
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;
    private Environment $twig;

    public function __construct(EntityManagerInterface $entityManager, LoggerInterface $requestLogger, Environment $twig) {
        $this->entityManager = $entityManager;
        $this->logger        = $requestLogger;
        $this->twig          = $twig;
    }

    public function render(array $data): string
    {
        $criteria = Criteria::create()->where(Criteria::expr()->contains('supportedSchemas', $data['_self']['schema']['reference']));
        $templates = $this->entityManager->getRepository('CoreBundle:Template')->matching($criteria);

        if(count($templates) === 0) {
            $this->logger->error('There is no render template for this type of object.');
            throw new BadRequestException('There is no render template for this type of object.', 406);
        } elseif (count($templates) > 1) {
            $this->logger->warning('There are more than 1 templates for this object, resolving by rendering the first template found.');
        }

        $template = $templates[0];
        if($template instanceof Template !== true) {
            return '';
        }

        return $this->twig->createTemplate($template->getContent())->render($data);
    }

    public function downloadPdf(array $data): string
    {
        $raw = $this->render($data);

        $pdfWriter = new Dompdf();
        $pdfWriter->setPaper('A4', 'portrait');
        $pdfWriter->loadHtml($raw);
        $pdfWriter->render();

        return $pdfWriter->stream();
    }
}
