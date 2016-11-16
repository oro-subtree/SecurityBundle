<?php

namespace Oro\Bundle\SecurityBundle\Acl\Extension;

use Symfony\Component\Security\Acl\Model\ObjectIdentityInterface;
use Symfony\Component\Security\Acl\Exception\InvalidDomainObjectException;
use Symfony\Component\Security\Acl\Voter\FieldVote;
use Symfony\Component\Security\Acl\Util\ClassUtils;

use Oro\Bundle\SecurityBundle\Acl\Domain\DomainObjectWrapper;
use Oro\Bundle\SecurityBundle\Acl\Domain\ObjectIdAccessor;
use Oro\Bundle\SecurityBundle\Annotation\Acl as AclAnnotation;

/**
 * This class provides a functionality to find ACL extension
 */
class AclExtensionSelector
{
    /** @var ObjectIdAccessor */
    protected $objectIdAccessor;

    /** @var AclExtensionInterface[] */
    protected $extensions = [];

    /** @var array [cache key => ACL extension, ...] */
    protected $localCache = [];

    /**
     * @param ObjectIdAccessor $objectIdAccessor
     */
    public function __construct(ObjectIdAccessor $objectIdAccessor)
    {
        $this->objectIdAccessor = $objectIdAccessor;
    }

    /**
     * Adds ACL extension
     *
     * @param AclExtensionInterface $extension
     */
    public function addAclExtension(AclExtensionInterface $extension)
    {
        $this->extensions[] = $extension;
    }

    /**
     * Gets ACL extension by its key
     *
     * @param string $extensionKey
     *
     * @return AclExtensionInterface|null
     */
    public function selectByExtensionKey($extensionKey)
    {
        foreach ($this->extensions as $extension) {
            if ($extension->getExtensionKey() === $extensionKey) {
                return $extension;
            }
        }

        return null;
    }

    /**
     * Gets ACL extension responsible for work with the given domain object
     *
     * @param mixed $val A domain object, ObjectIdentity, object identity descriptor (id:type) or ACL annotation
     *
     * @return AclExtensionInterface
     *
     * @throws InvalidDomainObjectException if ACL extension was not found for the given domain object
     */
    public function select($val)
    {
        if ($val === null) {
            return new NullAclExtension();
        }

        $type = $id = $fieldName = null;
        if (is_string($val)) {
            list($id, $type, $fieldName) = ObjectIdentityHelper::parseIdentityString($val);
        } elseif (is_object($val)) {
            list($id, $type, $fieldName) = $this->parseObject($val);
        }

        if ($type !== null) {
            $cacheKey = $this->buildCacheKey($id, $type, $fieldName);
            if (isset($this->localCache[$cacheKey])) {
                return $this->localCache[$cacheKey];
            }

            foreach ($this->extensions as $extension) {
                if ($extension->supports($type, $id)) {
                    $extension = $fieldName ? $extension->getFieldExtension() : $extension;
                    $this->localCache[$cacheKey] = $extension;

                    return $extension;
                }
            }
        }

        throw $this->createAclExtensionNotFoundException($val, $type, $id);
    }

    /**
     * Gets all ACL extension
     *
     * @return AclExtensionInterface[]
     */
    public function all()
    {
        return $this->extensions;
    }

    /**
     * @param object $object
     *
     * @return array
     */
    protected function parseObject($object)
    {
        $fieldName = null;
        if ($object instanceof FieldVote) {
            $fieldName = $object->getField();
            $object = $object->getDomainObject();
        }
        if ($object instanceof DomainObjectWrapper) {
            $object = $object->getObjectIdentity();
        }
        if ($object instanceof ObjectIdentityInterface) {
            $type = $object->getType();
            $id = $object->getIdentifier();
        } elseif ($object instanceof AclAnnotation) {
            $type = $object->getClass();
            if (empty($type)) {
                $type = $object->getId();
            }
            $id = $object->getType();
        } else {
            $type = ClassUtils::getRealClass($object);
            $id = $this->objectIdAccessor->getId($object);
        }

        return [$id, $type, $fieldName];
    }

    /**
     * @param mixed       $id
     * @param string      $type
     * @param string|null $fieldName
     *
     * @return string
     */
    protected function buildCacheKey($id, $type, $fieldName)
    {
        $cacheKey = ($id ? (string)$id : 'null') . '!' . $type;
        if ($fieldName) {
            $cacheKey .= '::' . $fieldName;
        }

        return $cacheKey;
    }

    /**
     * Creates an exception indicates that ACL extension was not found for the given domain object
     *
     * @param mixed      $val
     * @param string     $type
     * @param int|string $id
     *
     * @return InvalidDomainObjectException
     */
    protected function createAclExtensionNotFoundException($val, $type, $id)
    {
        $objInfo = is_object($val) && !($val instanceof ObjectIdentityInterface)
            ? get_class($val)
            : (string)$val;

        return new InvalidDomainObjectException(
            sprintf('An ACL extension was not found for: %s. Type: %s. Id: %s', $objInfo, $type, (string)$id)
        );
    }
}
