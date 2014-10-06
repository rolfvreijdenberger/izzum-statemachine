<?php
namespace izzum\examples\demo\command;
/**
 * SwitchGreen command switches the traffic light to green.
 * each switch command operates on the domain object and will have side effects, 
 * like manipulating the model, setting data etc.
 */
class SwitchGreen extends Switcher{
    protected function _execute() {
        $this->light->setGreen();
    }
}
