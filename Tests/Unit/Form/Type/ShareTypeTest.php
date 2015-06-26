<?php

namespace Oro\Bundle\SecurityBundle\Tests\Unit\Form\Type;

use Oro\Bundle\SecurityBundle\Form\Type\ShareType;

class ShareTypeTest extends \PHPUnit_Framework_TestCase
{
    /** @var ShareType */
    protected $type;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $this->type = new ShareType();
    }

    public function testGetName()
    {
        $this->assertEquals('oro_share', $this->type->getName());
    }

    public function testBuildForm()
    {
        $builder = $this->getMockBuilder('Symfony\Component\Form\FormBuilder')
            ->disableOriginalConstructor()
            ->getMock();
        $builder->expects($this->at(0))->method('add')->with(
            'entityClass', 'hidden', ['required' => false]
        )->willReturn($builder);
        $builder->expects($this->at(1))->method('add')->with(
            'entityId', 'hidden', ['required' => false]
        )->willReturn($builder);
        $builder->expects($this->at(2))->method('add')->with(
            'users',
            'entity',
            [
                'class'    => 'OroUserBundle:User',
                'property' => 'username',
                'label'    => 'oro.user.entity_plural_label',
                'required' => false,
                'multiple' => true,
            ]
        )->willReturn($builder);
        $builder->expects($this->at(3))->method('add')->with(
            'businessunits',
            'entity',
            [
                'class'    => 'OroOrganizationBundle:BusinessUnit',
                'property' => 'name',
                'label'    => 'oro.organization.businessunit.entity_plural_label',
                'required' => false,
                'multiple' => true,
            ]
        )->willReturn($builder);
        $this->type->buildForm($builder, []);
    }

    public function testSetDefaultOptions()
    {
        $resolver = $this->getMockBuilder('Symfony\Component\OptionsResolver\OptionsResolverInterface')
            ->disableOriginalConstructor()
            ->getMock();
        $resolver->expects($this->once())
            ->method('setDefaults')
            ->with(
                [
                    'data_class'         => 'Oro\Bundle\SecurityBundle\Form\Model\Share',
                    'intention'          => 'email',
                    'csrf_protection'    => true,
                    'cascade_validation' => true,
                ]
            );
        $this->type->setDefaultOptions($resolver);
    }
}
