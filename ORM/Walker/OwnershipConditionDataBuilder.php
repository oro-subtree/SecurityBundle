<?php

namespace Oro\Bundle\SecurityBundle\ORM\Walker;

use Doctrine\ORM\Query\AST\PathExpression;

use Symfony\Component\Security\Core\SecurityContextInterface;

use Oro\Bundle\SecurityBundle\Authentication\Token\OrganizationContextTokenInterface;
use Oro\Bundle\SecurityBundle\Metadata\EntitySecurityMetadataProvider;
use Oro\Bundle\SecurityBundle\Owner\Metadata\OwnershipMetadata;
use Oro\Bundle\SecurityBundle\Owner\Metadata\OwnershipMetadataProvider;
use Oro\Bundle\SecurityBundle\Owner\OwnerTreeProvider;
use Oro\Bundle\SecurityBundle\Acl\Domain\OneShotIsGrantedObserver;
use Oro\Bundle\SecurityBundle\Acl\Domain\ObjectIdAccessor;
use Oro\Bundle\SecurityBundle\Acl\AccessLevel;
use Oro\Bundle\SecurityBundle\Acl\Voter\AclVoter;
use Oro\Bundle\EntityConfigBundle\DependencyInjection\Utils\ServiceLink;

/**
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class OwnershipConditionDataBuilder
{
    /** @var ServiceLink */
    protected $securityContextLink;

    /** @var ObjectIdAccessor */
    protected $objectIdAccessor;

    /** @var AclVoter */
    protected $aclVoter;

    /** @var OwnershipMetadataProvider */
    protected $metadataProvider;

    /** @var EntitySecurityMetadataProvider */
    protected $entityMetadataProvider;

    /** @var OwnerTreeProvider */
    protected $treeProvider;

    /**
     * @param ServiceLink                    $securityContextLink
     * @param ObjectIdAccessor               $objectIdAccessor
     * @param EntitySecurityMetadataProvider $entityMetadataProvider
     * @param OwnershipMetadataProvider      $metadataProvider
     * @param OwnerTreeProvider              $treeProvider
     * @param AclVoter                       $aclVoter
     */
    public function __construct(
        ServiceLink $securityContextLink,
        ObjectIdAccessor $objectIdAccessor,
        EntitySecurityMetadataProvider $entityMetadataProvider,
        OwnershipMetadataProvider $metadataProvider,
        OwnerTreeProvider $treeProvider,
        AclVoter $aclVoter = null
    ) {
        $this->securityContextLink    = $securityContextLink;
        $this->aclVoter               = $aclVoter;
        $this->objectIdAccessor       = $objectIdAccessor;
        $this->entityMetadataProvider = $entityMetadataProvider;
        $this->metadataProvider       = $metadataProvider;
        $this->treeProvider           = $treeProvider;
    }

    /**
     * Get data for query acl access level check
     * Return null if entity has full access, empty array if user does't have access to the entity
     *  and array with entity field and field values which user have access.
     *
     * @param $entityClassName
     * @param $permissions
     *
     * @return null|array
     */
    public function getAclConditionData($entityClassName, $permissions = 'VIEW')
    {
        if ($this->aclVoter === null
            || !$this->getUserId()
            || !$this->entityMetadataProvider->isProtectedEntity($entityClassName)
        ) {
            return [];
        }

        $condition = null;

        $observer = new OneShotIsGrantedObserver();
        $this->aclVoter->addOneShotIsGrantedObserver($observer);
        $isGranted = $this->getSecurityContext()->isGranted($permissions, 'entity:' . $entityClassName);

        if ($isGranted) {
            $condition = $this->buildConstraintIfAccessIsGranted(
                $entityClassName,
                $observer->getAccessLevel(),
                $this->metadataProvider->getMetadata($entityClassName)
            );
        }

        return $condition;
    }

    /**
     * @param  string            $targetEntityClassName
     * @param  int               $accessLevel
     * @param  OwnershipMetadata $metadata
     *
     * @return null|array
     *
     * The cyclomatic complexity warning is suppressed by performance reasons
     * (to avoid unnecessary cloning od arrays)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    protected function buildConstraintIfAccessIsGranted(
        $targetEntityClassName,
        $accessLevel,
        OwnershipMetadata $metadata
    ) {
        $tree       = $this->treeProvider->getTree();
        $constraint = null;

        if (AccessLevel::SYSTEM_LEVEL === $accessLevel) {
            $constraint = [];
        } elseif (!$metadata->hasOwner()) {
            if (AccessLevel::GLOBAL_LEVEL === $accessLevel) {
                if ($this->metadataProvider->getOrganizationClass() === $targetEntityClassName) {
                    $orgIds     = $tree->getUserOrganizationIds($this->getUserId());
                    $constraint = $this->getCondition($orgIds, $metadata, 'id');
                } else {
                    $constraint = [];
                }
            } else {
                $constraint = [];
            }
        } else {
            if (AccessLevel::BASIC_LEVEL === $accessLevel) {
                if ($this->metadataProvider->getUserClass() === $targetEntityClassName) {
                    $constraint = $this->getCondition($this->getUserId(), $metadata, 'id');
                } elseif ($metadata->isUserOwned()) {
                    $constraint = $this->getCondition($this->getUserId(), $metadata);
                }
            } elseif (AccessLevel::LOCAL_LEVEL === $accessLevel) {
                if ($this->metadataProvider->getBusinessUnitClass() === $targetEntityClassName) {
                    $buIds      = $tree->getUserBusinessUnitIds($this->getUserId(), $this->getOrganizationId());
                    $constraint = $this->getCondition($buIds, $metadata, 'id');
                } elseif ($metadata->isBusinessUnitOwned()) {
                    $buIds      = $tree->getUserBusinessUnitIds($this->getUserId(), $this->getOrganizationId());
                    $constraint = $this->getCondition($buIds, $metadata);
                } elseif ($metadata->isUserOwned()) {
                    $userIds = [];
                    $this->fillBusinessUnitUserIds($this->getUserId(), $this->getOrganizationId(), $userIds);
                    $constraint = $this->getCondition($userIds, $metadata);
                }
            } elseif (AccessLevel::DEEP_LEVEL === $accessLevel) {
                if ($this->metadataProvider->getBusinessUnitClass() === $targetEntityClassName) {
                    $buIds = [];
                    $this->fillSubordinateBusinessUnitIds($this->getUserId(), $this->getOrganizationId(), $buIds);
                    $constraint = $this->getCondition($buIds, $metadata, 'id');
                } elseif ($metadata->isBusinessUnitOwned()) {
                    $buIds = [];
                    $this->fillSubordinateBusinessUnitIds($this->getUserId(), $this->getOrganizationId(), $buIds);
                    $constraint = $this->getCondition($buIds, $metadata);
                } elseif ($metadata->isUserOwned()) {
                    $userIds = [];
                    $this->fillSubordinateBusinessUnitUserIds($this->getUserId(), $this->getOrganizationId(), $userIds);
                    $constraint = $this->getCondition($userIds, $metadata);
                }
            } elseif (AccessLevel::GLOBAL_LEVEL === $accessLevel) {
                if ($metadata->isOrganizationOwned()) {
                    $constraint = $this->getCondition([$this->getOrganizationId()], $metadata, null, true);
                } else {
                    $constraint = $this->getCondition(null, $metadata, null, true);
                }
            }
        }

        return $constraint;
    }

    /**
     * @param OwnershipMetadata $metadata
     *
     * @return array|int|null
     */
    protected function getOrganizationId(OwnershipMetadata $metadata = null)
    {
        $token = $this->getSecurityContext()->getToken();
        if ($token instanceof OrganizationContextTokenInterface) {
            return $token->getOrganizationContext()->getId();
        }

        return null;
    }

    /**
     * Gets the id of logged in user
     *
     * @return int|string
     */
    public function getUserId()
    {
        $token = $this->getSecurityContext()->getToken();
        if (!$token) {
            return null;
        }
        $user = $token->getUser();
        if (!is_object($user) || !is_a($user, $this->metadataProvider->getUserClass())) {
            return null;
        }

        return $this->objectIdAccessor->getId($user);
    }

    /**
     * Adds all business unit ids within all subordinate business units the given user is associated
     *
     * @param int|string $userId
     * @param int|string $organizationId
     * @param array      $result [output]
     */
    protected function fillSubordinateBusinessUnitIds($userId, $organizationId, array &$result)
    {
        $buIds  = $this->treeProvider->getTree()->getUserBusinessUnitIds($userId, $organizationId);
        $result = array_merge($buIds, []);
        foreach ($buIds as $buId) {
            $diff = array_diff($this->treeProvider->getTree()->getSubordinateBusinessUnitIds($buId), $result);
            if (!empty($diff)) {
                $result = array_merge($result, $diff);
            }
        }
    }

    /**
     * Adds all user ids within all business units the given user is associated
     *
     * @param int|string $userId
     * @param int|string $organizationId
     * @param array      $result [output]
     */
    protected function fillBusinessUnitUserIds($userId, $organizationId, array &$result)
    {
        // add current user to select this user owned records
        $result[] = $userId;

        foreach ($this->treeProvider->getTree()->getUserBusinessUnitIds($userId, $organizationId) as $buId) {
            $userIds = $this->treeProvider->getTree()->getUsersAssignedToBU($buId);
            if (!empty($userIds)) {
                $result = array_unique(array_merge($result, $userIds));
            }
        }
    }

    /**
     * Adds all user ids within all subordinate business units the given user is associated
     *
     * @param int|string $userId
     * @param int|string $organizationId
     * @param array      $result [output]
     */
    protected function fillSubordinateBusinessUnitUserIds($userId, $organizationId, array &$result)
    {
        // add current user to select this user owned records
        $result[] = $userId;

        $buIds = [];
        $this->fillSubordinateBusinessUnitIds($userId, $organizationId, $buIds);
        foreach ($buIds as $buId) {
            $userIds = $this->treeProvider->getTree()->getUsersAssignedToBU($buId);
            if (!empty($userIds)) {
                $result = array_unique(array_merge($result, $userIds));
            }
        }
    }

    /**
     * Adds all business unit ids within all organizations the given user is associated
     *
     * @param int|string $userId
     * @param array      $result [output]
     */
    protected function fillOrganizationBusinessUnitIds($userId, array &$result)
    {
        foreach ($this->treeProvider->getTree()->getUserOrganizationIds($userId) as $orgId) {
            $buIds = $this->treeProvider->getTree()->getOrganizationBusinessUnitIds($orgId);
            if (!empty($buIds)) {
                $result = array_merge($result, $buIds);
            }
        }
    }

    /**
     * Adds all user ids within all organizations the given user is associated
     *
     * @param int|string $userId
     * @param array      $result [output]
     */
    protected function fillOrganizationUserIds($userId, array &$result)
    {
        foreach ($this->treeProvider->getTree()->getUserOrganizationIds($userId) as $orgId) {
            foreach ($this->treeProvider->getTree()->getOrganizationBusinessUnitIds($orgId) as $buId) {
                $userIds = $this->treeProvider->getTree()->getBusinessUnitUserIds($buId);
                if (!empty($userIds)) {
                    $result = array_merge($result, $userIds);
                }
            }
        }
    }

    /**
     * Gets SQL condition for the given owner id or ids
     *
     * @param int|int[]|null    idOrIds
     * @param OwnershipMetadata $metadata
     * @param string|null       $columnName
     * @param bool              $ignoreOwner
     *
     * @return array|null
     */
    protected function getCondition($idOrIds, OwnershipMetadata $metadata, $columnName = null, $ignoreOwner = false)
    {
        $organizationField = null;
        $organizationValue = null;
        if ($metadata->getOrganizationColumnName() && $this->getOrganizationId($metadata)) {
            $organizationField = $metadata->getOrganizationFieldName();
            $organizationValue = $this->getOrganizationId($metadata);
        }

        if (!$ignoreOwner && !empty($idOrIds)) {
            return [
                $this->getColumnName($metadata, $columnName),
                $idOrIds,
                $columnName == null ? PathExpression::TYPE_SINGLE_VALUED_ASSOCIATION : PathExpression::TYPE_STATE_FIELD,
                $organizationField,
                $organizationValue,
                $ignoreOwner
            ];
        } elseif ($organizationField && $organizationValue) {
            return [
                null,
                null,
                PathExpression::TYPE_SINGLE_VALUED_ASSOCIATION,
                $organizationField,
                $organizationValue,
                $ignoreOwner
            ];
        }

        return null;
    }

    /**
     * Gets the name of owner column
     *
     * @param OwnershipMetadata $metadata
     * @param null              $columnName
     *
     * @return null|string
     */
    protected function getColumnName(OwnershipMetadata $metadata, $columnName = null)
    {
        if ($columnName === null) {
            $columnName = $metadata->getOwnerFieldName();
        }

        return $columnName;
    }

    /**
     * @return SecurityContextInterface
     */
    protected function getSecurityContext()
    {
        return $this->securityContextLink->getService();
    }
}
