<?php

/**
 * @package    3slab/VdmLibraryDoctrineOrmTransportBundle
 * @copyright  2020 Suez Smart Solutions 3S.lab
 * @license    https://github.com/3slab/VdmLibraryDoctrineOrmTransportBundle/blob/master/LICENSE
 */

namespace Vdm\Bundle\LibraryDoctrineOrmTransportBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Vdm\Bundle\LibraryDoctrineOrmTransportBundle\Executor\AbstractDoctrineExecutor;
use Vdm\Bundle\LibraryDoctrineOrmTransportBundle\Executor\DefaultDoctrineExecutor;
use Vdm\Bundle\LibraryDoctrineOrmTransportBundle\Transport\DoctrineOrmTransportFactory;

class SetDoctrineExecutorCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->has(DoctrineOrmTransportFactory::class)) {
            return;
        }

        $taggedServicesDoctrineExecutor = $container->findTaggedServiceIds('vdm_library.doctrine_executor');

        // Unload default doctrine executor if multiple doctrineExecutor
        if (count($taggedServicesDoctrineExecutor) > 1) {
            foreach ($taggedServicesDoctrineExecutor as $id => $tags) {
                if ($id === DefaultDoctrineExecutor::class) {
                    unset($taggedServicesDoctrineExecutor[$id]);
                    break;
                }
            }
        }

        $container->setAlias(AbstractDoctrineExecutor::class, array_key_first($taggedServicesDoctrineExecutor));
    }
}
