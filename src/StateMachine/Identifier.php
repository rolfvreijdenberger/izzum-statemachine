<?php

namespace Izzum\StateMachine;

/**
 * an instance of Identifier uniquely identifies the statemachine to be used.
 *
 * A statemachine is always uniquely identified by the combination of an entity
 * id and a machine name (that provides the relation to the statemachine the
 * entity is governed by).
 *
 * the machine name should be a 'machine readable' string, since it will be stored in different
 * backends and might be used as a key there (eg: in redis).
 *
 * The entity id is something that uniquely identifies a domain model. Probably
 * something that is stored in your application, like a primary key in a table, a GUID or a hash.
 *
 * This object thus stores the minimum data needed from other processes in your
 * application domain to succesfully work with the statemachine.
 *
 * @author Rolf Vreijdenberger
 *
 */
class Identifier implements \Stringable
{
    public const NULL_ENTITY_ID = "-1";
    public const NULL_STATEMACHINE = 'null-machine';

    /**
     * an entity id that represents the unique identifier for an application
     * domain specific object (entity) like 'Order', 'Customer' etc.
     *
     * @var string
     */
    protected string $entityId;

    /**
     * @param mixed $entityId
     *            the id of the domain specific entity (it will internally be
     *            converted to a string)
     * @param string $machineName
     *            the statemachine that governs the state behaviour for this
     *            entity (eg 'order'), used in conjunction with the entity id
     *            to define what a statemachine is about
     */
    public function __construct($entityId, protected string $machineName)
    {
        // convert $entityId to string (it will likely be an int but a string
        // gives more flexibility)
        $this->setEntityId($entityId);
    }

    /**
     * gets the statemachine name that handles the entity
     */
    public function getMachine(): string
    {
        return $this->machineName;
    }

    /**
     * set the id of the domain specific entity (it will internally be converted to a string)
     */
    public function setEntityId($entityId): void
    {
        $this->entityId = trim("$entityId");
    }

    /**
     * gets the entity id that represents the unique identifier for the
     * application domain specific model.
     */
    public function getEntityId(): string
    {
        return $this->entityId;
    }

    /**
     * get the unique identifier representation for an Identifier, which
     * consists of the machine name and the entity_id in parseable form.
     */
    public function getId(bool $readable = false): string
    {
        if ($readable) {
            $output = "machine: '" . $this->getMachine() . "', id: '" . $this->getEntityId() . "'";
        } else {
            $output = $this->getMachine() . "_" . $this->getEntityId();
        }
        return $output;
    }

    public function toString(): string
    {
        return static::class . ' ' . $this->getId(true);
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
