<?php

namespace Izzum\StateMachine\Builder;

use Izzum\StateMachine\EntityBuilder;
use Izzum\StateMachine\Identifier;

/**
 * Always returns the same model; the one provided in the constructor.
 * This will thus not build the model when the statemachine asks for it, but
 * rather the model is built when your application code creates it.
 *
 * This class is useful if your domain model actually subclasses the
 * StateMachine class or uses object composition to use the statemachines' logic internally.
 *
 * In that case, you can construct the StateMachine in your models' constructor,
 * including the Context with this class in it and with the domain model itself ($this) as
 * an argument to the builder, so you can put our event handlers on the domain model and
 * have them respond to the statemachine.
 *
 * //eg: in the constructor of your domain model that uses object composition
 * $builder = new ModelBuilder($this);
 * $identifier = new Identifier($this->getId(), 'my-model-machine');
 * $context = new Context($identifier, $builder);
 * $this->machine = new StateMachine($context);
 *
 *
 * eg: in the constructor of your domain model that uses StateMachine as its' superclass
 * $builder = new ModelBuilder($this);
 * $identifier = new Identifier($this->getId(), 'my-model-machine');
 * $context = new Context($identifier, $builder);
 * parent::__construct($context)
 *
 * @link https://en.wikipedia.org/wiki/Object_composition
 *
 * @author Rolf Vreijdenberger
 *
 */
class ModelBuilder extends EntityBuilder
{
    /**
     * @param mixed $model
     *            the domain model you want to have returned from this class.
     */
    public function __construct(private $model) {}

    /**
     * {@inheritDoc}
     */
    #[\Override]
    protected function build(Identifier $identifier)
    {
        // no building actually happens. we always return the same model.
        return $this->model;
    }
}
