<?php

/*
 * This file is part of the ONGR package.
 *
 * (c) NFQ Technologies UAB <info@nfq.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ONGR\ApiBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Validates and merges configuration from your app/config files.
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('ongr_api');

        $rootNode
            ->children()
                ->arrayNode('authorization')
                    ->addDefaultsIfNotSet()
                    ->validate()
                        ->ifTrue(
                            function ($node) {
                                return $node['enabled'] && !isset($node['secret']);
                            }
                        )
                        ->thenInvalid("'secret' for api must be set if authorization is enabled.")
                    ->end()
                    ->children()
                        ->booleanNode('enabled')
                            ->defaultFalse()
                            ->info('Set to true if authorization needs to be enabled.')
                        ->end()
                        ->scalarNode('secret')
                            ->info('Secret used for authentication')
                        ->end()
                    ->end()
                ->end()
                ->booleanNode('version_in_url')
                    ->defaultFalse()
                    ->info(
                        'By default versions is only handled via Accept headers. '.
                        'In addition there is possible to add it in the url.'
                    )
                ->end()
                ->scalarNode('output_format')
                    ->defaultValue('json')
                    ->example('json')
                    ->info('Default encoding type. Changed through headers')
                    ->validate()
                        ->ifNotInArray(['json', 'xml'])
                        ->thenInvalid(
                            'Currently valid encoders are only json and xml. '.
                            'For more you can inject your own serializer.'
                        )
                    ->end()
                ->end()
                ->append($this->getVersionsNode())
            ->end();

        return $treeBuilder;
    }

    /**
     * Builds configuration tree for endpoint versions.
     *
     * @return NodeDefinition
     */
    private function getVersionsNode()
    {
        $builder = new TreeBuilder();
        $node = $builder->root('versions');

        $node
            ->info('Defines api versions.')
            ->useAttributeAsKey('version')
            ->prototype('array')
                ->children()
                    ->scalarNode('versions')
                        ->info('Defines a version for current api endpoints.')
                        ->example('v1')
                    ->end()
                ->append($this->getEndpointNode())
                ->end()
            ->end();

        return $node;
    }

    /**
     * Builds configuration tree for endpoints.
     *
     * @return NodeDefinition
     */
    public function getEndpointNode()
    {
        $builder = new TreeBuilder();
        $node = $builder->root('endpoints');

        $node
            ->info('Defines version endpoints.')
            ->useAttributeAsKey('endpoint')
            ->prototype('array')
                ->children()
                    ->scalarNode('endpoint')
                        ->info('Endpoint name (will be included in url (e.g. products))')
                        ->example('products')
                    ->end()
                    ->scalarNode('repository')
                        ->isRequired()
                        ->info('Document service from Elasticsearch bundle which will be used for data fetching')
                        ->example('es.manager.default.products')
                    ->end()
                    ->arrayNode('methods')
                        ->defaultValue(
                            [
                                Request::METHOD_POST,
                                Request::METHOD_GET,
                            ]
                        )
                        ->prototype('scalar')
                        ->validate()
                        ->ifNotInArray(
                            [
                                Request::METHOD_HEAD,
                                Request::METHOD_POST,
                                Request::METHOD_PATCH,
                                Request::METHOD_GET,
                                Request::METHOD_PUT,
                                Request::METHOD_DELETE,
                            ]
                        )
                        ->thenInvalid(
                            'Invalid HTTP method used! Please check your ongr_api endpoint configuration.'
                        )
                        ->end()
                    ->end()
                    ->end()
                    ->booleanNode('allow_extra_fields')
                        ->defaultFalse()
                        ->info(
                            'Allows to pass unknown fields to an api. '.
                            'Make sure you have configured elasticsearch respectively.'
                        )
                    ->end()
                    ->arrayNode('allow_fields')
                        ->defaultValue([])
                        ->info('A list off a allowed fields to operate through api for a document.')
                        ->prototype('scalar')->end()
                    ->end()
                    ->booleanNode('allow_get_all')
                    ->defaultFalse()
                    ->info(
                        'Allows to use `_scroll` elasticsearch api to get all documents from a type.'
                    )
                    ->end()
                ->end()
            ->end();

        return $node;
    }
}
