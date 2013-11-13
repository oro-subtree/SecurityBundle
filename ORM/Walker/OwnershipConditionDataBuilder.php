<?php

namespace Oro\Bundle\SecurityBundle\ORM\Walker;

use Oro\Bundle\SecurityBundle\Metadata\EntitySecurityMetadataProvider;
use Symfony\Component\Security\Core\SecurityContextInterface;
use Oro\Bundle\SecurityBundle\Owner\Metadata\OwnershipMetadata;
use Oro\Bundle\SecurityBundle\Owner\Metadata\OwnershipMetadataProvider;
use Oro\Bundle\SecurityBundle\Owner\OwnerTree;
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
    /**
     * @var ServiceLink
     */
    protected $securityContextLink;

    /**
     * @var ObjectIdAccessor
     */
    protected $objectIdAccessor;

    /**
     * @var AclVoter
     */
    protected $aclVoter;

    /**
     * @var OwnershipMetadataProvider
     */
    protected $metadataProvider;

    /**
     * @var EntitySecurityMetadataProvider
     */
    protected $entityMetadataProvider;

    /**
     * @var OwnerTree
     */
    protected $tree;

    /**
     * @param ServiceLink $securityContextLink
     * @param ObjectIdAccessor $objectIdAccessor
     * @param EntitySecurityMetadataProvider $entityMetadataProvider
     * @param OwnershipMetadataProvider $metadataProvider
     * @param $treeProvider
     * @param AclVoter $aclVoter
     */
    public function __construct(
        ServiceLink $securityContextLink,
        ObjectIdAccessor $objectIdAccessor,
        EntitySecurityMetadataProvider $entityMetadataProvider,
        OwnershipMetadataProvider $metadataProvider,
        OwnerTreeProvider $treeProvider,
        AclVoter $aclVoter = null
    ) {
        $this->securityContextLink = $securityContextLink;
        $this->aclVoter = $aclVoter;
        $this->objectIdAccessor = $objectIdAccessor;
        $this->entityMetadataProvider = $entityMetadataProvider;
        $this->metadataProvider = $metadataProvider;
        $this->tree = $treeProvider->getTree();
    }

    /**
     * @param $entityClassName
     * @param $permissions
     * @return null|array
     */
    public function getAclConditionData($entityClassName, $permissions = 'VIEW')
    {
        if ($this->aclVoter === null
            || !$this->getUserId()
            || !$this->entityMetadataProvider->isProtectedEntity($entityClassName)
        ) {
            null;
        }

        $condition = null;

        $observer = new OneShotIsGrantedObserver();
        $this->aclVoter->addOneShotIsGrantedObserver($observer);
        $isGranted = $this->getSecurityContext()->isGranted($permissions, 'entity:' . $entityClassName);

        $constraint = null;

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
        $constraint = null;

        if (AccessLevel::SYSTEM_LEVEL === $accessLevel) {
            $constraint = null;
        } elseif (!$metadata->hasOwner()) {
            if (AccessLevel::GLOBAL_LEVEL === $accessLevel) {
                if ($this->metadataProvider->getOrganizationClass() === $targetEntityClassName) {
                    $orgIds = $this->tree->getUserOrganizationIds($this->getUserId());
                    $constraint = $this->getCondition($orgIds, $metadata, 'id');
                } else {
                    $constraint = null;
                }
            } else {
                $constraint = null;
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
                    $buIds = $this->tree->getUserBusinessUnitIds($this->getUserId());
                    $constraint = $this->getCondition($buIds, $metadata, 'id');
                } elseif ($metadata->isBusinessUnitOwned()) {
                    $buIds = $this->tree->getUserBusinessUnitIds($this->getUserId());
                    $constraint = $this->getCondition($buIds, $metadata);
                } elseif ($metadata->isUserOwned()) {
                    $userIds = [];
                    $this->fillBusinessUnitUserIds($this->getUserId(), $userIds);
                    $constraint = $this->getCondition($userIds, $metadata);
                }
            } elseif (AccessLevel::DEEP_LEVEL === $accessLevel) {
                if ($this->metadataProvider->getBusinessUnitClass() === $targetEntityClassName) {
                    $buIds = [];
                    $this->fillSubordinateBusinessUnitIds($this->getUserId(), $buIds);
                    $constraint = $this->getCondition($buIds, $metadata, 'id');
                } elseif ($metadata->isBusinessUnitOwned()) {
                    $buIds = [];
                    $this->fillSubordinateBusinessUnitIds($this->getUserId(), $buIds);
                    $constraint = $this->getCondition($buIds, $metadata);
                } elseif ($metadata->isUserOwned()) {
                    $userIds = [];
                    $this->fillSubordinateBusinessUnitUserIds($this->getUserId(), $userIds);
                    $constraint = $this->getCondition($userIds, $metadata);
                }
            } elseif (AccessLevel::GLOBAL_LEVEL === $accessLevel) {
                if ($metadata->isOrganizationOwned()) {
                    $orgIds = $this->tree->getUserOrganizationIds($this->getUserId());
                    $constraint = $this->getCondition($orgIds, $metadata);
                } elseif ($metadata->isBusinessUnitOwned()) {
                    $buIds = [];
                    $this->fillOrganizationBusinessUnitIds($this->getUserId(), $buIds);
                    $constraint = $this->getCondition($buIds, $metadata);
                } elseif ($metadata->isUserOwned()) {
                    $userIds = [];
                    $this->fillOrganizationUserIds($this->getUserId(), $userIds);
                    $constraint = $this->getCondition($userIds, $metadata);
                }
            }
        }

        return $constraint;
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
     * @param array      $result [output]
     */
    protected function fillSubordinateBusinessUnitIds($userId, array &$result)
    {
        $buIds = $this->tree->getUserBusinessUnitIds($userId);
        $result = array_merge($buIds, []);
        foreach ($buIds as $buId) {
            $diff = array_diff($this->tree->getSubordinateBusinessUnitIds($buId), $result);
            if (!empty($diff)) {
                $result = array_merge($result, $diff);
            }
        }
    }

    /**
     * Adds all user ids within all business units the given user is associated
     *
     * @param int|string $userId
     * @param array      $result [output]
     */
    protected function fillBusinessUnitUserIds($userId, array &$result)
    {
        foreach ($this->tree->getUserBusinessUnitIds($userId) as $buId) {
            $userIds = $this->tree->getBusinessUnitUserIds($buId);
            if (!empty($userIds)) {
                $result = array_merge($result, $userIds);
            }
        }
    }

    /**
     * Adds all user ids within all subordinate business units the given user is associated
     *
     * @param int|string $userId
     * @param array      $result [output]
     */
    protected function fillSubordinateBusinessUnitUserIds($userId, array &$result)
    {
        $buIds = [];
        $this->fillSubordinateBusinessUnitIds($userId, $buIds);
        foreach ($buIds as $buId) {
            $userIds = $this->tree->getBusinessUnitUserIds($buId);
            if (!empty($userIds)) {
                $result = array_merge($result, $userIds);
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
        foreach ($this->tree->getUserOrganizationIds($userId) as $orgId) {
            $buIds = $this->tree->getOrganizationBusinessUnitIds($orgId);
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
        foreach ($this->tree->getUserOrganizationIds($userId) as $orgId) {
            foreach ($this->tree->getOrganizationBusinessUnitIds($orgId) as $buId) {
                $userIds = $this->tree->getBusinessUnitUserIds($buId);
                if (!empty($userIds)) {
                    $result = array_merge($result, $userIds);
                }
            }
        }
    }

    /**
     * Gets SQL condition for the given owner id or ids
     *
     * @param  int|int[]|null    $idOrIds
     * @param  OwnershipMetadata $metadata
     * @param  string|null       $columnName
     * @return array|null
     */
    protected function getCondition($idOrIds, OwnershipMetadata $metadata, $columnName = null)
    {
        if (!empty($idOrIds)) {
            return array(
                $this->getColumnName($metadata, $columnName),
                $idOrIds
            );
        }

        return null;
    }

    /**
     * Gets the name of owner column
     *
     * @param OwnershipMetadata $metadata
     * @param null $columnName
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