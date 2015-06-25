<?php

namespace Oro\Bundle\SecurityBundle\Owner\Metadata;

use Doctrine\Common\Collections\ArrayCollection;

use Oro\Bundle\SecurityBundle\Exception\NoSupportsMetadataProviderException;

class ChainMetadataProvider implements MetadataProviderInterface
{
    /**
     * @var ArrayCollection|MetadataProviderInterface[]
     */
    protected $providers;

    /**
     * @var MetadataProviderInterface
     */
    protected $supportedProvider;

    /**
     * @param MetadataProviderInterface[] $providers
     */
    public function __construct(array $providers = [])
    {
        $this->providers = new ArrayCollection($providers);
    }

    /**
     * Adds all providers that marked by tag: oro_security.owner.metadata_provider
     *
     * @param MetadataProviderInterface $provider
     */
    public function addProvider(MetadataProviderInterface $provider)
    {
        if (!$this->providers->contains($provider)) {
            $this->providers->add($provider);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function supports()
    {
        foreach ($this->providers as $provider) {
            if ($provider->supports()) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function getMetadata($className)
    {
        return $this->getSupportedProvider()->getMetadata($className);
    }

    /**
     * {@inheritDoc}
     */
    public function getBasicLevelClass()
    {
        return $this->getSupportedProvider()->getBasicLevelClass();
    }

    /**
     * {@inheritDoc}
     */
    public function getLocalLevelClass($deep = false)
    {
        return $this->getSupportedProvider()->getLocalLevelClass($deep);
    }

    /**
     * {@inheritDoc}
     */
    public function getGlobalLevelClass()
    {
        return $this->getSupportedProvider()->getGlobalLevelClass();
    }

    /**
     * {@inheritDoc}
     */
    public function getSystemLevelClass()
    {
        return $this->getSupportedProvider()->getSystemLevelClass();
    }

    /**
     * {@inheritDoc}
     */
    public function getMaxAccessLevel($accessLevel, $object = null)
    {
        return $this->getSupportedProvider()->getMaxAccessLevel($accessLevel, $object);
    }

    /**
     * @return MetadataProviderInterface
     *
     * @throws NoSupportsMetadataProviderException
     */
    protected function getSupportedProvider()
    {
        if ($this->supportedProvider) {
            return $this->supportedProvider;
        }

        foreach ($this->providers as $provider) {
            if ($provider->supports()) {
                $this->supportedProvider = $provider;

                return $this->supportedProvider;
            }
        }

        throw new NoSupportsMetadataProviderException('Found no supports provider in chain');
    }
}
