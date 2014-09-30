<?php
namespace izzum\statemachine;
/**
 * EntityBuilder is an object that builds an entity (an application domain specific
 * model) that a Context must return.
 * It can be obtained via the factory method getEntity(). 
 * 
 * The entity returned is the application domain specific object that will be 
 * used by Rules and Commands for a specific statemachine (eg: Order). 
 * A typical statemachine would use a subclass of this builder.
 * 
 * Of course, a specific Rule or Command can just accept the Entity directly
 * and use the entity_id to generate the domain object itself. But this would also
 * mean that the Rules and Commands will not be specific to the applications' problem
 * domain but to that of the statemachine (and therefore possibly less reusable).
 * 
 * The Context instance is configured with this builder class by default. It can be configured
 * with a subclass if necessary. 
 * 
 * This class implements caching of the entity returned for a specific Entity.
 * This means that the builder is reusable for different statemachines (that have
 * a different Entity by definition). The cache will be rebuilt whenever a new
 * Context object is passed to the 'getEntity' method.
 * 
 * This class returns the Entity itself. This is 
 * useful because it allows us to test a lot of scenarios without side effects.
 * 
 * This is a prime candidate to be overriden by subclasses (if need be).
 * Subclass this class and override the 'build' method to return a
 * domain specific object of choice. The building of that domain objects depends
 * on the information in the Entity, specifically the entity_id.
 * 
 * the builder can be configured via dependency injection at creation time so 
 * the builder can make use of this data when it is called via 'getEntity'.
 * 
 * An example of a builder would be an 'EntityBuilderOrder' class,
 * which would be used for an Order statemachine.
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
     * @var Object
     */
    protected $entity;
    
    /**
     * a cached instance of the used Context.
     * @var Context
     */
    protected $context;
    
    /**
     * Gets an application domain specific model of choice, as implemented by
     * a subclass.
     * 
     * @return Object an object of any type, depending on the statemachine.
     *     This object will be used by the Rule and Command that go with a 
     *     certain statemachine. It will be injected in the constructor of the
     *     Rule and Command.
     *     It implements caching so we always get the same entity instance on 
     *     each call of 'getEntity' with the same Context
     * @see Context::getEntity()
     * @throws Exception
     */
    public final function getEntity(Context $context)
    {
        try {
            //lazy loading with caching.
            //we cache the context so we can be sure to provide
            //a new reference to the entity when the builder is used on a new Context.
            if($this->entity === null || $this->context !== $context)
            {
                //build cache
                $this->entity = $this->build($context);
                $this->context = $context;
            }
            return $this->entity;
        } catch (Exception $e) {
            //already a statemachine exception, just rethrow, it is logged
            throw $e;
        } catch (\Exception $e) {
            //a non statemachine type exception, wrap it so it is logged and throw
            $e = new Exception($e->getMessage(), Exception::BUILDER_FAILURE, $e);
            throw $e;
        }
    }
    
    
    
    
    /**
     * the actual building function. Override this one to return a different 
     * application specific domain model.
     * 
     * In an overriden function it is possible to use state of the concrete 
     * builder itself, which can be passed in via dependency injection at 
     * construction time
     * 
     * @param Context $context
     * @return Object an object of any type defined by the subclass
     */
    protected function build(Context $context)
    {
        //the default implementation returns a context, which holds an id
        //that can be used to work with in your rules and commands.
        return $context;
    }
    
    public function toString()
    {
        return get_class($this);
    }    
}