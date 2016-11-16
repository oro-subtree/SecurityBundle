<?php

namespace Oro\Bundle\SecurityBundle\Twig;

use Oro\Bundle\SecurityBundle\Acl\Domain\DomainObjectWrapper;
use Oro\Bundle\SecurityBundle\SecurityFacade;
use Symfony\Component\Security\Acl\Domain\ObjectIdentity;
use Symfony\Component\Security\Acl\Voter\FieldVote;

class OroSecurityExtension extends \Twig_Extension
{
    /** @var SecurityFacade */
    protected $securityFacade;
    
    /**
     * @param SecurityFacade $securityFacade
     */
    public function __construct(SecurityFacade $securityFacade)
    {
        $this->securityFacade = $securityFacade;
    }

    /**
     * Returns a list of functions to add to the existing list.
     *
     * @return array An array of functions
     */
    public function getFunctions()
    {
        return array(
            'resource_granted' => new \Twig_Function_Method($this, 'checkResourceIsGranted'),
        );
    }

    /**
     * Check if ACL resource is granted for current user
     *
     * @param string|string[] $attributes Can be a role name(s), permission name(s), an ACL annotation id
     *                                    or something else, it depends on registered security voters
     * @param mixed $object A domain object, object identity or object identity descriptor (id:type)
     * @param string $fieldName Field name in case if Field ACL check should be used
     *
     * @return bool
     */
    public function checkResourceIsGranted($attributes, $object = null, $fieldName = null)
    {
        if ($attributes === 'VIEW_WORKFLOW') {
            return $this->securityFacade->isGranted(
                'PERFORM_TRANSITION',
                new FieldVote(
                    new DomainObjectWrapper($object, new ObjectIdentity('workflow', 'b2c_flow_abandoned_shopping_cart')),
                    'abandon'
                )
            );
            return $this->securityFacade->isGranted(
                $attributes,
                new DomainObjectWrapper($object, new ObjectIdentity('workflow', 'b2c_flow_abandoned_shopping_cart'))
            );
        }
        if ($fieldName) {
            return $this->securityFacade->isGranted($attributes, new FieldVote($object, $fieldName));
        }

        return $this->securityFacade->isGranted($attributes, $object);
    }

    /**
     * Returns the name of the extension.
     *
     * @return string
     */
    public function getName()
    {
        return 'oro_security_extension';
    }
}
