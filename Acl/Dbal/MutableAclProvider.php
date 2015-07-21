<?php

namespace Oro\Bundle\SecurityBundle\Acl\Dbal;

use Doctrine\DBAL\Driver\Connection;

use Symfony\Component\Security\Acl\Dbal\MutableAclProvider as BaseMutableAclProvider;
use Symfony\Component\Security\Acl\Domain\Acl;
use Symfony\Component\Security\Acl\Domain\RoleSecurityIdentity;
use Symfony\Component\Security\Acl\Domain\UserSecurityIdentity;
use Symfony\Component\Security\Acl\Model\AclInterface;
use Symfony\Component\Security\Acl\Model\AclCacheInterface;
use Symfony\Component\Security\Acl\Model\MutableAclInterface;
use Symfony\Component\Security\Acl\Model\ObjectIdentityInterface;
use Symfony\Component\Security\Acl\Model\PermissionGrantingStrategyInterface;
use Symfony\Component\Security\Acl\Model\SecurityIdentityInterface;

use Oro\Bundle\SecurityBundle\Acl\Domain\BusinessUnitSecurityIdentity;

/**
 * This class extends the standard Symfony MutableAclProvider.
 *
 * @todo Periodically check if updateSecurityIdentity and deleteSecurityIdentity methods exist
 *       in the standard Symfony MutableAclProvider and delete them from this class if so.
 *       Before deleting carefully check standard implementation of these methods,
 *       especially updateSecurityIdentity.
 * @see https://github.com/symfony/symfony/pull/8305
 * @see https://github.com/symfony/symfony/pull/8650
 */
class MutableAclProvider extends BaseMutableAclProvider
{
    /**
     * @var PermissionGrantingStrategyInterface
     */
    protected $permissionStrategy;

    /**
     * @var MutableAclInterface
     */
    protected $updatedAcl;

    /**
     * Constructor.
     *
     * @param Connection                          $connection
     * @param PermissionGrantingStrategyInterface $permissionGrantingStrategy
     * @param array                               $options
     * @param AclCacheInterface                   $cache
     */
    public function __construct(
        Connection $connection,
        PermissionGrantingStrategyInterface $permissionGrantingStrategy,
        array $options,
        AclCacheInterface $cache = null
    ) {
        $this->permissionStrategy = $permissionGrantingStrategy;
        parent::__construct($connection, $permissionGrantingStrategy, $options, $cache);

    }

    /**
     * Clear cache by $oid
     *
     * @param ObjectIdentityInterface $oid
     */
    public function clearOidCache(ObjectIdentityInterface $oid)
    {
        $this->cache->evictFromCacheByIdentity($oid);
    }

    /**
     * Put in cache empty ACL object for the given OID
     *
     * @param ObjectIdentityInterface $oid
     */
    public function cacheEmptyAcl(ObjectIdentityInterface $oid)
    {
        $this->cache->putInCache(new Acl(0, $oid, $this->permissionStrategy, array(), false));
    }

    /**
     * Checks whether the given ACL is empty
     *
     * @param AclInterface $acl
     * @return bool
     */
    public function isEmptyAcl(AclInterface $acl)
    {
        return method_exists($acl, 'getId') && $acl->getId() === 0;
    }

    /**
     * Put in cache empty ACL object for the given OID indicates that we should use
     * underlying ACL instead it
     *
     * @param ObjectIdentityInterface $oid
     */
    public function cacheWithUnderlyingAcl(ObjectIdentityInterface $oid)
    {
        $this->cache->putInCache(new Acl(-1, $oid, $this->permissionStrategy, array(), false));
    }

    /**
     * Checks whether the given ACL should be replaced with underlying ACL
     *
     * @param AclInterface $acl
     * @return bool
     */
    public function isReplaceWithUnderlyingAcl(AclInterface $acl)
    {
        return method_exists($acl, 'getId') && $acl->getId() === -1;
    }

    /**
     * Initiates a transaction
     */
    public function beginTransaction()
    {
        $this->connection->beginTransaction();
    }

    /**
     * Commits a transaction
     */
    public function commit()
    {
        $this->connection->commit();
    }

    /**
     * Rolls back a transaction
     */
    public function rollBack()
    {
        $this->connection->rollBack();
    }

    /**
     * Updates a security identity when the user's username or the role name changes
     *
     * @param SecurityIdentityInterface $sid
     * @param string $oldName The old security identity name.
     *                        It is the user's username if $sid is UserSecurityIdentity
     *                        or the role name if $sid is RoleSecurityIdentity
     */
    public function updateSecurityIdentity(SecurityIdentityInterface $sid, $oldName)
    {
        $this->connection->executeQuery($this->getUpdateSecurityIdentitySql($sid, $oldName));
    }

    /**
     * Deletes the security identity from the database.
     * ACL entries have the CASCADE option on their foreign key so they will also get deleted
     *
     * @param SecurityIdentityInterface $sid
     * @throws \InvalidArgumentException
     */
    public function deleteSecurityIdentity(SecurityIdentityInterface $sid)
    {
        $this->connection->executeQuery($this->getDeleteSecurityIdentityIdSql($sid));
    }

    /**
     * Deletes all ACL including class data for a given object identity.
     *
     * @param ObjectIdentityInterface $oid
     * @throws \Exception
     */
    public function deleteAclClass(ObjectIdentityInterface $oid)
    {
        $this->connection->beginTransaction();
        try {
            $this->deleteAcl($oid);
            $this->connection->executeQuery($this->getDeleteClassIdSql($oid->getType()));

            $this->connection->commit();
        } catch (\Exception $failed) {
            $this->connection->rollBack();

            throw $failed;
        }
    }

    /**
     * Constructs the SQL for updating a security identity.
     *
     * @param SecurityIdentityInterface $sid
     * @param string $oldName
     * @return string
     * @throws \InvalidArgumentException
     */
    protected function getUpdateSecurityIdentitySql(SecurityIdentityInterface $sid, $oldName)
    {
        if ($sid instanceof UserSecurityIdentity) {
            if ($sid->getUsername() == $oldName) {
                throw new \InvalidArgumentException('There are no changes.');
            }
            $oldIdentifier = $sid->getClass() . '-' . $oldName;
            $newIdentifier = $sid->getClass() . '-' . $sid->getUsername();
            $username = true;
        } elseif ($sid instanceof RoleSecurityIdentity) {
            if ($sid->getRole() == $oldName) {
                throw new \InvalidArgumentException('There are no changes.');
            }
            $oldIdentifier = $oldName;
            $newIdentifier = $sid->getRole();
            $username = false;
        } elseif ($sid instanceof BusinessUnitSecurityIdentity) {
            if ($sid->getId() === $oldName) {
                throw new \InvalidArgumentException('There are no changes.');
            }
            $oldIdentifier = $sid->getClass() . '-' . $oldName;
            $newIdentifier = $sid->getClass() . '-' . $sid->getId();
            $username = false;
        } else {
            throw new \InvalidArgumentException(
                '$sid must either be an instance of UserSecurityIdentity or RoleSecurityIdentity' .
                ' or BusinessUnitSecurityIdentity.'
            );
        }

        return sprintf(
            'UPDATE %s SET identifier = %s WHERE identifier = %s AND username = %s',
            $this->options['sid_table_name'],
            $this->connection->quote($newIdentifier),
            $this->connection->quote($oldIdentifier),
            $this->connection->getDatabasePlatform()->convertBooleans($username)
        );
    }

    /**
     * Constructs the SQL to delete a security identity.
     *
     * @param SecurityIdentityInterface $sid
     * @throws \InvalidArgumentException
     * @return string
     */
    protected function getDeleteSecurityIdentityIdSql(SecurityIdentityInterface $sid)
    {
        $select = $this->getSelectSecurityIdentityIdSql($sid);
        $delete = preg_replace('/^SELECT id FROM/', 'DELETE FROM', $select);

        return $delete;
    }

    /**
     * Constructs the SQL to delete an ACL class.
     *
     * @param string $classType
     * @return string
     */
    protected function getDeleteClassIdSql($classType)
    {
        $select = $this->getSelectClassIdSql($classType);
        $delete = preg_replace('/^SELECT id FROM/', 'DELETE FROM', $select);

        return $delete;
    }

    /**
     * Constructs the SQL for inserting a security identity.
     *
     * @param SecurityIdentityInterface $sid
     *
     * @throws \InvalidArgumentException
     *
     * @return string
     */
    protected function getInsertSecurityIdentitySql(SecurityIdentityInterface $sid)
    {
        if ($sid instanceof UserSecurityIdentity) {
            $identifier = $sid->getClass().'-'.$sid->getUsername();
            $username = true;
        } elseif ($sid instanceof RoleSecurityIdentity) {
            $identifier = $sid->getRole();
            $username = false;
        } elseif ($sid instanceof BusinessUnitSecurityIdentity) {
            $identifier = $sid->getClass() . '-' . $sid->getId();
            $username = false;
        } else {
            throw new \InvalidArgumentException(
                '$sid must either be an instance of UserSecurityIdentity or RoleSecurityIdentity' .
                ' or BusinessUnitSecurityIdentity.'
            );
        }

        return sprintf(
            'INSERT INTO %s (identifier, username) VALUES (%s, %s)',
            $this->options['sid_table_name'],
            $this->connection->quote($identifier),
            $this->connection->getDatabasePlatform()->convertBooleans($username)
        );
    }

    /**
     * Constructs the SQL for selecting the primary key of a security identity.
     *
     * @param SecurityIdentityInterface $sid
     *
     * @throws \InvalidArgumentException
     *
     * @return string
     */
    protected function getSelectSecurityIdentityIdSql(SecurityIdentityInterface $sid)
    {
        if ($sid instanceof UserSecurityIdentity) {
            $identifier = $sid->getClass().'-'.$sid->getUsername();
            $username = true;
        } elseif ($sid instanceof RoleSecurityIdentity) {
            $identifier = $sid->getRole();
            $username = false;
        }  elseif ($sid instanceof BusinessUnitSecurityIdentity) {
            $identifier = $sid->getClass() . '-' . $sid->getId();
            $username = false;
        } else {
            throw new \InvalidArgumentException(
                '$sid must either be an instance of UserSecurityIdentity or RoleSecurityIdentity' .
                ' or BusinessUnitSecurityIdentity.'
            );
        }

        return sprintf(
            'SELECT id FROM %s WHERE identifier = %s AND username = %s',
            $this->options['sid_table_name'],
            $this->connection->quote($identifier),
            $this->connection->getDatabasePlatform()->convertBooleans($username)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function updateAcl(MutableAclInterface $acl)
    {
        $this->updatedAcl = $acl;
        parent::updateAcl($acl);
        $this->updatedAcl = null;
    }

    /**
     * {@inheritdoc}
     */
    protected function getInsertAccessControlEntrySql(
        $classId,
        $objectIdentityId,
        $field,
        $aceOrder,
        $securityIdentityId,
        $strategy,
        $mask,
        $granting,
        $auditSuccess,
        $auditFailure
    ) {
        $recordId = $this->updatedAcl && $this->updatedAcl->getObjectIdentity()
            ? $this->updatedAcl->getObjectIdentity()->getIdentifier()
            : null;

        $query = <<<QUERY
            INSERT INTO %s (
                class_id,
                object_identity_id,
                field_name,
                ace_order,
                security_identity_id,
                mask,
                granting,
                granting_strategy,
                audit_success,
                audit_failure,
                record_id
            )
            VALUES (%d, %s, %s, %d, %d, %d, %s, %s, %s, %s, %s)
QUERY;

        return sprintf(
            $query,
            $this->options['entry_table_name'],
            $classId,
            null === $objectIdentityId ? 'NULL' : (int) $objectIdentityId,
            null === $field ? 'NULL' : $this->connection->quote($field),
            $aceOrder,
            $securityIdentityId,
            $mask,
            $this->connection->getDatabasePlatform()->convertBooleans($granting),
            $this->connection->quote($strategy),
            $this->connection->getDatabasePlatform()->convertBooleans($auditSuccess),
            $this->connection->getDatabasePlatform()->convertBooleans($auditFailure),
            null === $recordId ? 'NULL' : (int) $recordId
        );
    }
}
