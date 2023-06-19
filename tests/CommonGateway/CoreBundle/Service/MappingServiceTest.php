<?php

namespace CommonGateway\CoreBundle\Service;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Style\SymfonyStyle;
use Twig\Environment;

class MappingServiceTest extends TestCase
{
    private $mappingService;

    protected function setUp(): void
    {
        $twigMock = $this->createMock(Environment::class);
        $this->mappingService = new MappingService($twigMock);
    }

    public function testEncodeArrayKeys(): void
    {
        $array = [
            'key.one' => 'value1',
            'key.two' => 'value2',
        ];
        $encodedArray = $this->mappingService->encodeArrayKeys($array, '.', '&#46;');

        $expectedArray = [
            'key&#46;one' => 'value1',
            'key&#46;two' => 'value2',
        ];

        $this->assertEquals($expectedArray, $encodedArray);
    }

    public function testMapping(): void
    {
        // Mock the Mapping object
        $mappingObjectMock = $this->createMock(Mapping::class);

        // Mock the input array
        $input = [
            'key1' => 'value1',
            'key2' => 'value2',
        ];

        $output = $this->mappingService->mapping($mappingObjectMock, $input);

        // Add your assertions for the output
    }

    public function testCoordinateStringToArray(): void
    {
        $coordinates = '1.234 5.678 9.012';
        $expectedArray = [
            [1.234, 5.678],
            [9.012],
        ];

        $resultArray = $this->mappingService->coordinateStringToArray($coordinates);

        $this->assertEquals($expectedArray, $resultArray);
    }
}
