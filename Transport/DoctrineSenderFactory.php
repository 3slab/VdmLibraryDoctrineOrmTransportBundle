<?php

/**
 * @package    3slab/VdmLibraryDoctrineOrmTransportBundle
 * @copyright  2020 Suez Smart Solutions 3S.lab
 * @license    https://github.com/3slab/VdmLibraryDoctrineOrmTransportBundle/blob/master/LICENSE
 */

namespace Vdm\Bundle\LibraryDoctrineOrmTransportBundle\Transport;

use Vdm\Bundle\LibraryDoctrineOrmTransportBundle\Executor\AbstractDoctrineExecutor;

class DoctrineSenderFactory
{
    /**
     * @var AbstractDoctrineExecutor
     */
    protected $executor;

    /**
     * DoctrineSenderFactory constructor.
     * @param AbstractDoctrineExecutor $executor
     */
    public function __construct(AbstractDoctrineExecutor $executor)
    {
        $this->executor = $executor;
    }

    /**
     * Created the DoctrineSender object based on messenger configuration.
     *
     * @return DoctrineSender
     */
    public function createDoctrineSender(): DoctrineSender
    {
        return new DoctrineSender($this->executor);
    }
}
