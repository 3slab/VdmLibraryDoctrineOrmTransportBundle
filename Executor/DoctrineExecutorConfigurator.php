<?php

/**
 * @package    3slab/VdmLibraryDoctrineOrmTransportBundle
 * @copyright  2020 Suez Smart Solutions 3S.lab
 * @license    https://github.com/3slab/VdmLibraryDoctrineOrmTransportBundle/blob/master/LICENSE
 */

namespace Vdm\Bundle\LibraryDoctrineOrmTransportBundle\Executor;

use Doctrine\Persistence\ObjectManager;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\Serializer\SerializerInterface;
use Vdm\Bundle\LibraryDoctrineOrmTransportBundle\Exception\InvalidIdentifiersCountException;
use Vdm\Bundle\LibraryDoctrineOrmTransportBundle\Exception\UnreadableEntityPropertyException;
use Vdm\Bundle\LibraryDoctrineOrmTransportBundle\Executor\AbstractDoctrineExecutor;

class DoctrineExecutorConfigurator
{
    /**
     * @param ObjectManager $objectManager
     */
    protected $objectManager;

    /**
     * @param LoggerInterface $logger
     */
    protected $logger;

    /**
     * @param SerializerInterface $serializer
     */
    protected $serializer;

    /**
     * @var array
     */
    protected $options = [];

    /**
     * @var PropertyAccessor
     */
    protected $accessor;

    public function __construct(
        ObjectManager $objectManager,
        LoggerInterface $logger,
        SerializerInterface $serializer,
        array $options
    ) {
        $this->objectManager = $objectManager;
        $this->logger        = $logger;
        $this->serializer    = $serializer;
        $this->options       = $options;

        $this->accessor = PropertyAccess::createPropertyAccessorBuilder()
            ->enableMagicCall()
            ->getPropertyAccessor()
        ;
    }

    /**
     * Configures the executor with specificities of each registered entity.
     *
     * @param  AbstractDoctrineExecutor $executor
     *
     * @return void
     */
    public function configure(AbstractDoctrineExecutor $executor): void
    {
        $repositories = [];

        $executor->setManager($this->objectManager);
        $executor->setLogger($this->logger);
        $executor->setSerializer($this->serializer);

        foreach (array_keys($this->options['entities']) as $entityFqcn) {
            $executor->addRepository($entityFqcn, $this->objectManager->getRepository($entityFqcn));

            // If a selector was defined, no need to check entity's identifiers mapping
            if (!empty($this->options['entities'][$entityFqcn]['selector'])) {
                $executor
                    ->setFetchMode($entityFqcn, AbstractDoctrineExecutor::SELECTION_MODE_FILTER)
                    ->setFilters($entityFqcn, $this->getSelectorFilter($entityFqcn))
                ;
            } else {
                // No selector was defined, we can try and guess how the entity works.
                $executor
                    ->setFetchMode($entityFqcn, AbstractDoctrineExecutor::SELECTION_MODE_IDENTIFER)
                    ->setIdentifier($entityFqcn, $this->guessConfiguration($entityFqcn))
                ;
            }
        }
    }

    /**
     * This method defines how to build a filter for an entity when the user provided an explicit configuration.
     *
     * @return array
     */
    protected function getSelectorFilter(string $entityFqcn): array
    {
        $selector = $this->options['entities'][$entityFqcn]['selector'];

        if (\is_string($selector)) {
            $selector = (array) $selector;
        }

        $filter = [];

        foreach ($selector as $key => $value) {
            if (\is_int($key)) {
                // Key is integer, getter matching the property is considered "natural".
                $this->assertPropertyIsReadable($entityFqcn, $value);

                $filter[$value] = $value;

                $this->logger->debug('Adding {entity}\'s {property} to filters', [
                    'entity'   => $entityFqcn,
                    'property' => $value,
                ]);
            } else {
                // otherwise, the getter is "unnatural" and is explicitely defined by the user. The key is the property, the value is the getter.
                $this->assertPropertyIsReadable($entityFqcn, $value);

                $filter[$key] = $value;

                $this->logger->debug('Adding {entity}\'s {property} ({method}) to filters', [
                    'entity'   => $entityFqcn,
                    'property' => $key,
                    'method'   => $value,
                ]);
            }
        }

        return $filter;
    }

    /**
     * This method guesses how to build a filter for the entity when the user didn't provide an explicit configuration.
     *
     * @return mixed
     */
    protected function guessConfiguration(string $entityFqcn)
    {
        $this->logger->info('No explicit configuration for entity {entity}, will try to guess how the entity works.', [
            'entity' => $entityFqcn,
        ]);

        $metadata    = $this->objectManager->getClassMetadata($entityFqcn);
        $identifiers = $metadata->getIdentifierFieldNames();

        // No identifier was defined, we have no way of selecting the entity → stop here.
        if (0 === \count($identifiers)) {
            $message = sprintf('Class %s does not define a unique identifier and you did not define any `selector` option. You need to define either so that the transport can try and fetch the entity prior to persisting it.', $this->options['entity']);
            
            $this->logger->error($message);

            throw new InvalidIdentifiersCountException($message);
        }

        // Composite identifier: ask user to fallback to selector mode so we have less code to maintain.
        if (\count($identifiers) > 1) {
            $message = sprintf('Composite identifiers are not supported (%s). Please use multiple selector.', implode(',', $identifiers));
            
            $this->logger->error($message);

            throw new InvalidIdentifiersCountException($message);
        }

        $identifier = $identifiers[0];

        $this->logger->info('Found unique identifier: {identifier}', [
            'identifier' => $identifier,
        ]);

        // Below this point we have one single identifier we can use. Last check:: can we read the identifier's value?
        $this->assertPropertyIsReadable($entityFqcn, $identifier);

        $this->logger->info('{identifier} is readable!', [
            'identifier' => $identifier,
        ]);

        return $identifier;
    }

    /**
     * Ensures the given property is readable on the subject entity.
     *
     * @param  string $entityFqcn
     * @param  string $property
     *
     * @throws UnreadableEntityPropertyException The given property isn't readable by the PropertyAccessor
     *
     * @return void
     */
    protected function assertPropertyIsReadable(string $entityFqcn, string $property): void
    {
        $reflection     = new ReflectionClass($entityFqcn);
        $entityInstance = $reflection->newInstanceWithoutConstructor();

        if (!$this->accessor->isReadable($entityInstance, $property)) {
            $message = sprintf('Cound not define a way to access property (%s) value in %s. Did you define a public getter?', $property, $entityFqcn);

            throw new UnreadableEntityPropertyException($message);
        }
    }
}