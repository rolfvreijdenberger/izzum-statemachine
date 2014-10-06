<?php
namespace izzum\examples\demo\command;
/**
 * SwitchRed command switches the traffic light to red.
 * each switch command operates on the domain object and will have side effects, 
 * like manipulating the model, setting data etc.
 */
class SwitchRed extends Switcher{
    protected function _execute() {
        $this->light->setRed();
    }
}
