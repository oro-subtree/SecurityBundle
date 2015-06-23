<?php

namespace Oro\Bundle\SecurityBundle\Tests\Unit\Owner\Metadata;

use Oro\Bundle\SecurityBundle\Owner\Metadata\OwnershipMetadata;

class OwnershipMetadataTest extends \PHPUnit_Framework_TestCase
{
    public function testConstructorWithoutParameters()
    {
        $metadata = new OwnershipMetadata();
        $this->assertFalse($metadata->hasOwner());
        $this->assertFalse($metadata->isGlobalLevelOwned());
        $this->assertFalse($metadata->isLocalLevelOwned());
        $this->assertFalse($metadata->isBasicLevelOwned());
        $this->assertEquals('', $metadata->getOwnerFieldName());
        $this->assertEquals('', $metadata->getOwnerColumnName());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testConstructorWithInvalidOwnerType()
    {
        new OwnershipMetadata('test');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testConstructorWithoutOwnerFieldName()
    {
        new OwnershipMetadata('ORGANIZATION');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testConstructorWithoutOwnerIdColumnName()
    {
        new OwnershipMetadata('ORGANIZATION', 'org');
    }

    public function testOrganizationOwnership()
    {
        $metadata = new OwnershipMetadata('ORGANIZATION', 'org', 'org_id');
        $this->assertEquals(OwnershipMetadata::OWNER_TYPE_ORGANIZATION, $metadata->getOwnerType());
        $this->assertTrue($metadata->hasOwner());
        $this->assertTrue($metadata->isGlobalLevelOwned());
        $this->assertFalse($metadata->isLocalLevelOwned());
        $this->assertFalse($metadata->isBasicLevelOwned());
        $this->assertTrue($metadata->isOrganizationOwned());
        $this->assertFalse($metadata->isBusinessUnitOwned());
        $this->assertFalse($metadata->isUserOwned());
        $this->assertEquals('org', $metadata->getOwnerFieldName());
        $this->assertEquals('org_id', $metadata->getOwnerColumnName());
    }

    public function testBusinessUnitOwnership()
    {
        $metadata = new OwnershipMetadata('BUSINESS_UNIT', 'bu', 'bu_id');
        $this->assertEquals(OwnershipMetadata::OWNER_TYPE_BUSINESS_UNIT, $metadata->getOwnerType());
        $this->assertTrue($metadata->hasOwner());
        $this->assertFalse($metadata->isGlobalLevelOwned());
        $this->assertTrue($metadata->isLocalLevelOwned());
        $this->assertFalse($metadata->isBasicLevelOwned());
        $this->assertFalse($metadata->isOrganizationOwned());
        $this->assertTrue($metadata->isBusinessUnitOwned());
        $this->assertFalse($metadata->isUserOwned());
        $this->assertEquals('bu', $metadata->getOwnerFieldName());
        $this->assertEquals('bu_id', $metadata->getOwnerColumnName());
    }

    public function testUserOwnership()
    {
        $metadata = new OwnershipMetadata('USER', 'usr', 'user_id');
        $this->assertEquals(OwnershipMetadata::OWNER_TYPE_USER, $metadata->getOwnerType());
        $this->assertTrue($metadata->hasOwner());
        $this->assertFalse($metadata->isGlobalLevelOwned());
        $this->assertFalse($metadata->isLocalLevelOwned());
        $this->assertTrue($metadata->isBasicLevelOwned());
        $this->assertFalse($metadata->isOrganizationOwned());
        $this->assertFalse($metadata->isBusinessUnitOwned());
        $this->assertTrue($metadata->isUserOwned());
        $this->assertEquals('usr', $metadata->getOwnerFieldName());
        $this->assertEquals('user_id', $metadata->getOwnerColumnName());
    }

    public function testSerialization()
    {
        $metadata = new OwnershipMetadata('ORGANIZATION', 'org', 'org_id');
        $data = serialize($metadata);
        $metadata = new OwnershipMetadata();
        $this->assertFalse($metadata->isGlobalLevelOwned());
        $this->assertFalse($metadata->isOrganizationOwned());
        $this->assertEquals('', $metadata->getOwnerFieldName());
        $this->assertEquals('', $metadata->getOwnerColumnName());
        $metadata = unserialize($data);
        $this->assertTrue($metadata->isOrganizationOwned());
        $this->assertEquals('org', $metadata->getOwnerFieldName());
        $this->assertEquals('org_id', $metadata->getOwnerColumnName());
    }

    public function testSetState()
    {
        $metadata = new OwnershipMetadata('ORGANIZATION', 'org', 'org_id');
        $restoredMetadata = $metadata->__set_state(
            [
                'ownerType' => $metadata->getOwnerType(),
                'ownerFieldName' => $metadata->getOwnerFieldName(),
                'ownerColumnName' => $metadata->getOwnerColumnName(),
            ]
        );
        $this->assertEquals($metadata, $restoredMetadata);
    }
}
