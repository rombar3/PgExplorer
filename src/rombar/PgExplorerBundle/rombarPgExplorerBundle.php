<?php

namespace rombar\PgExplorerBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use rombar\PgExplorerBundle\DependencyInjection\Compiler\AddDbalCacheConfigurationPass;

class rombarPgExplorerBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);
     
        $container->addCompilerPass(new AddDbalCacheConfigurationPass());
    }
}
