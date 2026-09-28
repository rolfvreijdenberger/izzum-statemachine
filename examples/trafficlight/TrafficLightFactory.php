<?php

namespace izzum\examples\trafficlight;

use Izzum\StateMachine\AbstractFactory;
use Izzum\StateMachine\EntityBuilder;
use Izzum\StateMachine\Loader\Loader;
use Izzum\StateMachine\Persistence\Adapter;
use Izzum\StateMachine\Persistence\Memory;
use Izzum\StateMachine\Loader\LoaderArray;
use Izzum\StateMachine\State;
use Izzum\StateMachine\Transition;

/**
 * the Factory to build the statemachines for TrafficLight domain models.
 * It extends the AbstractFactory and implements all the methods we need
 * to build all the relevant models for our statemachine.
 */
class TrafficLightFactory extends AbstractFactory
{
    protected function createBuilder(): EntityBuilder
    {
        return new EntityBuilderTrafficLight();
    }

    protected function createLoader(): Loader
    {
        //we use the array loader
        //in a non-example situation we would use a backend like a
        //database for example
        //@see PDO adapter and loader

        //define the states
        $new = new State('new', State::TYPE_INITIAL);
        $green = new State('green', State::TYPE_NORMAL, State::COMMAND_NULL);
        $orange = new State('orange', State::TYPE_NORMAL);
        $red = new State('red', State::TYPE_NORMAL);

        //create the transtions by using the states
        $ng =  new Transition($new, $green, 'go-green', Transition::RULE_TRUE, Transition::COMMAND_NULL);
        $go = new Transition(
            $green,
            $orange,
            'go-orange',
            'izzum\examples\trafficlight\rules\CanSwitch',
            'izzum\examples\trafficlight\command\SwitchOrange',
        );
        $or = new Transition(
            $orange,
            $red,
            'go-red',
            'izzum\examples\trafficlight\rules\CanSwitch',
            'izzum\examples\trafficlight\command\SwitchRed',
        );
        $rg = new Transition(
            $red,
            $green,
            'go-green',
            'izzum\examples\trafficlight\rules\CanSwitch',
            'izzum\examples\trafficlight\command\SwitchGreen',
        );

        //set some descriptions for uml generation
        $ng->setDescription("from green to orange. use the switch to orange command");
        $go->setDescription("from new to green. this will start the cycle");
        $or->setDescription("from orange to red. use the appropriate command");
        $rg->setDescription("from red back to green.");

        $new->setDescription('the init state');
        $green->setDescription("go!");
        $orange->setDescription("looks like a shade of green...");
        $red->setDescription('stop');


        $transitions[] = $ng;
        $transitions[] = $go;
        $transitions[] = $or;
        $transitions[] = $rg ;

        $loader = new LoaderArray($transitions);
        return $loader;
    }

    protected function getMachineName(): string
    {
        return 'traffic-light';
    }

    protected function createAdapter(): Adapter
    {
        //we use the in-memory adapter
        //in real life we would use some persisten storage like
        //a relational database.
        //@see PDO adapter
        return new Memory();
    }
}
