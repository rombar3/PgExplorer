<?php

namespace rombar\PgExplorerBundle\DependencyInjection\Compiler;
 
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
 
class AddDbalCacheConfigurationPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $id = 'doctrine.dbal.default_connection.configuration';
 
        if ($container->hasDefinition($id)) {
            $container->getDefinition($id)->addMethodCall('setResultCacheImpl', array(new Reference('doctrine.orm.default_result_cache')));
        }
    }
}
?>
