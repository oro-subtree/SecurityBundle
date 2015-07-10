<?php

namespace Oro\Bundle\SecurityBundle\Tests\Unit\Acl\Extension;

use Symfony\Component\Security\Acl\Domain\ObjectIdentity;

use Oro\Bundle\SecurityBundle\Acl\Extension\ActionAclExtension;
use Oro\Bundle\SecurityBundle\Annotation\Acl as AclAnnotation;
use Oro\Bundle\SecurityBundle\Metadata\ActionMetadataProvider;

class ActionAclExtensionTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|ActionMetadataProvider
     */
    protected $metadataProvider;

    /**
     * @var ActionAclExtension
     */
    protected $extension;

    protected function setUp()
    {
        $this->metadataProvider = $this->getMockBuilder('Oro\Bundle\SecurityBundle\Metadata\ActionMetadataProvider')
            ->disableOriginalConstructor()
            ->getMock();

        $this->extension = new ActionAclExtension($this->metadataProvider);
    }

    protected function tearDown()
    {
        unset($this->metadataProvider, $this->extension);
    }

    /**
     * @dataProvider supportsDataProvider
     *
     * @param mixed $id
     * @param string $type
     * @param string $action
     * @param bool $isKnownAction
     * @param bool $expected
     */
    public function testSupports($id, $type, $action, $isKnownAction, $expected)
    {
        $this->metadataProvider->expects($isKnownAction ? $this->once() : $this->never())
            ->method('isKnownAction')
            ->with($action)
            ->willReturn($isKnownAction);

        $this->assertEquals($expected, $this->extension->supports($type, $id));
    }

    /**
     * @return array
     */
    public function supportsDataProvider()
    {
        return [
            [
                'id' => 'entity',
                'type' => '\stdClass',
                'action' => '\stdClass',
                'isKnownAction' => false,
                'expected' => false
            ],
            [
                'id' => 'action',
                'type' => 'action_id',
                'action' => 'action_id',
                'isKnownAction' => true,
                'expected' => true
            ],
            [
                'id' => 'action',
                'type' => 'group@action_id',
                'action' => 'action_id',
                'isKnownAction' => true,
                'expected' => true
            ],
        ];
    }

    /**
     * @dataProvider getObjectIdentityDataProvider
     *
     * @param mixed $val
     * @param ObjectIdentity $expected
     */
    public function testGetObjectIdentity($val, $expected)
    {
        $this->assertEquals($expected, $this->extension->getObjectIdentity($val));
    }

    /**
     * @return array
     */
    public function getObjectIdentityDataProvider()
    {
        $annotation = new AclAnnotation([
            'id' => 'action_id',
            'type' => 'action'
        ]);

        $annotation2 = new AclAnnotation([
            'id' => 'action_id',
            'type' => 'action',
            'group_name' => 'group'
        ]);

        return [
            [
                'val' => 'action:action_id',
                'expected' => new ObjectIdentity('action', 'action_id')
            ],
            [
                'val' => 'action:group@action_id',
                'expected' => new ObjectIdentity('action', 'group@action_id')
            ],
            [
                'val' => $annotation,
                'expected' => new ObjectIdentity('action', 'action_id')
            ],
            [
                'val' => $annotation2,
                'expected' => new ObjectIdentity('action', 'group@action_id')
            ]
        ];
    }
}
