<?php

namespace CommonGateway\CoreBundle\Service;

use App\Entity\Gateway as Source;
use CommonGateway\CoreBundle\Service\FileSystemHandleService;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\Config;
use League\Flysystem\DirectoryListing;
use League\Flysystem\Filesystem;
use League\Flysystem\ZipArchive\FilesystemZipArchiveProvider;
use League\Flysystem\ZipArchive\ZipArchiveAdapter;
use PhpParser\Node\Scalar\MagicConst\Dir;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Encoder\YamlEncoder;
use Symfony\Component\Filesystem\Filesystem as SymfonyFileSystem;

class FileSystemHandleServiceTest extends TestCase
{
    private $entityManager;
    private $mappingService;
    private $callLogger;
    private $fscService;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->mappingService = $this->createMock(MappingService::class);
        $this->callLogger = $this->createMock(LoggerInterface::class);
        $this->fscService = $this->createMock(FileSystemCreateService::class);
    }

    /**
     * Tests the getFileContents method on an existing file returning content.
     *
     * @return void
     */
    public function testGetFileContents_ExistingFile_ReturnsContent()
    {
        // Arrange
        $filesystemMock = $this->createMock(Filesystem::class);
        $location = '/path/to/file.txt';
        $content = 'File content';

        $filesystemMock->expects($this->once())
            ->method('fileExists')
            ->with($location)
            ->willReturn(true);

        $filesystemMock->expects($this->once())
            ->method('read')
            ->with($location)
            ->willReturn($content);

        $service = new FileSystemHandleService(
            $this->entityManager,
            $this->mappingService,
            $this->callLogger,
            $this->fscService
        );

        // Act
        $result = $service->getFileContents($filesystemMock, $location);

        // Assert
        $this->assertEquals($content, $result);
    }

    /**
     * Tests the getFileContents method on an non-existing file returning no content.
     *
     * @return void
     */
    public function testGetFileContents_NonExistingFile_ReturnsNull()
    {
        // Arrange
        $filesystemMock = $this->createMock(Filesystem::class);
        $location = '/path/to/file.txt';

        $filesystemMock->expects($this->once())
            ->method('fileExists')
            ->with($location)
            ->willReturn(false);

        $service = new FileSystemHandleService(
            $this->entityManager,
            $this->mappingService,
            $this->callLogger,
            $this->fscService
        );

        // Act
        $result = $service->getFileContents($filesystemMock, $location);

        // Assert
        $this->assertNull($result);
    }

    /**
     * Tests the decodeFile function with a json content.
     *
     * @return void
     * @throws \Exception
     */
    public function testDecodeFile_WithJsonContent_ReturnsDecodedArray()
    {
        // Arrange
        $content = '{"key": "value"}';

        $service = new FileSystemHandleService(
            $this->entityManager,
            $this->mappingService,
            $this->callLogger,
            $this->fscService
        );

        // Act
        $result = $service->decodeFile($content, 'file.json');

        // Assert
        $this->assertEquals(['key' => 'value'], $result);
    }

    /**
     * Tests the decodeFile function with a yaml content.
     *
     * @return void
     * @throws \Exception
     */
    public function testDecodeFile_WithYamlContent_ReturnsDecodedArray()
    {
        // Arrange
        $content = 'key: value';

        $service = new FileSystemHandleService(
            $this->entityManager,
            $this->mappingService,
            $this->callLogger,
            $this->fscService
        );

        // Act
        $result = $service->decodeFile($content, 'file.yaml');

        // Assert
        $this->assertEquals(['key' => 'value'], $result);
    }

    /**
     * Tests the decodeFile function with a xml content.
     *
     * @return void
     * @throws \Exception
     */
    public function testDecodeFile_WithXmlContent_ReturnsDecodedArray()
    {
        // Arrange
        $content = '<root><key>value</key></root>';

        $service = new FileSystemHandleService(
            $this->entityManager,
            $this->mappingService,
            $this->callLogger,
            $this->fscService
        );

        // Act
        $result = $service->decodeFile($content, 'file.xml');

        // Assert
        $this->assertEquals(['key' => 'value'], $result);
    }

    /**
     * Tests the getContentFromAllFiles function.
     *
     * @return void
     * @throws \Exception
     */
    public function testGetContentFromAllFiles_ReturnsArrayWithContents()
    {
        // Arrange
        $filesystemMock = $this->createMock(Filesystem::class);
        $filesystemCreateService = new FileSystemCreateService();

        $id = Uuid::uuid4();
        $filename = '/var/tmp/test-'.$id->toString();
        $zipArchiveAdapter = new ZipArchiveAdapter(new FilesystemZipArchiveProvider($filename));

        $zipArchiveAdapter->write('file1.json', '{"content": "Content 1"}', new Config([]));
        $zipArchiveAdapter->write('file2.json', '{"content": "Content 2"}', new Config([]));
        $zipArchiveAdapter->write('file3.json', '{"content": "Content 3"}', new Config([]));

        $fileSystem = new Filesystem($zipArchiveAdapter);

        $fileContents = [
            'file1.json' => ['content' => 'Content 1'],
            'file2.json' => ['content' => 'Content 2'],
            'file3.json' => ['content' => 'Content 3'],
        ];

        $service = new FileSystemHandleService(
            $this->entityManager,
            $this->mappingService,
            $this->callLogger,
            $this->fscService
        );

        // Act
        $result = $service->getContentFromAllFiles($fileSystem);

        // Assert
        $this->assertEquals($fileContents, $result);

        $filesystemCreateService->removeZipFile($filename);
    }

    /**
     * Tests the call function with format config.
     *
     * @return void
     */
    public function testCall_WithFormatConfig_ReturnsDecodedResponse()
    {
        // Arrange
        $sourceMock = $this->createMock(Source::class);
        $filesystemMock = $this->createMock(Filesystem::class);
        $location = '/path/to/file.txt';
        $content = '{"key": "value"}';
        $decodedFile = ['key' => 'value'];

        $sourceMock->expects($this->once())
            ->method('getEndpointsConfig')
            ->willReturn([]);

        $this->fscService->expects($this->once())
            ->method('openFtpFilesystem')
            ->with($sourceMock)
            ->willReturn($filesystemMock);

        $filesystemMock->expects($this->once())
            ->method('fileExists')
            ->with($location)
            ->willReturn(true);

        $filesystemMock->expects($this->once())
            ->method('read')
            ->with($location)
            ->willReturn($content);

        $service = new FileSystemHandleService(
            $this->entityManager,
            $this->mappingService,
            $this->callLogger,
            $this->fscService
        );

        // Act
        $result = $service->call($sourceMock, $location, ['format' => 'json']);

        // Assert
        $this->assertEquals($decodedFile, $result);
    }
}
