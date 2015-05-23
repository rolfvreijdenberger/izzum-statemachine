<?php
namespace izzum\statemachine;
use izzum\statemachine\loader\Loader;
use izzum\statemachine\persistence\Adapter;

/**
 * This class (or it's subclasses) should be the preferred way to get a
 * statemachine.
 * It's based on the AbstractFactory pattern.
 *
 * To fully use polymorphism for all your statemachines, you should instantiate
 * all your machines via factories.
 * Full polymorphism allows you to build tooling that is the same for every
 * statemachine you use in your program. The only thing you would need to do is
 * instantiate the different factories to produce the different machines.
 *
 * The different sql backends provided store a fully qualified factory class
 * name for a machine so you can instantiate factories dynamically.
 *
 *
 * implement the abstract methods in a factory subclass specific to your problem
 * domain.
 *
 * $factory = new MySpecificFactory($dependencies_injected_here);
 * $machine = $factory->getStateMachine($order->getId());
 * $factory->add($machine->getInitialState());
 * $machine->run();
 *
 * @author Rolf Vreijdenberger
 *        
 * @link https://en.wikipedia.org/wiki/Abstract_factory_pattern
 * @link https://en.wikipedia.org/wiki/Template_method_pattern
 */
abstract class AbstractFactory {

    /**
     * Gets the concrete Loader.
     * 
     * A simple implementation might use the LoaderArray
     *
     * @return Loader An implementation of a Loader class (might be implemented
     *         on the persistence adapter)
     */
    abstract protected function createLoader();

    /**
     * Returns an implementation of an Adapter class for the persistence layer
     *
     * @return Adapter
     */
    abstract protected function createAdapter();

    /**
     * Get a builder to build your domain objects
     *
     * @return EntityBuilder
     */
    abstract protected function createBuilder();

    /**
     * get the machine name for the machines that are produced by this factory.
     * will be used by the Identifier and Context
     *
     * @return string
     */
    abstract protected function getMachineName();

    /**
     * Factory method to get a correctly configured statemachine without
     * creating all the default objects in application code.
     *
     * TRICKY: When using this method it could lead to unoptimized creation
     * of different builders/loaders/persistence objects.
     * For example: a Loader can be reused, a databaseloader will only have
     * to access a database once for a specific machine to get all the
     * transitions.
     *
     *
     * When using this method when inside a loop of some sorts where multiple
     * statemachines for different entities are needed, it would be wise to
     * cache/reuse the different loaders, builders and persistence adapters.
     *
     * php's spl_object_hash() method would be a good way to cache a fully
     * loaded statemachine.
     *
     * Furthermore, once a Loader, ReferenceBuilder and Adapter for persistence
     * have been instantiated, they can be cached in a field of this class since
     * they can safely be reused and shared. Or just change the context on a
     * machine to have access to all the same transitions, builders etc. of the
     * machine.
     *
     * @param string $id
     *            the entity id for the Identifier
     * @return StateMachine a statemachine ready to go
     * @throws Exception
     * @link https://en.wikipedia.org/wiki/Abstract_factory_pattern
     * @link https://en.wikipedia.org/wiki/Template_method_pattern
     */
    public function getStateMachine($id)
    {
        $context = $this->createContext(new Identifier($id, $this->getMachineName()));
        $machine = $this->createMachine($context);
        $loader = $this->createLoader();
        $loader->load($machine);
        return $machine;
    }

    /**
     * create a statemachine
     *
     * @param Context $context            
     * @return StateMachine
     */
    protected function createMachine(Context $context)
    {
        return new StateMachine($context);
    }

    /**
     * Factory method to get a configured Context with the default Builder
     * and persistence adapter for a concrete statemachine type.
     *
     *
     * @param Identifier $id            
     * @return Context
     * @throws Exception
     * @link https://en.wikipedia.org/wiki/Abstract_factory_pattern
     * @link https://en.wikipedia.org/wiki/Template_method_pattern
     */
    protected function createContext(Identifier $identifier)
    {
        $context = new Context($identifier, $this->createBuilder(), $this->createAdapter());
        return $context;
    }

    /**
     * add state information to the persistence layer if it is not there.
     * Used to mark the initial construction of a statemachine at a certain
     * point in time. This method only makes sense the first time a statemachine
     * is initialized since it will do nothing once a transition has been made.
     *
     * This is a convenience method since it delegates to the Context.
     *
     * @param Identifier $identifier            
     * @param string $state            
     */
    public final function add(Identifier $identifier, $state)
    {
        $context = $this->createContext($identifier)->add($state);
    }
}