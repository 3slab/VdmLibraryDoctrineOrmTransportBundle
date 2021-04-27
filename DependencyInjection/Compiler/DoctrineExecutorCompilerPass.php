<?php

/**
 * @package    3slab/VdmLibraryDoctrineOrmTransportBundle
 * @copyright  2020 Suez Smart Solutions 3S.lab
 * @license    https://github.com/3slab/VdmLibraryDoctrineOrmTransportBundle/blob/master/LICENSE
 */

namespace Vdm\Bundle\LibraryDoctrineOrmTransportBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Vdm\Bundle\LibraryDoctrineOrmTransportBundle\Executor\DoctrineExecutorRegistry;

/**
 * Class DoctrineExecutorCompilerPass
 * @package Vdm\Bundle\LibraryDoctrineOrmTransportBundle\DependencyInjection\Compiler
 */
class DoctrineExecutorCompilerPass implements CompilerPassInterface
{
    /**
     * @param ContainerBuilder $container
     * @throws \Exception
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition(DoctrineExecutorRegistry::class)) {
            return;
        }

        $definition = $container->getDefinition(DoctrineExecutorRegistry::class);
        $taggedServices = $container->findTaggedServiceIds('vdm_library.doctrine_orm_executor');
        foreach ($taggedServices as $id => $tags) {
            $definition->addMethodCall('addExecutor', [new Reference($id), $id]);
        }
    }
}
