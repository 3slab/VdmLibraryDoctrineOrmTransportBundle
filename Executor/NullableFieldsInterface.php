<?php

/**
 * @package    3slab/VdmLibraryDoctrineOrmTransportBundle
 * @copyright  2020 Suez Smart Solutions 3S.lab
 * @license    https://github.com/3slab/VdmLibraryDoctrineOrmTransportBundle/blob/master/LICENSE
 */

namespace Vdm\Bundle\LibraryDoctrineOrmTransportBundle\Executor;

/**
 * Interface NullableFieldsInterface
 * @package Vdm\Bundle\LibraryDoctrineOrmTransportBundle\Executor
 */
interface NullableFieldsInterface
{
    public static function getNullableFields(): array;
}
