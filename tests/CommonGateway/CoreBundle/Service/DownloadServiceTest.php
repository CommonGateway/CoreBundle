<?php

namespace App\Tests\CommonGateway\CoreBundle\Service;

use App\Entity\Template;
use App\Repository\TemplateRepository;
use CommonGateway\CoreBundle\Service\DownloadService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class DownloadServiceTest extends \PHPUnit\Framework\TestCase
{
    private $entityManager;
    private $logger;
    private $twig;
    private $downloadService;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->twig = new Environment(new ArrayLoader([]));

        $this->downloadService = new DownloadService($this->entityManager, $this->logger, $this->twig);
    }

    public function testRenderWithValidTemplate(): void
    {
        $template = new Template();
        $template->setId(Uuid::uuid4());
        $template->setSupportedSchemas(['template_id']);

        $templateContent = '<html>{{ object.name }}</html>';

        $template->setContent($templateContent);

        $data = [
            '_self' => [
                'schema' => [
                    'id' => 'template_id',
                ],
            ],
        ];


        $criteria = Criteria::create()
            ->where(Criteria::expr()->memberOf('supportedSchemas', 'template_id'));

        $templates = [$template];

        $templateRepository = $this->createMock(TemplateRepository::class);

        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->with('App:Template')
            ->willReturn($templateRepository);

        $templateRepository->expects($this->once())
            ->method('findAll')
            ->willReturn($templates);

        $this->downloadService->render($data);
    }

    public function testRenderWithManyTemplates(): void
    {
        $template = new Template();
        $template->setId(Uuid::uuid4());
        $template->setSupportedSchemas(['template_id']);

        $templateContent = '<html>{{ object.name }}</html>';

        $template->setContent($templateContent);

        $data = [
            '_self' => [
                'schema' => [
                    'id' => 'template_id',
                ],
            ],
        ];


        $criteria = Criteria::create()
            ->where(Criteria::expr()->memberOf('supportedSchemas', 'template_id'));

        $template2 = clone $template;

        $templates = [$template, $template2];

        $templateRepository = $this->createMock(TemplateRepository::class);

        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->with('App:Template')
            ->willReturn($templateRepository);

        $templateRepository->expects($this->once())
            ->method('findAll')
            ->willReturn($templates);

        $this->downloadService->render($data);
    }

    public function testRenderWithInvalidTemplate(): void
    {
        $data = [
            '_self' => [
                'schema' => [
                    'id' => 'template_id',
                ],
            ],
        ];

        $emptyArray = [];
        $templates = $this->createMock(ArrayCollection::class);

        $templateRepository = $this->createMock(TemplateRepository::class);

        $criteria = Criteria::create()
            ->where(Criteria::expr()->memberOf('supportedSchemas', 'template_id'));

        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->with('App:Template')
            ->willReturn($templateRepository);

        $templateRepository->expects($this->once())
            ->method('findAll')
            ->willReturn($emptyArray);

//        $templates->expects($this->once())
//            ->method('matching')
//            ->with($criteria)
//            ->willReturn($templates);

        $this->logger->expects($this->once())
            ->method('error')
            ->with('There is no render template for this type of object.');

        $this->expectException(BadRequestException::class);

        $this->downloadService->render($data);
    }

    public function testDownloadPdf(): void
    {
        $template = new Template();
        $template->setId(Uuid::uuid4());
        $template->setSupportedSchemas(['template_id']);

        $templateContent = '<html>{{ object.name }}</html>';

        $template->setContent($templateContent);

        $templates = [$template];

        $templateRepository = $this->createMock(TemplateRepository::class);

        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->with('App:Template')
            ->willReturn($templateRepository);

        $templateRepository->expects($this->once())
            ->method('findAll')
            ->willReturn($templates);

        $data = [
            '_self' => [
                'schema' => [
                    'id' => 'template_id',
                ],
            ],
        ];

        $this->downloadService->downloadPdf($data);
    }
}
