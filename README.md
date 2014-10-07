izzum [![Build Status](https://travis-ci.org/rolfvreijdenberger/izzum.svg?branch=master)](https://travis-ci.org/rolfvreijdenberger/izzum/) [![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/rolfvreijdenberger/izzum/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/rolfvreijdenberger/izzum/?branch=master) [![Code Coverage](https://scrutinizer-ci.com/g/rolfvreijdenberger/izzum/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/rolfvreijdenberger/izzum/?branch=master)
=====
'_Yo man, who gots the izzum for tonights festivities?_'

- see [http://documentup.com/rolfvreijdenberger/izzum](http://documentup.com/rolfvreijdenberger/izzum/recompile "navigable version on documentup.com") for a navigable
version of this document.
- Want to know what to do to get it working? Skip to the [Usage section](#usage) or [examples](#examples)
- **`new`**: a [postgresql](http://www.postgresql.org) and [sqlite](http://www.sqlite.org) backend to store your data.

##about##
### A superior, extensible and flexible statemachine library ###
A [finite statemachine](https://en.wikipedia.org/wiki/Finite-state_machine "finite statemachine on wikipedia") 
implementation that allows you to add state to any domain object and to define
the logic of transitions between any and all states for that object.
It revolves around the [Command pattern](https://en.wikipedia.org/wiki/Command_pattern "command pattern on wikipedia") 
for executing transition logic and uses business rules for the transition guard logic.

By using the [open/closed principle](https://en.wikipedia.org/wiki/Open/closed_principle "open/closed principle on wikipedia")
we give you the means to adjust the logic provided by this library to your needs.
Subclassing and/or using hooks in the code allow you to add logging, event dispatching,
inserting logic specific to your domain etc.

### thoroughly documented ###
find out how it works and what matters in clean code and excellent inline
documentation with explanations of how and why you'd use it and what you can 
potentially use it for. 
The unittests and the examples provided can serve as extra documentation on how to use it.

### Use your own specific domain models ###
It can be tailored to your application domain by using an adapter for 
your persistence layer (postgresql/mysql, memcache, sessions etc), a loader 
for your purpose (yaml~, json~, postgresql) and an abstraction of your 
[application specific domain models](https://en.wikipedia.org/wiki/Domain_model "domain model on wikipedia") 
by using a subclassed builder that creates the domain model you want to associate
with a statemachine. 

This allows you to operate on your own domain models by using them as an argument to your 
`Command` (transition logic) and `Rule` (transition guard) classes.


### Easy configuration ###
Izzum makes it simple to define a statemachine with transitions and states by
providing simple objects that handle the loading logic for you.
A LoaderData object contains data to fully configure one transition between 2 states
and a Loader class can handle the loading of the statemachine via LoaderData for you.
Implement your own loader to adapt it to your configuration wishes like getting
the data from a database or file.

A full implementation using [php PDO](http://php.net/manual/en/intro.pdo.php) is provided, 
which enables you to directly connect to postgresql/sqlite/mysql/MSSQL database 
with the code and the sql definitions provided in `assets/sql/*.sql`   

### Well designed interface ###
Clients of your code (your application) will only need to use a couple of lines
of code to interact with your statemachine and have access to a well designed
interface in case there is a need for more advanced manipulation.
```php
//interface overview for the statemachine
$machine = new StateMachine($context);
$machine->apply('new_to_initialize');
$machine->run();
$machine->runToCompletion();
$machine->can('new_to_initialize');
$machine->getContext();
$machine->getStates();
$machine->getTransitions();
$machine->getCurrentState();
$machine->getInitialState();
$machine->toString();
$machine->addTransition($transition);
$machine->changeContext($context);
```

### Formal ways to encapsulate the logic for transitions ###
All logic for a single transition is encapsulated in two classes:
- a subclass of Rule (see the package) which makes sure that a transition is 
allowed/disallowed by checking if a business rule applies.
- a subclass of Command (see the package) which executes logic that is part
of the transition that is currently being executed between two states.

Although you have to do some work to create the rules and commands and have them
accept your domain model as a dependency in their constructor, it provides you
with a great way to seperate your concerns in their own (testable) classes.

It makes it easy for teams to work on discrete parts of the lifecycle of the domain model.

### Battle proven, fully unittested with high code coverage ### 
Using industry best practices for writing code with tests to back it up. 
Making use of proven design patterns to allow you to tailor it to your needs.

It is used in a high load commercial environment with a postgresql backend 
for one of the best Dutch fiber ISP organisations for their order management system.

### Uses well known design patterns and OOP principles ###
- patterns: AbstractFactory, template method (hooks), command, Adapter (persistence), Builder (domain model), Decorator (Loader)
- principles: Dependency injection, encapsulation, polymorphism, extensible/inheritance, open/closed.
 
### multiple backend implementations ###
we have provided a [php PDO](http://php.net/manual/en/intro.pdo.php) implementation that can be used 
to connect to all supported databases as your persistence layer to store
and retrieve states, define your machines and transitions, and keep your history of transitions.

### no dependencies ###
There are no dependencies on third party libraries. php 5.3 or higher

### License ###
[MIT]("https://en.wikipedia.org/wiki/MIT_License")

### Automated uml state diagram creation ###
Create uml state diagrams from a statemachine [with plantuml](http://plantuml.sourceforge.net/ "plantuml on sourceforge") 
It is a great way to visualize your machine with all the Rule/Command logic, 
making it easy to communicate with business users or stakeholders.

see the examples section for some diagrams.

##Usage##

###demo###
see the `/examples/trafficlight` directory for a working implementation of a 
traffic light that you can easily run from the command line.

###installation###
use [composer](https://getcomposer.org/) to install the project
###domain models: the representation of your applications' workers###
your domain models are specific to your application. They are carefully designed
and group data and related logic together. They work well with other models in your
application. There is a model in your application that you wish to manipulate in 
more discrete points during it's lifecycle, which are defined by states that your
object can be in. You can identify your domain models by their unique
id in the application, possibly related to storage in a database. You manipulate 
them via gui's, cron jobs, message queues and they perform all kinds of magic.
But they don't hold state very well.
This is where the statemachine shines. It's only function is to use your domain model
and query it for information about transitions that it wants to make between states.
It can/will store data seperately from your domain object OR can integrate with
your currently existing domain model (via a persistance adapter, more on that later).
```php
namespace izzum\examples\trafficlight;
/**
 * Traffic light is the domain object of our example.
 */
class TrafficLight {
     private $id;
     private $color;
     private $switch_time;
     const TIME_RED = 4, TIME_ORANGE = 2, TIME_GREEN = 6;
    
    public function __construct($id) {
        $this->setId($id);
        $this->setGreen();
    }
    protected function setSwitchTime() {
        $this->switch_time = time();
    }
    public function setGreen() {
        $this->setColor('green');
    }
    ...also some methods for red and orange
    protected function setColor($color) {
        $this->setSwitchTime();
        $this->color = $color;
        echo sprintf('trafficlight[%s] switching to [%s]', 
                $this->id, strtoupper($color)) . PHP_EOL;
    }
    public function isReadyToSwitch() {
        switch ($this->color) {
            case 'green':
                if($this->onColorFor(self::TIME_GREEN)){
                    return true;
                }
                ...same for orange and red
        }
        return false;
    }
    protected function onColorFor($time) {
        $difference = $this->switch_time + $time;
        return time() >= $difference;
    }   
    public function toString() {
        return sprintf("trafficlight[%s] on color [%s] for [%s] seconds", 
                $this->id, $this->color, time() - $this->switch_time) .
            PHP_EOL;
    }  
}
```
### rules: check if a transition is allowed ###
A rule will query your domain object for information to decide whether it is allowed
to make a transition (by returning true or false).

Create a rule by subclassing `izzum\rules\Rule` and by accepting a domain object in 
the newly created rules' constructor. Override the `_applies()` method to query
the  domain object and return true/false.
```php
namespace izzum\examples\trafficlight\rules;
use izzum\rules\Rule;
use izzum\examples\trafficlight\TrafficLight;
/**
 * This rule checks if a traffic light can switch.
 */
class CanSwitch extends Rule {
    private $light;
    public function __construct(TrafficLight $light) {
        $this->light = $light;
    }
    protected function _applies() {
        return (boolean) $this->light->isReadyToSwitch();
    }
}
```
### commands: perform transition logic ###
A command will execute the logic associated with the transition and this is the
place where your domain model (or associated objects) will be manipulated.

Create Commands for your domain model by subclassing `izzum\command\Command` and
by accepting a domain model via the constructor. store the domain model on your
concrete command. It is advisable to create a superclass for your statemachine
commands that stores the domain object so you can use it in your subclasses.
Then override the `_execute` method in each Command to do the magic necessary 
for that step.
```php
<?php
namespace izzum\examples\trafficlight\command;
use izzum\command\Command;
use izzum\examples\trafficlight\TrafficLight;
/**
 * SwitchRed command switches the traffic light to red.
 */
class SwitchRed extends Command {
    protected $light;
    public function __construct(TrafficLight $light) {
        $this->light = $light;
    }
    protected function _execute() {
        $this->light->setRed();
    }
}
```
### entity builders: build your domain model ###
An EntityBuilder builds your domain model on which you operate, in our case it's
a TrafficLight. A domain object will always be representable by it's type and a 
unique id. It's the duty of the EntityBuilder to create one for your statemachine
and set the correct (entity)id on it.

Create a specific EntityBuilder for your domain model by subclassing `izzum\statemachine\EntityBuilder`.
override the `build(Context $context)` method to return a domainmodel of choice. 
The method accepts an `izzum\statemachine\Context` model that can be queried 
for the id of the domain model via `Context::getEntityId()`. 
The concrete EntityBuilder for your application should be set on the Context object.

The object that is returned by the EntityBuilder is the object that will be 
injected at runtime in the Rules and Command associated with a transition.
```php
namespace izzum\examples\trafficlight;
use izzum\statemachine\EntityBuilder;
use \izzum\statemachine\Context;
class EntityBuilderTrafficLight extends EntityBuilder{
    protected function build(Context $context) {
        return new TrafficLight($context->getEntityId());
    }
}
```
### persistence adapters: writing and reading your state data ###
A persistance adapter is an adapter that is specifically tailored for your 
applications' design to store and retrieve the data associated by the statemachine
with your domain model. 

Currently there is a fully functional and tested Postgres database adapter that 
both loads the configuration and stores the data. An sql file for the data
definitions is also provided in `/assets/sql/postgres.sql`
We also provide an in memory adapter that stores data for a single php process
and a session adapter that stores data for a session (can be used for GUI wizards)

A specific adapter is created by subclassing `izzum\statemachine\persistence\Adapter`
and implementing the methods to get and set states.

This is a power feature for advanced users who will know or can find out what 
they are or should be doing.
An example is not provided here, you can check out `izzum\statemachine\persistence`
to take a look at the different adapters provided.
### loaders: loading your statemachine definition ###
A loader is closely (but not necessarily) coupled to your persistence layer and 
is used to load the data for your statemachine. This includes all data for the
states, the transition between these states and the rules and commands associated
with these transitions.

Create a Loader by implementing the `izzum\statemachine\loader\Loader` interface on
your custom Loader class and then delegate the actual loading in the `load` method
to the `izzum\statemachine\loader\LoaderArray` class.

Since a Loader and a Persistence adapter are probably tightly coupled, you can 
integrate both of them in one class (see the izzum\statemachine\persistence\Postgres
class for an example of that)
```php
        $data = array();
        //from new to green. this will start the cycle. mark 'new' as type initial
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
        //from red back to green.  The transition from green has already been  defined earlier.
        $data[] = LoaderData::get('red', 'green' , 
                'izzum\examples\trafficlight\rules\CanSwitch',
                'izzum\examples\trafficlight\command\SwitchGreen');

        $loader = new LoaderArray($data);
```

###factories: creating related classes###
An Factory can be used to create a family of related classes. In our case, all
the classes that are needed for your application domain. The factory should provide
you with a statemachine, which is dependent on a Context object. The Context object
works with an EntityBuilder that actually creates your domain model. The context
object also works with a Persistance adapter that reads and writes to the underlying
storage facility. A loader will load up your statemachine with all the State and
Transition models and their associated Rules and Commands for the statemachine
to use. 

Since creating a statemachine involces creating different types of objects, 
it is advisable to use a factory for your application domain but it is not necessary.

Create a factory by subclassing `izzum\statemachine\factory\AbstractFactory` and
by implementing the abstract methods necessary.
```php
namespace izzum\examples\trafficlight;
use izzum\statemachine\factory\AbstractFactory;
use izzum\statemachine\persistence\Memory;
use izzum\statemachine\loader\LoaderData;
use izzum\statemachine\loader\LoaderArray;
/**
 * the Factory to build the statemachines for TrafficLight domain models.
 */
class TrafficLightFactory extends AbstractFactory{
    protected function getEntityBuilder() {
        return new EntityBuilderTrafficLight();
    }
    protected function getLoader() {
        $data = array();
        ... see earlier example
        $loader = new LoaderArray($data);
        return $loader;
    }
    protected function getMachineName() {
        return 'traffic-light';
    }
    protected function getPersistenceAdapter() {
        return new Memory();
    }
}
```
###your application: tying it all together###
Your application needs to do some work!
Create the factory and get a statemachine from it. Just pass in the unique id
for the domain model you want to manipulate to get the correct statemachine.
Use the `run()`, `apply($transition_name)`, `runToCompletion()` methods on the statemachine. 
The statemachine will find it's own path through it's
transitions by using the rules that either allow/disallow a transition and
by executing the Command logic if a transition is possible.

```php
//create the factory
$factory = new TrafficLightFactory();
//get the machine from the factory, for traffic light 1
$machine = $factory->getStateMachine(1);

//let the traffic light do it's work
while(true) {
    $machine->run();
    sleep(1);  
}
```
###result: state diagram for the traffic light machine###
![traffic light state diagram](https://raw.githubusercontent.com/rolfvreijdenberger/izzum/master/assets/images/state-diagram-plantuml-traffic-light.png )
###result: output for the traffic light machine###
![traffic light state diagram](https://raw.githubusercontent.com/rolfvreijdenberger/izzum/master/assets/images/traffic-light-output.png )


##Examples##

### uml diagram for an order system ###
![generated plant uml statediagram from izzum statemachine](https://raw.githubusercontent.com/rolfvreijdenberger/izzum/master/assets/images/state-diagram-plantuml.png )

### php example for the simplest case ###
```php
<?php
use izzum\statemachine\StateMachine;
use izzum\statemachine\PostgresFactory;

//use the abstract factory pattern with a postgres persistence layer to store
//the states for the different stateful entities, a loader to load all the configured
//transitions, states, rules and commands and a specific builder for the 
//domain object (an order)
$factory = new PostgresFactory('order-machine');
//get a statemachine that can run transitions for all states for
//the order we are currently manipulating
$machine = $factory->getStateMachine($order->getId());
//run the first transition allowed
$machine->run();
echo $machine->getCurrentState();
```

### plantuml diagram and the code to create it ###
```php
<?php
use izzum\statemachine\StateMachine;
use izzum\statemachine\LoaderData;
use izzum\statemachine\LoaderArray;
//this was used to create the uml example diagram as seen below
$machine = 'coffee-machine';
$id = $this->getCoffeeCount();
$context = new Context($id, $machine);
$machine = new StateMachine($context);
$data = array();
$data[] = new LoaderData('new', 'initialize', Transition::RULE_TRUE, 'izzum\command\Initialize', 'initial');
$data[] = new LoaderData('initialize', 'cup', Transition::RULE_TRUE, 'izzum\command\DropCup');
$data[] = new LoaderData('cup', 'coffee', Transition::RULE_TRUE, 'izzum\command\AddCoffee');
$data[] = new LoaderData('coffee', 'sugar', 'izzum\rules\WantsSugar', 'izzum\command\AddSugar');
$data[] = new LoaderData('sugar', 'coffee', Transition::RULE_TRUE, Transition::COMMAND_NULL);
$data[] = new LoaderData('coffee', 'milk', 'izzum\rules\WantsMilk', 'izzum\command\AddMilk');
$data[] = new LoaderData('milk', 'coffee', Transition::RULE_TRUE, Transition::COMMAND_NULL);
$data[] = new LoaderData('coffee', 'spoon', 'izzum\rules\MilkOrSugar', 'izzum\command\AddSpoon');
$data[] = new LoaderData('coffee', 'done', 'izzum\rules\CoffeeTakenOut', 'izzum\command\Cleanup', State::TYPE_NORMAL, State::TYPE_FINAL);
$data[] = new LoaderData('spoon', 'done', 'izzum\rules\CoffeeTakenOut', 'izzum\command\CleanUp', State::TYPE_NORMAL, State::TYPE_FINAL);
$loader = new LoaderArray($data);
$loader->load($machine);
//some output for plantuml
echo PlantUml::createStateDiagram($machine);
//run the machine to completion
$machine->runToCompletion();
```
![generated plant uml statediagram from izzum statemachine](https://raw.githubusercontent.com/rolfvreijdenberger/izzum/master/assets/images/state-diagram-plantuml-coffee.png )


##contributors and thank you's##
- Richard Ruiter, Romuald Villetet, Harm de Jong, Elena van Engelen-Maslova
- the statemachine package was influenced by the [yohang statemachine](https://github.com/yohang/Finite "Finite on github") , thanks for some good work.
- creation of README.md markdown with the help of [dillinger.io/](http://dillinger.io/)
- nice layout of this file: [documentup.com](http://documentup.com/rolfvreijdenberger/izzum)
- continuous integration servers: https://travis-ci.org and https://scrutinizer-ci.com





