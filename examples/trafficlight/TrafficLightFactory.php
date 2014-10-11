<?php
namespace izzum\examples\trafficlight;
use izzum\statemachine\AbstractFactory;
use izzum\statemachine\persistence\Memory;
use izzum\statemachine\loader\LoaderData;
use izzum\statemachine\loader\LoaderArray;
use izzum\statemachine\State;
use izzum\statemachine\Transition;
/**
 * the Factory to build the statemachines for TrafficLight domain models.
 * It extends the AbstractFactory and implements all the methods we need
 * to build all the relevant models for our statemachine.
 */
class TrafficLightFactory extends AbstractFactory{
    
    protected function createBuilder() {
        return new EntityBuilderTrafficLight();
    }

    protected function createLoader() {
        //we use the array loader
        //in a non-example situation we would use a backend like a
        //database for example
        
        //define the data to load
        $data = array();
        //from new to green. this will start the cycle. 
        //mark 'new' as type initial
        $data[] = LoaderData::get('new', 'green' , 
                Transition::RULE_TRUE, Transition::COMMAND_NULL, 
                State::TYPE_INITIAL, State::TYPE_NORMAL);

        //from green to orange. use the switch to orange command
        $data[] = LoaderData::get('green', 'orange' , 
                'izzum\examples\trafficlight\rules\CanSwitch',
                'izzum\examples\trafficlight\command\SwitchOrange');
        //from orange to red. use the appropriate command
        $data[] = LoaderData::get('orange', 'red' , 
                'izzum\examples\trafficlight\rules\CanSwitch',
                'izzum\examples\trafficlight\command\SwitchRed');

        //from red back to green.  The transition from green has already been 
        //defined earlier.
        $data[] = LoaderData::get('red', 'green' , 
                'izzum\examples\trafficlight\rules\CanSwitch',
                'izzum\examples\trafficlight\command\SwitchGreen');

        $loader = new LoaderArray($data);
        return $loader;
    }

    protected function getMachineName() {
        return 'traffic-light';
    }

    protected function createAdapter() {
        //we use the inmemory adapter
        //in real life we would use some persisten storage like 
        //a relational database.
        return new Memory();
    }
}
