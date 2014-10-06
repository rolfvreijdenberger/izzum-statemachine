<?php
namespace izzum\examples\demo\rules;
use izzum\rules\Rule;
use izzum\examples\demo\TrafficLight;
/**
 * This rule checks if a traffic light can switch.
 * It only has knowledge about a traffic light domain model and no knowledge
 * about any statemachine.
 * The TrafficLight instance will be injected at runtime via the 
 * statemachine that makes use of the EntityBuilderTrafficLight.
 * A rule should never have side effects. instead, it should only check if
 * some condition is true or false.
 */
class CanSwitch extends Rule {
    /**
     * @var TrafficLight
     */
    private $light;
    
    /**
     * constructor. get the domain object injected
     * @param TrafficLight $light
     */
    public function __construct($light) {
        $this->light = $light;
    }
    
    /**
     * overriden method with the correct implementation for our
     * domain logic
     * {@inheritDoc}
     */
    protected function _applies() {
        echo $this->light->toString();
        return (boolean) $this->light->isReadyToSwitch();
    }
}

