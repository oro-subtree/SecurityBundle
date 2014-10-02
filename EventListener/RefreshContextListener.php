<?php

namespace Oro\Bundle\SecurityBundle\EventListener;

use Symfony\Component\Security\Core\SecurityContextInterface;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\Common\Persistence\Proxy;
use Doctrine\ORM\Event\OnClearEventArgs;
use Doctrine\ORM\EntityManager;

use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Oro\Bundle\SecurityBundle\Authentication\Token\OrganizationContextTokenInterface;
use Oro\Bundle\EntityConfigBundle\DependencyInjection\Utils\ServiceLink;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;

class RefreshContextListener
{
    /**
     * @var ServiceLink
     */
    protected $securityContextLink;

    /**
     * @var ManagerRegistry
     */
    protected $registry;

    /**
     * @var ManagerRegistry
     */
    protected $doctrineHelper;

    /**
     * @param ServiceLink $securityContextLink
     * @param ManagerRegistry $registry
     * @param DoctrineHelper $doctrineHelper
     */
    public function __construct(
        ServiceLink $securityContextLink,
        ManagerRegistry $registry,
        DoctrineHelper $doctrineHelper
    ) {
        $this->securityContextLink = $securityContextLink;
        $this->registry = $registry;
        $this->doctrineHelper = $doctrineHelper;
    }

    /**
     * @param OnClearEventArgs $event
     */
    public function onClear(OnClearEventArgs $event)
    {
        /** @var SecurityContextInterface $securityContext */
        $securityContext = $this->securityContextLink->getService();
        $className = $event->getEntityClass();

        $token = $securityContext->getToken();
        if (!$token) {
            return;
        }

        $user = $token->getUser();
        if ($user && (!$className || $className == ClassUtils::getClass($user))) {
            $user = $this->refreshEntity($user);
            if ($user) {
                $token->setUser($user);
            }
        }

        if ($token instanceof OrganizationContextTokenInterface) {
            $organization = $token->getOrganizationContext();
            if ($organization && (!$className || $className == ClassUtils::getClass($organization))) {
                /** @var Organization $organization */
                $organization = $this->refreshEntity($organization);
                if ($organization) {
                    $token->setOrganizationContext($organization);
                }
            }
        }
    }

    /**
     * @param object $entity
     * @return object|null
     */
    protected function refreshEntity($entity)
    {
        if ($entity instanceof Proxy && !$entity->__isInitialized()) {
            $entity->__load();
        }

        if (!$this->doctrineHelper->getSingleEntityIdentifier($entity)) {
            return null;
        }

        $entityClass = ClassUtils::getClass($entity);

        /** @var EntityManager $entityManager */
        $entityManager = $this->registry->getManagerForClass($entityClass);

        return $entityManager->merge($entity);
    }
}
