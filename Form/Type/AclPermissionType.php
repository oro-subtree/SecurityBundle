<?php

namespace Oro\Bundle\SecurityBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class AclPermissionType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('accessLevel', 'checkbox', array(
                'required' => false,
            )
        );
        $builder->add('name', 'hidden', array(
                'required' => false,
            )
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'oro_acl_permission';
    }

    /**
     * {@inheritdoc}
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'Oro\Bundle\SecurityBundle\Model\AclPermission',
        ));
    }
}
