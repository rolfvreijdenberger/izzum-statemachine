
[![Build Status](https://travis-ci.org/rolfvreijdenberger/izzum-statemachine.svg?branch=master)](https://travis-ci.org/rolfvreijdenberger/izzum-statemachine/) 
[![Total Downloads](https://poser.pugx.org/rolfvreijdenberger/izzum-statemachine/downloads.svg)](https://packagist.org/packages/rolfvreijdenberger/izzum-statemachine) 
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/rolfvreijdenberger/izzum-statemachine/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/rolfvreijdenberger/izzum-statemachine/?branch=master) 
[![Latest Stable Version](https://poser.pugx.org/rolfvreijdenberger/izzum-statemachine/v/stable.svg)](https://packagist.org/packages/rolfvreijdenberger/izzum-statemachine) 
[![Code Coverage](https://scrutinizer-ci.com/g/rolfvreijdenberger/izzum-statemachine/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/rolfvreijdenberger/izzum-statemachine/?branch=master)
[![License](https://poser.pugx.org/rolfvreijdenberger/izzum-statemachine/license.svg)](https://packagist.org/packages/rolfvreijdenberger/izzum-statemachine)
#izzum statemachine

- **`new: release of version 2`**: transition guards and actions, new: state entry and exit actions, a fully functional PDO relational database implementation with the sql provided for 
[mysql](https://www.mysql.com), [postgresql](http://www.postgresql.org) and 
[sqlite](https://sqlite.org/) backends, uml diagrams generated from code. new: 'event' support added as transition input (mealy and moore machines)
- Want to know what to do to get it working? Skip to the [Usage section](#usage-a-working-example) or [examples](#examples)
- Visually oriented? Know uml? TLDR; see the [class diagram of the whole package](#class-diagram-for-the-izzum-package)
- see [documentup.com](http://documentup.com/rolfvreijdenberger/izzum-statemachine/recompile "navigable version on documentup.com") for a navigable and pretty version of this document.

##about
###A superior, extensible and flexible statemachine library
A [finite statemachine](https://en.wikipedia.org/wiki/Finite-state_machine "finite statemachine on wikipedia") 
implementation that allows you to add state for any domain object and to define
the logic of transitions between any and all states for that object while keeping your object
unaware that it is governed by a statemachine.

The [finite statemachine](https://en.wikipedia.org/wiki/Finite-state_machine) is in the form of a rooted finite [graph](https://en.wikipedia.org/wiki/Graph_(abstract_data_type)) and supports both [mealy](https://en.wikipedia.org/wiki/Mealy_machine) and [moore](https://en.wikipedia.org/wiki/Moore_machine) type of statemachines. It supports state entry logic, state exit logic, transition logic and transition guards.

By using the [open/closed principle](https://en.wikipedia.org/wiki/Open/closed_principle "open/closed principle on wikipedia")
we give you the means to adjust the logic provided by this library to your needs, including using the backend of choice to store configuration of the statemachine and the transition history of your stateful objects.

###thoroughly documented
find out how it works and what matters in clean code and excellent inline
documentation with explanations of how and why you'd use it and what you can 
potentially use it for. 
The unittests and the examples provided can serve as extra documentation on how to use it.

###Use your own specific domain models
It can be tailored to your application domain to work together with your own
[application specific domain models](https://en.wikipedia.org/wiki/Domain_model "domain model on wikipedia") 


###Easy configuration
Izzum makes it simple to define a statemachine with transitions and states by
providing simple objects that handle the loading logic for you.

A full implementation using [php PDO](http://php.net/manual/en/intro.pdo.php) is provided, 
which enables you to directly connect to postgresql/sqlite/mysql/MSSQL database 
with the code and the sql definitions provided in `assets/sql/*.sql`   

###Well designed interface
Clients of your code (your application) will only need to use a couple of lines
of code to interact with your statemachine and have access to a well designed
interface in case there is a need for more advanced manipulation.

###Formal ways to encapsulate the logic for transitions
All logic for a single transition is encapsulated in two classes:
- a subclass of Rule, a business rule which makes sure that a transition is 
allowed/disallowed by checking if a business rule applies.
- a subclass of a [Command](https://en.wikipedia.org/wiki/Command_pattern) which executes logic that is part
of the transition that is currently being executed between two states.

Although you have to do some work to create the rules and commands and have them
accept your domain model as a dependency in their constructor, it provides you
with a great way to seperate your concerns in their own (testable) classes.

It makes it easy for teams to work on discrete parts of the lifecycle of the domain model.

###Battle proven, fully unittested with high code coverage
Quality first! Using industry best practices for writing code with tests to back it up. 
Making use of proven design patterns to allow you to tailor it to your needs.
Go to the `/tests` directory and execute the command `phpunit -c phpunit.xml` to run the tests.

It is used in a high load commercial environment with a postgresql backend 
for one of the best Dutch fiber ISP organisations for their order management system.

###Uses well known design patterns and OOP principles
- patterns: AbstractFactory, template method (hooks), [Command](https://en.wikipedia.org/wiki/Command_pattern), Adapter (persistence), Builder (domain model), Decorator (Loader)
- principles: Dependency injection, encapsulation, polymorphism, extensible/inheritance, open/closed.
 
###multiple backend implementations
we have provided a [php PDO](http://php.net/manual/en/intro.pdo.php) implementation that can be used 
to connect to all supported databases as your persistence layer to store
and retrieve states, define your machines and transitions, and keep your history of transitions.

###no dependencies
There are no dependencies on third party libraries. php 5.3 or higher

###License
[MIT](https://en.wikipedia.org/wiki/MIT_License)

###Automated uml state diagram creation
Create uml state diagrams from a statemachine [with plantuml](http://plantuml.sourceforge.net/ "plantuml on sourceforge") 
It is a great way to visualize your machine with all the Rule/Command logic, 
making it easy to communicate with business users or stakeholders.

###installation
use [composer](https://getcomposer.org/) to install the project.
Create a file called composer.json with these lines: 
```
{
    "require": {
        "rolfvreijdenberger/izzum-statemachine": "~2.1"
    }
}
```
and install the package with:
```
composer install
```
You will find the izzum package in ./vendor/rolfvreijdenberger/izzum-statemachine.
You can also download it directly from github.

##Usage: a working example

###demo
see the `./examples/trafficlight` directory for a working implementation of a 
traffic light that you can easily run from the command line.
In the directory, type ```php -f index.php```


###domain models: your existing application code
your domain models are specific to your application. They are carefully designed
and group data and related logic together. They work well with other models in your
application. 

You can identify your domain models by their unique
id in the application, possibly related to storage in a database. You manipulate 
them via gui's, cron jobs, message queues and they perform all kinds of magic.

But they don't hold state very well.
And you would like them to be stateful.

There is a model in your application that you wish to manipulate in 
more discrete points during it's lifecycle, which are defined by states that your
object can be in. 

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
    //there are also some methods for red and orange (not shown here)
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
                //same for orange and red (not shown here)
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
###rules: check if a transition is allowed
A (business) rule will query your domain object for information to decide whether it is allowed
to make a transition (by returning true or false). It will be associated to a 
transition definition by providing the fully qualified classname. The statemachine
will construct the Rule at runtime only when it needs to check if a transition is possible.

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
###commands: perform transition logic
A command will execute the logic associated with the transition and this is the
place where your domain model (or associated objects) will be manipulated.

It will be associated to a transition definition by providing the fully qualified classname. 
The statemachine will construct the Command at runtime only when a transition is made.

Create Commands for your domain model by subclassing `izzum\command\Command` and
by accepting a domain model via the constructor. store the domain model on your
concrete command. It is advisable to create a superclass for your statemachine
commands that stores the domain object so you can use it in your subclasses.
Then override the `_execute` method in each Command to do the magic necessary 
for that step. (examples: talking to remote services, creating new objects, store
data in database etc.)
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
###entity builders: build your domain model
An EntityBuilder builds your domain model on which you operate, in our case it's
a TrafficLight. A domain object will always be representable by it's type and a 
unique id (stored in an `Identifier` object). It's the duty of the EntityBuilder to create a domain model for your statemachine
and use the (entity)id to build it.

Create a specific EntityBuilder for your domain model by subclassing `izzum\statemachine\EntityBuilder`.
override the `build(Identifier $identifier)` method to return a domainmodel of choice. 
The method accepts an `izzum\statemachine\Identifier` model that can be queried 
for the id of the domain model via `Identfier::getEntityId()`. 
The concrete EntityBuilder for your application should be set on the Context object.

The object that is returned by the EntityBuilder is the object that will be 
injected at runtime in the Rules and Command associated with a transition.
```php
namespace izzum\examples\trafficlight;
use izzum\statemachine\EntityBuilder;
use \izzum\statemachine\Identifier;
class EntityBuilderTrafficLight extends EntityBuilder{
    protected function build(Identifier $identifier) {
        return new TrafficLight($identifier->getEntityId());
    }
}
```
###persistence adapters: writing and reading your state data
A persistance adapter is an adapter that is specifically tailored for your 
applications' design to store and retrieve the data associated by the statemachine
with your domain model. 

Currently there is a fully functional and tested PDO (php database objects) adapter that 
both loads the configuration and stores the data in a backend of choice. Sql files for the data
definitions are provided in `/assets/sql/*.sql` (postgresql, mysql, sqlite). 
Other ports are more than welcome if you want to contribute.

We also provide an in memory adapter that stores data for a single php process
and a session adapter that stores data for a session (can be used for GUI wizards)

A specific adapter is created by subclassing `izzum\statemachine\persistence\Adapter`
and implementing the methods to get and set states.

This is a power feature for advanced users who will know or can find out what 
they are or should be doing.
An example is not provided here, you can check out `izzum\statemachine\persistence`
to take a look at the different adapters provided.
###loaders: loading your statemachine definition
A loader is closely (but not necessarily) coupled to your persistence layer and 
is used to load the data for your statemachine. This includes all data for the
states, the transition between these states and the rules and commands associated
with these transitions.

Create a Loader by implementing the `izzum\statemachine\loader\Loader` interface on
your custom Loader class and then delegate the actual loading in the `load` method
to the `izzum\statemachine\loader\LoaderArray` class.

Since a Loader and a Persistence adapter are probably tightly coupled, you can 
integrate both of them in one class (see the izzum\statemachine\persistence\PDO
class for an example of that)
```php
        //define the states 
        $new = new State('new', State::TYPE_INITIAL);
        $green = new State('green', State::TYPE_NORMAL, State::COMMAND_NULL);
        $orange = new State('orange', State::TYPE_NORMAL);
        //use a state entry action and a state exit action
        $red = new State('red', State::TYPE_NORMAL, 'izzum\command\StartCamera', 
                'izzum\command\StopCamera');
        
        //create the transtions by using the states
        $ng =  new Transition($new, $green, Transition::RULE_TRUE, Transition::COMMAND_NULL);
        $go = new Transition($green, $orange, 'izzum\examples\trafficlight\rules\CanSwitch',
                'izzum\examples\trafficlight\command\SwitchOrange');
        $or = new Transition($orange, $red, 'izzum\examples\trafficlight\rules\CanSwitch',
                'izzum\examples\trafficlight\command\SwitchRed');
        $rg = new Transition($red, $green, 'izzum\examples\trafficlight\rules\CanSwitch',
                'izzum\examples\trafficlight\command\SwitchGreen');

        //optional: set some descriptions for uml generation
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
```

###factories: creating related classes
A [Factory can be used to create a family of related classes](https://en.wikipedia.org/wiki/Abstract_factory_pattern). 
In our case, all the classes that are needed for your application domain.
 
The factory should provide you with a statemachine, which is dependent on a Context object. 
The Context object works with an EntityBuilder that actually creates your domain model. 
The context object also works with a Persistance adapter that reads and writes 
to the underlying storage facility. 
A Loader will load up your statemachine with all the State and Transition models
 and their associated Rules and Commands for the statemachine to use. 

Since creating a statemachine involces creating different types of objects, 
it is advisable to use a factory for your application domain but it is not necessary.

Create a factory by subclassing `izzum\statemachine\AbstractFactory` and
by implementing the abstract methods necessary.
```php
namespace izzum\examples\trafficlight;
use izzum\statemachine\AbstractFactory;
use izzum\statemachine\persistence\Memory;
use izzum\statemachine\loader\LoaderArray;
/**
 * the Factory to build the statemachines for TrafficLight domain models.
 */
class TrafficLightFactory extends AbstractFactory{
    protected function createBuilder() {
        return new EntityBuilderTrafficLight();
    }
    protected function createLoader() {
        $transitions = array();
        //see earlier example, full code not shown here
        $loader = new LoaderArray($transitions);
        return $loader;
    }
    protected function getMachineName() {
        return 'traffic-light';
    }
    protected function createAdapter() {
        return new Memory();
    }
}
```
###your application: tying it all together
Your application needs to do some work!
Create the factory and get a statemachine from it. Just pass in the unique id
for the domain model you want to manipulate to get the correct statemachine.
Use the `run()`, `transition($transition_name)`, `runToCompletion()`, `handle($event)`methods on the statemachine. 
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
###result: generated state diagram for the traffic light machine
![traffic light state diagram](https://raw.githubusercontent.com/rolfvreijdenberger/izzum-statemachine/master/assets/images/state-diagram-plantuml-traffic-light.png )

###result: output for the traffic light machine
![traffic light state diagram](https://raw.githubusercontent.com/rolfvreijdenberger/izzum-statemachine/master/assets/images/traffic-light-output.png )


##Examples

###examples section in the code###
Check out the `./examples/trafficlight/` directory. Here you will find a 
working example of a functioning application with a Factory, Builder and some
Rules and Commands in action. This can guide you to building your own implementation.

###class diagram for the izzum package
![generated plant uml classdiagram from izzum statemachine](https://raw.githubusercontent.com/rolfvreijdenberger/izzum-statemachine/master/assets/images/class-diagram-plantuml.png )


###howto create a state diagram via plantuml from the code
feed a constructed statemachine to the PlantUml class:
```php
use izzum\statemachine\StateMachine;
use izzum\statemachine\LoaderArray;
use izzum\statemachine\Context;
use izzum\statemachine\Identifier;
//this was used to create the uml example diagram as seen below
$machine = 'coffee-machine';
$id = $this->getCoffeeCount();
$context = new Context(new Identifier($id, $machine));
$machine = new StateMachine($context);
$transitions = array();
//simplified code for creating the transitions and the states, see other examples
//create states and transitions
$a = new State('a');
$b = new State('b');
$tab = new Transition($a, $b);
$transitions[] = $tab;
//load the statemachine.
$loader = new LoaderArray($transitions);
$loader->load($machine);
//some output for plantuml
$generator = new PlantUml();
echo $generator->createStateDiagram($machine);
//feed the output to a plantuml diagram creator:
// - available on plantuml site
// - or incorporate the generator directly in your application
```

###uml diagram for a fictive coffee machine
![generated plant uml statediagram from izzum statemachine](https://raw.githubusercontent.com/rolfvreijdenberger/izzum-statemachine/master/assets/images/state-diagram-plantuml-coffee.png )




##contributors and thank you's
- urban dictionary: '_Yo man, who gots the izzum for tonights festivities?_'
- Richard Ruiter, Romuald Villetet, Harm de Jong, Elena van Engelen-Maslova
- the statemachine package was influenced by the [yohang statemachine](https://github.com/yohang/Finite "Finite on github")
- creation of README.md markdown with the help of [dillinger.io/](http://dillinger.io/)
- continuous integration servers: https://travis-ci.org and https://scrutinizer-ci.com
- [plantuml](http://plantuml.sourceforge.net/) for providing great tools for automated uml generation
