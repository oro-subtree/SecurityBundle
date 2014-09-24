<?php

namespace Oro\Bundle\SecurityBundle\DependencyInjection\Security\Factory;

use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\RememberMeFactory;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\DefinitionDecorator;
use Symfony\Component\DependencyInjection\Reference;

class OrganizationRememberMeFactory extends RememberMeFactory
{
    /**
     * {@inheritdoc}
     */
    public function create(ContainerBuilder $container, $id, $config, $userProvider, $defaultEntryPoint)
    {
        // authentication provider
        $authProviderId = 'oro_security.authentication.provider.organization_rememberme.' . $id;
        $container
            ->setDefinition(
                $authProviderId,
                new DefinitionDecorator('oro_security.authentication.provider.organization_rememberme')
            )
            ->addArgument($config['key'])
            ->addArgument($id);


        $templateId = 'oro_security.authentication.organization_rememberme.services.simplehash';
        $rememberMeServicesId = $templateId . '.' . $id;

        if ($container->hasDefinition('security.logout_listener.' . $id)) {
            $container
                ->getDefinition('security.logout_listener.' . $id)
                ->addMethodCall('addHandler', array(new Reference($rememberMeServicesId)));
        }

        $rememberMeServices = $container->setDefinition($rememberMeServicesId, new DefinitionDecorator($templateId));
        $rememberMeServices->replaceArgument(1, $config['key']);
        $rememberMeServices->replaceArgument(2, $id);

        if (isset($config['token_provider'])) {
            $rememberMeServices->addMethodCall(
                'setTokenProvider',
                array(
                    new Reference($config['token_provider'])
                )
            );
        }

        // remember-me options
        $rememberMeServices->replaceArgument(3, array_intersect_key($config, $this->options));

        // attach to remember-me aware listeners
        $rememberMeServices->replaceArgument(
            0,
            $this->getUserProviders($container, $config, $id, $rememberMeServicesId)
        );

        // remember-me listener
        $listenerId = 'oro_security.authentication.listener.organization_rememberme.' . $id;
        $listener = $container->setDefinition(
            $listenerId,
            new DefinitionDecorator('oro_security.authentication.listener.organization_rememberme')
        );
        $listener->replaceArgument(1, new Reference($rememberMeServicesId));

        return array($authProviderId, $listenerId, $defaultEntryPoint);
    }

    /**
     * {@inheritdoc}
     */
    public function getKey()
    {
        return 'organization-remember-me';
    }

    /**
     * @param ContainerBuilder $container
     * @param array            $config
     * @param string           $id
     * @param string           $rememberMeServicesId
     *
     * @return array
     */
    protected function getUserProviders(ContainerBuilder $container, $config, $id, $rememberMeServicesId)
    {
        $userProviders = [];
        foreach ($container->findTaggedServiceIds('security.remember_me_aware') as $serviceId => $attributes) {
            foreach ($attributes as $attribute) {
                if (!isset($attribute['id']) || $attribute['id'] !== $id) {
                    continue;
                }

                if (!isset($attribute['provider'])) {
                    throw new \RuntimeException(
                        'Each "security.remember_me_aware" tag must have a provider attribute.'
                    );
                }

                $userProviders[] = new Reference($attribute['provider']);
                $container
                    ->getDefinition($serviceId)
                    ->addMethodCall('setRememberMeServices', array(new Reference($rememberMeServicesId)));
            }
        }
        if ($config['user_providers']) {
            $userProviders = array();
            foreach ($config['user_providers'] as $providerName) {
                $userProviders[] = new Reference('security.user.provider.concrete.' . $providerName);
            }
        }
        if (count($userProviders) === 0) {
            throw new \RuntimeException(
                'You must configure at least one remember-me aware listener (such as form-login)
                for each firewall that has organization-remember-me enabled.'
            );
        }

        return $userProviders;
    }
}
