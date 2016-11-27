<?php

namespace Oro\Bundle\SecurityBundle\Owner\Metadata;

use Doctrine\Common\Cache\CacheProvider;

use Oro\Bundle\EntityBundle\ORM\EntityClassResolver;
use Oro\Bundle\EntityConfigBundle\Config\ConfigInterface;
use Oro\Bundle\EntityConfigBundle\Provider\ConfigProvider;
use Oro\Bundle\OrganizationBundle\Form\Type\OwnershipType;
use Oro\Bundle\SecurityBundle\Acl\AccessLevel;
use Oro\Bundle\UserBundle\Entity\User;

/**
 * This class provides access to the ownership metadata of a domain object
 */
class OwnershipMetadataProvider extends AbstractMetadataProvider
{
    /**
     * @deprecated since 1.8, use getConfigProvider method instead
     *
     * @var ConfigProvider
     */
    protected $configProvider;

    /**
     * @var CacheProvider
     */
    private $cache;

    /**
     * @var string
     */
    protected $organizationClass;

    /**
     * @var string
     */
    protected $businessUnitClass;

    /**
     * @var string
     */
    protected $userClass;

    /**
     * @param array               $owningEntityNames
     * @param ConfigProvider      $configProvider
     * @param EntityClassResolver $entityClassResolver
     * @param CacheProvider|null  $cache
     *
     * @deprecated since 1.8. $configProvider, $entityClassResolver will be removed
     *      use getConfigProvider, getCache, getEntityClassResolver methods instead
     */
    public function __construct(
        array $owningEntityNames,
        ConfigProvider $configProvider = null,
        EntityClassResolver $entityClassResolver = null,
        CacheProvider $cache = null
    ) {
        parent::__construct($owningEntityNames);

        $this->configProvider = $configProvider;
        $this->cache = $cache;
    }

    /**
     * {@inheritdoc}
     */
    protected function setAccessLevelClasses(array $owningEntityNames, EntityClassResolver $entityClassResolver = null)
    {
        if (!isset($owningEntityNames['organization'], $owningEntityNames['business_unit'], $owningEntityNames['user'])
        ) {
            throw new \InvalidArgumentException(
                'Array parameter $owningEntityNames must contains `organization`, `business_unit` and `user` keys'
            );
        }

        $this->organizationClass = $this->getEntityClassResolver()->getEntityClass($owningEntityNames['organization']);
        $this->businessUnitClass = $this->getEntityClassResolver()->getEntityClass($owningEntityNames['business_unit']);
        $this->userClass = $this->getEntityClassResolver()->getEntityClass($owningEntityNames['user']);
    }

    /**
     * {@inheritdoc}
     */
    protected function createNoOwnershipMetadata()
    {
        return new OwnershipMetadata();
    }

    /**
     * {@inheritdoc}
     */
    public function getGlobalLevelClass()
    {
        return $this->organizationClass;
    }

    /**
     * Gets the class name of the organization entity
     *
     * @return string
     *
     * @deprecated since 1.8, use getGlobalLevelClass instead
     */
    public function getOrganizationClass()
    {
        return $this->getGlobalLevelClass();
    }

    /**
     * {@inheritdoc}
     */
    public function getLocalLevelClass($deep = false)
    {
        return $this->businessUnitClass;
    }

    /**
     * Gets the class name of the business unit entity
     *
     * @return string
     *
     * @deprecated since 1.8, use getLocalLevelClass instead
     */
    public function getBusinessUnitClass()
    {
        return $this->getLocalLevelClass();
    }

    /**
     * {@inheritdoc}
     */
    public function getBasicLevelClass()
    {
        return $this->userClass;
    }

    /**
     * Gets the class name of the user entity
     *
     * @return string
     *
     * @deprecated since 1.8, use getBasicLevelClass instead
     */
    public function getUserClass()
    {
        return $this->getBasicLevelClass();
    }

    /**
     * {@inheritdoc}
     */
    public function supports()
    {
        return $this->getContainer()->get('oro_security.security_facade')->getLoggedUser() instanceof User;
    }

    /**
     * {@inheritdoc}
     */
    protected function getOwnershipMetadata(ConfigInterface $config)
    {
        $ownerType              = $config->get('owner_type');
        $ownerFieldName         = $config->get('owner_field_name');
        $ownerColumnName        = $config->get('owner_column_name');
        $organizationFieldName  = $config->get('organization_field_name');
        $organizationColumnName = $config->get('organization_column_name');

        if (!$organizationFieldName && $ownerType === OwnershipType::OWNER_TYPE_ORGANIZATION) {
            $organizationFieldName  = $ownerFieldName;
            $organizationColumnName = $ownerColumnName;
        }

        return new OwnershipMetadata(
            $ownerType,
            $ownerFieldName,
            $ownerColumnName,
            $organizationFieldName,
            $organizationColumnName
        );
    }

    /**
     * Fix Access Level for given object. Change it from SYSTEM_LEVEL to GLOBAL_LEVEL
     * if object have owner type OWNER_TYPE_BUSINESS_UNIT, OWNER_TYPE_USER or OWNER_TYPE_ORGANIZATION
     *
     * {@inheritdoc}
     */
    public function getMaxAccessLevel($accessLevel, $className = null)
    {
        if (AccessLevel::SYSTEM_LEVEL === $accessLevel && $className) {
            $metadata = $this->getMetadata($className);
            if ($metadata->hasOwner()
                && in_array(
                    $metadata->getOwnerType(),
                    [
                        OwnershipMetadata::OWNER_TYPE_BUSINESS_UNIT,
                        OwnershipMetadata::OWNER_TYPE_USER,
                        OwnershipMetadata::OWNER_TYPE_ORGANIZATION
                    ],
                    true
                )
            ) {
                $accessLevel = AccessLevel::GLOBAL_LEVEL;
            }
        }

        return $accessLevel;
    }

    /**
     * {@inheritdoc}
     */
    protected function getCache()
    {
        if (!$this->cache) {
            $this->cache = $this->getContainer()->get('oro_security.owner.ownership_metadata_provider.cache');
        }

        return $this->cache;
    }
}
