<?php
namespace izzum\examples\trafficlight\command;
/**
 * SwitchOrange command switches the traffic light to orange.
 * each switch command operates on the domain object and will have side effects, 
 * like manipulating the model, setting data etc.
 */
class SwitchOrange extends Switcher{
    protected function _execute() {
        $this->light->setOrange();
    }
}
