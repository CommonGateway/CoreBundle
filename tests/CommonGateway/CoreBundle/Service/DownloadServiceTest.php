<?php

namespace App\Tests\CommonGateway\CoreBundle\Service;

use App\Entity\Template;
use App\Repository\TemplateRepository;
use CommonGateway\CoreBundle\Service\DownloadService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * A test case for the DownloadService.
 *
 * @Author Robert Zondervan <robert@conduction.nl>, Wilco Louwerse <wilco@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category TestCase
 */
class DownloadServiceTest extends TestCase
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
     * @var DownloadService
     */
    private DownloadService $downloadService;

    /**
     * Set up mock data.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $twig = new Environment(new ArrayLoader([]));

        $this->downloadService = new DownloadService($this->entityManager, $this->logger, $twig);
    }

    /**
     * Tests the render function of the download service with a valid template.
     *
     * @return void
     */
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

    /**
     * Tests the render function of the download service with a many templates.
     *
     * @return void
     */
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

    /**
     * Tests the render function of the download service with an invalid template.
     *
     * @return void
     */
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

        $templateRepository = $this->createMock(TemplateRepository::class);

        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->with('App:Template')
            ->willReturn($templateRepository);

        $templateRepository->expects($this->once())
            ->method('findAll')
            ->willReturn($emptyArray);

        $this->logger->expects($this->once())
            ->method('error')
            ->with('There is no render template for this type of object.');

        $this->expectException(BadRequestException::class);

        $this->downloadService->render($data);
    }

    /**
     * Tests the downloadPdf function of the download service.
     *
     * @return void
     */
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