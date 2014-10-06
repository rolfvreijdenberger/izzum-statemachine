<?php
namespace izzum\examples\demo;
use izzum\statemachine\EntityBuilder;
use \izzum\statemachine\Context;
/**
 * The builder for our Context object.
 * It returns the TrafficLight domain object and will cache it
 * as long as this builder is called via the same context.
 */
class EntityBuilderTrafficLight extends EntityBuilder{
    protected function build(Context $context) {
        $light = new TrafficLight($context->getEntityId());
        return $light;
    }
}
