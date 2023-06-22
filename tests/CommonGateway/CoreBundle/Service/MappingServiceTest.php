<?php

namespace App\Tests\CommonGateway\CoreBundle\Service;

use App\Entity\Mapping;
use CommonGateway\CoreBundle\Service\MappingService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
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
            'key.three' => [
                'key.four' => 'value3'
            ]
        ];
        $encodedArray = $this->mappingService->encodeArrayKeys($array, '.', '&#46;');

        $expectedArray = [
            'key&#46;one' => 'value1',
            'key&#46;two' => 'value2',
            'key&#46;three' => [
                'key&#46;four' => 'value3'
            ]
        ];

        $this->assertEquals($expectedArray, $encodedArray);
    }

    public function testMapping(): void
    {
        // Mock the Mapping object
        $mappingObjectMock = new Mapping();

        $mappingObjectMock->setMapping([
            'firstKey'  => 'key1',
            'secondKey' => 'key2',
        ]);
        $mappingObjectMock->setCast([
            'secondKey' => 'bool',
            'key8'      => 'bool',
            'key3'      => 'string',
            'key4'      => 'keyCantBeValue',
            'key5'      => 'unsetIfValue==toBeUnset',
            'key6'      => 'jsonToArray',
            'key7'      => 'coordinateStringToArray'

        ]);
        $mappingObjectMock->setUnset(['key1', 'key2']);
        $mappingObjectMock->setPassTrough(true);

        // Mock the input array
        $input = [
            'key1' => 'value1',
            'key2' => 'false',
            'key3' => 3,
            'key4' => 'key4',
            'key5' => 'toBeUnset',
            'key6' => '{"a": "b"}',
            'key7' => '1.234 5.678',
            'key8' => 'true'
        ];

        $expectedOutput = [
            'firstKey' => 'value1',
            'secondKey' => false,
            'key3' => 3,
            'key6' => ['a' => 'b'],
            'key7' => [1.234, 5.678],
            'key8' => true
            ];

        $output = $this->mappingService->mapping($mappingObjectMock, $input);

        $this->assertEquals($expectedOutput, $output);
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

    public function testSetStyle(): void
    {
        $result = $this->mappingService->setStyle(new SymfonyStyle(new ArrayInput(['bla' => 'bla']), new BufferedOutput()));

        $this->assertEquals($this->mappingService, $result);
    }
}