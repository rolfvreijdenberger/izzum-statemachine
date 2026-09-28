<?php

namespace izzum\examples\trafficlight;

use Izzum\StateMachine\EntityBuilder;
use Izzum\StateMachine\Identifier;

/**
 * The builder for our Context object.
 * It returns the TrafficLight domain object and will cache it
 * as long as this builder is called via the same context.
 */
class EntityBuilderTrafficLight extends EntityBuilder
{
    #[\Override]
    protected function build(Identifier $identifier)
    {
        $light = new TrafficLight($identifier->getEntityId());
        return $light;
    }
}
