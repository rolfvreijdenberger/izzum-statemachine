<?php
namespace izzum\examples\demo\command;
use izzum\command\Command;
use izzum\examples\demo\TrafficLight;
/**
 * Switcher functions as a superclass for all our
 * traffic light switcher classes and accepts a traffic light
 * via dependency injection in the constructor.
 */
abstract class Switcher extends Command{
    /**
     * @var TrafficLight
     */
    protected $light;
    public function __construct(TrafficLight $light)
    {
        $this->light = $light;
    }
}
