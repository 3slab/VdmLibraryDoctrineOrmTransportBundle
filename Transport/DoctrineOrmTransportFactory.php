<?php

/**
 * @package    3slab/VdmLibraryDoctrineOrmTransportBundle
 * @copyright  2020 Suez Smart Solutions 3S.lab
 * @license    https://github.com/3slab/VdmLibraryDoctrineOrmTransportBundle/blob/master/LICENSE
 */

namespace Vdm\Bundle\LibraryDoctrineOrmTransportBundle\Transport;

use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\Serializer\SerializerInterface as SymfonySerializer;
use Vdm\Bundle\LibraryDoctrineOrmTransportBundle\Exception\UndefinedEntityException;
use Vdm\Bundle\LibraryDoctrineOrmTransportBundle\Executor\AbstractDoctrineExecutor;
use Vdm\Bundle\LibraryDoctrineOrmTransportBundle\Executor\DoctrineExecutorConfigurator;

class DoctrineOrmTransportFactory implements TransportFactoryInterface
{
    protected const DSN_PROTOCOL_DOCTRINE = 'vdm+doctrine_orm://';
    protected const DSN_PATTERN_MATCHING  = '/(?P<protocol>[^:]+:\/\/)(?P<connection>.*)/';

    /**
     * @var LoggerInterface $logger
     */
    protected $logger;

    /**
     * @var ManagerRegistry $doctrine
     */
    protected $doctrine;

    /**
     * @var AbstractDoctrineExecutor $executor
     */
    protected $executor;

    /**
     * @var SymfonySerializer
     */
    protected $serializer;

    /**
     * @param ManagerRegistry $doctrine
     * @param AbstractDoctrineExecutor $executor
     * @param SymfonySerializer $serializer
     * @param LoggerInterface|null $vdmLogger
     */
    public function __construct(
        ManagerRegistry $doctrine,
        AbstractDoctrineExecutor $executor,
        SymfonySerializer $serializer,
        LoggerInterface $vdmLogger = null
    ) {
        $this->doctrine   = $doctrine;
        $this->executor   = $executor;
        $this->serializer = $serializer;
        $this->logger     = $vdmLogger ?? new NullLogger();
    }

    /**
     * Creates DoctrineTransport
     * @param  string              $dsn
     * @param  array               $options
     * @param  SerializerInterface $serializer
     *
     * @return TransportInterface
     */
    public function createTransport(string $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        if (empty($options['entities'])) {
            $errorMessage = sprintf(
                '%s requires that you define at least one entity value in the transport\'s options.',
                __CLASS__
            );
            throw new UndefinedEntityException($errorMessage);
        }

        unset($options['transport_name']);

        $manager = $this->getManager($dsn);

        $configurator = new DoctrineExecutorConfigurator($manager, $this->serializer, $options, $this->logger);
        $configurator->configure($this->executor);

        $doctrineSenderFactory = new DoctrineSenderFactory($this->executor);
        $doctrineSender        = $doctrineSenderFactory->createDoctrineSender();

        return new DoctrineTransport($doctrineSender, $this->logger);
    }

    /**
     * Tests if DSN is valid (protocol and valid Doctrine connection).
     *
     * @param string $dsn
     * @param array  $options
     *
     * @return bool
     */
    public function supports(string $dsn, array $options): bool
    {
        preg_match(static::DSN_PATTERN_MATCHING, $dsn, $match);

        if (0 === strpos($match['protocol'], static::DSN_PROTOCOL_DOCTRINE)) {
            // No need to put it in a variable now. If the connection doesn't exist, Doctrine will throw an exception
            $this->getManager($dsn);

            // If we passe the if statement, and getManager(), we're good.
            return true;
        }

        // Otherwise, tranport not supported.
        return false;
    }

    /**
     * Returns the manager from Doctrine registry.
     *
     * @param  string $dsn
     *
     * @throws InvalidArgumentException invalid connection
     *
     * @return ObjectManager
     */
    protected function getManager(string $dsn): ObjectManager
    {
        preg_match(static::DSN_PATTERN_MATCHING, $dsn, $match);

        $match['connection'] = $match['connection'] ?: 'default';

        $manager = $this->doctrine->getManager($match['connection']);

        return $manager;
    }
}
