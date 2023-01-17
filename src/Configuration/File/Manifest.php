<?php

declare(strict_types=1);

namespace Keboola\OutputMapping\Configuration\File;

use Keboola\OutputMapping\Configuration\Configuration;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

class Manifest extends Configuration
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('file');
        $root = $treeBuilder->getRootNode();
        self::configureNode($root);
        return $treeBuilder;
    }

    public static function configureNode(ArrayNodeDefinition $node): void
    {
        $node
            ->children()
                ->arrayNode('tags')->prototype('scalar')->end()->end()
                ->booleanNode('is_public')->defaultValue(false)->end()
                ->booleanNode('is_permanent')->defaultValue(false)->end()
                ->booleanNode('is_encrypted')->defaultValue(true)->end()
                ->booleanNode('notify')->defaultValue(false)->end()
            ;
    }
}
