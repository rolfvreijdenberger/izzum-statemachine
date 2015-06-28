<?php
namespace izzum\statemachine;

/**
 * EntityBuilder is an object that builds an entity (an application domain
 * specific model) for a Context object so your statemachine can interact with your
 * domain model.
 * 
 * The entity returned is the application domain specific object that will be
 * injected in the Rules and Commands for a specific statemachine (eg: Order)
 * and can implement event handlers and callables.
 * A typical statemachine would use a subclass of this builder.
 *
 * The Context can build the entity by using the associated Identity object of the
 * Context. the entity can be obtained via the factory method EntityBuilder::getEntity().
 *
 * Of course, a specific Rule or Command can just accept the Identifier directly
 * and use the entity_id to generate the domain object itself. But this would
 * also mean that the Rules and Commands will not be specific to the applications'
 * problem domain but to that of the statemachine (and therefore possibly less
 * reusable). It is therefore advisable to let a builder build your domain model.
 *
 * The Context instance is configured with this builder class by default. It can
 * be configured with a subclass for your application domain if necessary (and the
 * configuration can be done automatically by using a concrete instance of the AbstractFactory)
 *
 * This class implements caching of the domain model/entity returned.
 * The builder is reusable for different statemachines (that have
 * a different Entity by definition). The cache will be rebuilt whenever a new
 * Identifier object is passed to the 'getEntity' method.
 *
 * This specific class (in contrast to subclasses) returns the Identifier itself instead of a domain model. 
 * This is useful because it allows us to test a lot of scenarios without side effects.
 * It also allows us to use the Identifier object for our rules and commands, so
 * you do not necessarily have to write your own entitybuilder.
 *
 * This is a prime candidate to be overriden by subclasses (if need be).
 * Subclass this class and override the 'build' method to return a
 * domain specific object of choice. The building of that domain object depends
 * on the information in the Context, specifically the entity_id.
 *
 * the builder can be configured via dependency injection at creation time so
 * the builder can make use of the injected data when it is called via 'getEntity'.
 *
 * An example of a builder would be an 'EntityBuilderOrder' class,
 * which would be used for a statemachine that uses rules and commands to act
 * upon an 'Order' object from your applation domain.
 *
 * @see Context::getEntity()
 * @link https://en.wikipedia.org/wiki/Builder_pattern
 *      
 * @author Rolf Vreijdenberger
 *        
 */
class EntityBuilder {
    
    /**
     * a cached instance of the built entity object
     * 
     * @var Object
     */
    protected $entity;
    
    /**
     * a cached instance of the used Identifier.
     * 
     * @var Identifier
     */
    protected $identifier;

    /**
     * Gets an application domain specific model of choice, as implemented by a
     * subclass.
     *
     * @param Identifier $identifier            
     * @param boolean $create_fresh_entity
     *            optional. if true, then a new instance is always created,
     *            else it might be cached if used for the same Identifier
     *            (performance).
     *            Since a statemachine might run multiple transitions in memory
     *            and alter the data in the persistence layer,
     *            there might be a need to refresh the entity (create it again)
     *            if the in memory object and the data
     *            in the persistence layer are not synchronized. this might
     *            cause rules or commands to act on
     *            in-memory data while it should use the persisted data.
     *            (an ORM should handle this automatically)
     *            
     * @return Object an object of any type, depending on the statemachine.
     *         This object will be used by the Rule and Command that go with a
     *         certain statemachine. It will be injected in the constructor of
     *         the
     *         Rule and Command.
     *         It implements caching so we always get the same entity instance
     *         on
     *         each call of 'getEntity' with the same Context
     * @see Context::getEntity()
     * @throws Exception
     */
    final public function getEntity(Identifier $identifier, $create_fresh_entity = false)
    {
        try {
            // lazy loading with caching.
            // we cache the context so we can be sure to provide
            // a new reference to the entity when the builder is used on a new
            // Identifier.
            if ($this->entity === null || $this->identifier !== $identifier || $create_fresh_entity == true) {
                // crate new entity and build cache
                $this->entity = $this->build($identifier);
                $this->identifier = $identifier;
            }
            return $this->entity;
        } catch(Exception $e) {
            // already a statemachine exception, just rethrow, it is logged
            throw $e;
        } catch(\Exception $e) {
            // a non statemachine type exception, wrap it so it is logged and
            // throw
            $e = new Exception($e->getMessage(), Exception::BUILDER_FAILURE, $e);
            throw $e;
        }
    }

    /**
     * the actual building function.
     * Override this method to return an application specific domain model.
     *
     * In an overriden function it is possible to use the state of the concrete
     * builder itself, which can be passed in via dependency injection at
     * construction time, so we have additional information on how to build the
     * domain object.
     *
     * @param Identifier $identifier            
     * @return Object an object of any type defined by the subclass
     */
    protected function build(Identifier $identifier)
    {
        // the default implementation returns an Identifier, which holds an id
        // that can be used in your rules and commands to build your domain
        // logic or build your domain object. specialized builders return a domain
        // model that will be accepted by your specialized rules/commands for
        // your application.
        return $identifier;
    }

    /**
     * returns the string representation
     * 
     * @return string
     */
    public function toString()
    {
        return get_class($this);
    }

    /**
     * returns the string representation
     * 
     * @return string
     */
    public function __toString()
    {
        return $this->toString();
    }
}
