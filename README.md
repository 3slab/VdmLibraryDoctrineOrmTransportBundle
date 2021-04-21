# Vdm Library Doctrine Orm Transport

## Configuration reference

There are two parts ton configure: the transport, and Doctrine's behaviour.

### Transport

In `messenger.yaml`:

```yaml
framework:
    messenger:
        transports:
            producer:
                dsn: vdm+doctrine_orm://mycustomconnection
                options:
                    entities:
                        App\Entity\Demande:
                            selector: RefDemande
```

Configuration | Description
--- | ---
dsn | Always starts with `vdm+doctrine://`. You can specify the connection to use with `vdm+doctrine://mycustomconnection` (fits into `doctrine.orm.xxx_entity_manager`). If you use the default connection, you can use only `vdm+doctrine://`
options.entities | Array of entities to register. At least one entity must be declared.
options.entities.FQCN.selector | (optional) Define how the executor will try and fetch a pre-existing entity before persisting (see below)

### No data loss policy

By default, the executor won't overwrite a non-null field with null. If you need to bypass this behaviour, your entity should implement `Vdm\Bundle\LibraryBundle\Entity\NullableFieldsInterface` and declare `getNullableFields()`:

```php

use Vdm\Bundle\LibraryBundle\Entity\NullableFieldsInterface;

class Foo implements NullableFieldsInterface
{
    public $nonNullable;
    public $nullable;
    public $otherNullable;

    public static function getNullableFields(): array
    {
        return [
            'nullable',
            'otherNullable',
        ];
    }
}
```

__NOTE__: the values returned by `getNullableFields()` should be something the [PropertyAccess component](https://symfony.com/doc/5.0/components/property_access.html#reading-from-objects) can resolve.

## Fetching pre-existing entity

Before persisting anything, this transport will always try to find an existing entity. You need to tell it how to proceed. You have several ways of doing it.

### The natural way

It means that your entity bears a unique identifier value, such as:
```php
    /**
     * @ORM\Id()
     */
    private $id;
```

If this value is carried by the incoming message, then you have nothing to configure. The only responsability on your end is making sure there is a public getter for this property (if there isn't you'll get a clear error message anyway).

__Note__: in this case, the sender will use the  `find` method on the repository.

### Multifield with natural getters

In case you don't have a mono-column primary key (ex: no key at all or composite key), you can turn to another approach and tell the executor which fields should be used to retrieve a pre-existing entity. For instance, if your entity has two fields representing its identity (let's say `code` and `hash`), and they both have a natural getter (i.e. `getCode` and `getHash`), then you need to configure the options like this:

```yaml
framework:
    messenger:
        transports:
            producer:
                dsn: vdm+doctrine_orm://
                options:
                    entities:
                        App\Entity\Demande:
                            selector:
                                - code
                                - hash
```

Under the hood, the repository will be called like:
```php
    $repo->findOneBy([ 'code' => $yourEntity->getCode(), 'hash' => $yourEntity->getHash() ])
```

__Note__: Notice the `findOneBy`. The sender will use the first matching entity. It's your responsability to provide a unique set of filter.

### Multifield with non-natural getters

In case the fields related to the identity have unnatural getters (ex: legacy code, multilingual code), you can define which getter to use to fetch the appropriate property. Let's say the identity is made of two fields: `label` and `hash`, which respective getters are `getLibelle()` and `hash()`. You will need configure the sender as such:

```yaml
framework:
    messenger:
        transports:
            producer:
                dsn: vdm+doctrine_orm://
                options:
                    entities:
                        App\Entity\Demande:
                            selector:
                                label: getLibelle
                                hash: hash
```

Under the hood, the repository will be called like:
```php
    $repo->findOneBy([ 'label' => $yourEntity->getLibelle(), 'hash' => $yourEntity->hash() ])
```

The same policy as natural getters apply: you have to make sure it returns something as unique as possible.

You can define several entities at once, and mix natural and non-natural getters. However, you will have to prefix your natural getters with integer keys. The key itself doesn't matter (as long as you don't create duplicates), it just needs to be an integer. If the key is an integer, the getter will be guessed. Otherwise, the getter will be what you provide

```yaml
framework:
    messenger:
        transports:
            producer:
                dsn: vdm+doctrine_orm://
                options:
                    entities:
                        App\Entity\Foo:
                            selector:
                                0: code # hack to mix natural and non-natural getters
                                label: getLibelle #non natural getter
                                hash: hash #non natural getter
                        App\Entity\Bar: ~ # Bar has a single-field identity (id) with natural getter, no configuration needed
                        App\Entity\Baz:
                            selector:
                                - reference # Baz uses a filter based on its reference with natural getter (getReference)
```

Under the hood, the repository will fetch the entities like this:
```php
    // Foo
    $repo->findOneBy([ 'code' => $foo->getCode(), 'label' => $foo->getLibelle(), 'hash' => $foo->hash() ]);

    // Bar
    $repo->find($bar->getId());

    // Baz
    $repo->findOneBy([ 'reference' => $baz->getReference() ]);
```

## Deserializaion

For the transport to know to which class the payload should be deserialized, you must provide the entity's fully qualified class name in the message's metadata, with key `entity`. Example `new Metadata('entity', 'App\Entity\Foo')`.

## Limitations

You cannot use different connections for different entities within one single transport. Should you have such a need, you should define one transport per connection, extends the library's Message (one per producer) and route the correct message to the correct producer.