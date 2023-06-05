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
        var_dump($data['_self']['schema']['id']);
        $criteria = Criteria::create()->where(Criteria::expr()->memberOf("supportedSchemas", $data['_self']['schema']['id']));

        $templates = new ArrayCollection($this->entityManager->getRepository('App:Template')->findAll());
        $templates = $templates->matching($criteria);

        if($templates->count() === 0) {
            $this->logger->error('There is no render template for this type of object.');
            throw new BadRequestException('There is no render template for this type of object.', 406);
        } elseif ($templates->count() > 1) {
            $this->logger->warning('There are more than 1 templates for this object, resolving by rendering the first template found.');
        }

        $template = $templates[0];
        if($template instanceof Template !== true) {
            return '';
        }

        $twigTemplate = $this->twig->createTemplate($template->getContent());
        $content = $twigTemplate->render(['object' => $data]);

        return $content;
    }

    public function downloadPdf(array $data): string
    {
        $raw = $this->render($data);

        $pdfWriter = new Dompdf();
        $pdfWriter->setPaper('A4', 'portrait');
        $pdfWriter->loadHtml($raw);
        $pdfWriter->render();

        return $pdfWriter->output();
    }
}
