<?php

namespace Oro\Bundle\SecurityBundle\Tests\Unit\Configuration;


use Oro\Bundle\SecurityBundle\Configuration\PermissionListConfiguration;
use Oro\Bundle\SecurityBundle\Configuration\PermissionConfiguration;

use Symfony\Component\Yaml\Yaml;

class PermissionListConfigurationTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var PermissionListConfiguration
     */
    protected $configuration;

    protected function setUp()
    {
        $this->configuration = new PermissionListConfiguration(new PermissionConfiguration());
    }

    protected function tearDown()
    {
        unset($this->configuration);
    }

    /**
     * @param array $input
     * @param array $expected
     * @dataProvider processDataProvider
     */
    public function testProcess(array $input, array $expected)
    {
        $this->assertEquals($expected, $this->configuration->processConfiguration($input));
    }

    /**
     * @return array
     */
    public function processDataProvider()
    {
        return [
            'permissions list 1' => [
                'input' => [
                    'permission1' => [
                        'label' => 'Test Label',
                    ],
                    'permission2' => [
                        'label' => 'Test Label',
                        'apply_to_all' => false,
                        'group_names' => ['frontend'],
                        'exclude_entities' => ['Entity1'],
                        'apply_to_entities' => ['Entity2'],
                        'description' => 'Test Description',
                    ],
                ],
                'expected' => [
                    'permission1' => [
                        'label' => 'Test Label',
                        'apply_to_all' => true,
                        'group_names' => ['default'],
                        'exclude_entities' => [],
                        'apply_to_entities' => [],
                    ],
                    'permission2' => [
                        'label' => 'Test Label',
                        'apply_to_all' => false,
                        'group_names' => ['frontend'],
                        'exclude_entities' => ['Entity1'],
                        'apply_to_entities' => ['Entity2'],
                        'description' => 'Test Description',
                    ],
                ],
            ]
        ];
    }
}
