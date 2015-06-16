
[![Build Status](https://travis-ci.org/rolfvreijdenberger/izzum-statemachine.svg?branch=master)](https://travis-ci.org/rolfvreijdenberger/izzum-statemachine/) 
[![Total Downloads](https://poser.pugx.org/rolfvreijdenberger/izzum-statemachine/downloads.svg)](https://packagist.org/packages/rolfvreijdenberger/izzum-statemachine) 
[![Latest Stable Version](https://poser.pugx.org/rolfvreijdenberger/izzum-statemachine/v/stable.svg)](https://packagist.org/packages/rolfvreijdenberger/izzum-statemachine) 
[![Code Coverage](https://scrutinizer-ci.com/g/rolfvreijdenberger/izzum-statemachine/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/rolfvreijdenberger/izzum-statemachine/?branch=master)
[![License](https://poser.pugx.org/rolfvreijdenberger/izzum-statemachine/license.svg)](https://packagist.org/packages/rolfvreijdenberger/izzum-statemachine)

###A superior, extensible and flexible statemachine library
A [finite statemachine](https://en.wikipedia.org/wiki/Finite-state_machine "finite statemachine on wikipedia") is a model for the behaviour of a system that consists of a finite number of states, transitions between those states and guard~ and transition logic for those states and transitions. 


###about
A proven enterprise ready, MIT licensed, fully unittested and high quality statemachine. It has the ability to be used with different backends (postgres, redis, sqlite, mongodb, mysql, session or memory) for storing state data and transition history, and for configuring the statemachine with states, transitions and the logic for those transitions (in yaml, json, xml, sql, redis or mongodb).
It will work seamlessly with existing domain models (like 'Order', 'Customer' etc) by operating on those models instead of having to create new domain models with statemachine logic in them (which is also possible). The examples, extensive (inline) documentation and unittests will make it easy to setup and get going. 
 
### Example walkthrough
The following code examples will guide you through using the statemachine and will familiarize you with the different ways to interact with the statemachine. 

###creating a statemachine
An entity id and a machine name provide a unique definition for your statemachine. together they constitute the simplest identifier for a statemachine in your application. An entity id will most probably be the unique id of a domain model in your application.
A context provides provides the operational context in which your statemachine operates. Context uses the identifier to uniquely define a statemachine. A context also provides ways to store your state in a backend of choice and provides ways to operate on your domain specific models via optional constructor arguments (explained later).
A statemachine needs a Context to define the environment it operates in.
```php
$identifier = new Identifier('198442', 'order');//the identifier for your machine
$context = new Context($identifier);//other parameters for Context will be explained later
$machine = new StateMachine($context);//create the statemachine
```
### adding states and transitions
Transitions can take place from one state to another state. A statemachine should have exactly 1 'initial' type state, 0 or more 'normal' type states and 0 or more 'final' type states.
Define your states (with optional types) and use them to define transtions. Transitions can be triggered by an 'event' string. Finally, add the transitions to the statemachine.
Transitions and states can accept more arguments in their constructors (explained later)
```php
$new = new State('new', State::TYPE_INITIAL);//there should be 1 initial state
$action = new State('action');//a normal state
$done = new State('done', State::TYPE_FINAL);//one of potentially many final states
$machine->addTransition(new Transition($new, $action, 'go'));//add a transition between states that is triggered by an event
$machine->addTransition(new Transition($action, $done, 'finish'));
//result: 3 states and 2 transitions defined on the statemachine
```
Transitions can (and should preferrably) be added via a Loader (more on that later) so you can define your statemachine in configuration files or a persistance backend of choice. This gives you greater flexibility for definining statemachines (and keeping them under version control).
### get information about the statemachine
The context provides contextual information about the machine and as such holds the metadata for that machine: the machine name, the entity id (both in the Identifier), a persistence adapter, used to store your state and transition data (there are adapters for Memory (the default), Session, Redis, and PDO (sql for postgres, sqlite and mysql)) and a domain model builder ('EntityBuilder') that serves as a factory to create your domain model with the help of the entity id (explained later)
```php
$context = $machine->getContext();
echo $context->getPersistenceAdapter();//echo works because of Memory::__toString()
# Memory
echo $context->getEntityId();//get the id for your domain model (entity)
# 198442
echo $context->getMachine();//get the name of the statemachine
# order
echo count($machine->getStates());//get the defined states directly from the machine
# 3
echo count($machine->getTransitions());//get the defined transitions directly from the machine
# 2
```

### get information about the states
The current state will be the initial state when the machine is initialized.
The state can be set directly or it can be retrieved from a persistance backend when one is used. (explained later)
```php
$state = $machine->getCurrentState();
echo $state->getName();
# new
echo $state->getType();
# initial
echo $machine->getInitialState();//echo works because of State::__toString()
# new
foreach($machine->getStates() as $state) {
    echo $state->getName();
}
# new, action, done
```
### adding regular expression states that expand to multiple transitions
Regular expression states take a [regular expression](https://en.wikipedia.org/wiki/Regular_expression) as their state name. When using a regex state in a transition, it will expand to transitions for all states that match the regex. This allows you to quickly setup a lot of transitions. It can be used for both the 'from' state as well as the 'to' state. Regex state names shall be prefixed with either 'regex:' or with 'not-regex:' for a negated regular expression.
```php
//action, or any state ending with 'ew'
$regex = new State('regex:/action|.*ew$/', State::TYPE_REGEX);
$pause = new State('pause');
$machine->addTransition(new Transition($regex, $pause), 'pause');
//new->pause, action->pause
```
### get information about the transitions
Transitions can be triggered by an event string, by their name (which is a concatenation of their 'from' state and 'to' state) or anonymously by trying a transition from the current state. The statemachine can be queried about the transitions that are allowed and how they are allowed in the current state the machine is in.
```php
//the current state 'new', has a transition that can be triggered by the 'go' event
echo $machine->hasEvent('go'); 
# true
//current state is 'new' and 'finish' is only a valid event for the 'action' state
echo $machine->hasEvent('finish');
# false
echo $machine->canHandle('go');//this will check the guard logic for the 'go' event (explanation later)
# true
//transitions have a name derived from their 'from' and 'to' states
echo $machine->canTransition('new_to_action');
# true
//not in the 'action' state
echo $machine->canTransition('action_to_done');
# false
//check the state itself for a transition
echo $machine->getCurrentState()->hasTransition('new_to_action');
# true
foreach ($machine->getTransitions() as $transition) {
    echo $transition->getName() . ":" . $transition->getEvent(); 
}
# new_to_action:go, action_to_done:finish, new_to_pause:pause, action_to_pause:pause
```


### performing specific transitions
Specific transitions from a state can be performed by calling `StateMachine::handle('<event>')`, by calling `Statemachine::<event>()` or by calling `StateMachine::transition('<name>')`. All methods will return true if a succesful transition was made.
```php
//the next three lines all produce the same result and are different ways to perform the transition to 'action' from the state 'new'
$machine->handle('go');//handle the 'go' trigger/event to transition to 'action' state
$machine->go();//use the trigger name directly as a method on the machine
$machine->transition('new_to_action');//transition by name <from>_to_<to>

//suppose we are in the 'action' state, the next lines produce the same result: a transition to 'done'
$machine->handle('finish');//transition to 'done' state via 'finish' trigger/event
$machine->finish();
$machine->transition('action_to_done');
```

### performing transitions anonymously/generically
Transitions can be opportunistically performed by trying to run the first transition that is allowed from the current state a statemachine is in. Transitions are tried in the order that they were added to the machine. Transitions can be allowed or disallowed by using 'guards': specific pieces of code for that transition that can check business rules (explained later)
```php
echo $machine->run();//perform the first transition from the current state that can run
# true
//perform as many transitions as the machine allows: 
//each transition will go to the 'to' state and will try the next transition from there,
//until it is in a final state or transitions are not possible for that current state.
echo $machine->runToCompletion();//suppose we started in 'new', then 2 transitions will be made
# 2
```
### using an EntityBuilder to build your domain model for the machine
Transition logic should be performed to do useful work. A statemachine should operate on or with domain objects from your application. A subclass of EntityBuilder shall create your application specific domain model that will be used for every transition to operate on. The `EntityBuilder::build(Identifier $identifier):*` method can be overriden to return any domain object that can be used by the statemachine (eg: an Order or a Customer) identified by an entity_id that is most probably a primary key for that object in your application. The domain model can be used by both the guard conditions for a transitions and the transition logic (including exit and entry logic). An instance of the of a subclass of EntityBuilder will be passed to the Context object which will be injected in the statemachine.
```php
$identifier = new Identifier('198442', 'order-machine');
$builder = new OrderBuilder();
$context = new Context($identifier, $builder);
$machine = new StateMachine($context);
//the builder class for an Order would look like this:
class OrderBuilder extends EntityBuilder{
  protected function build(Identifier $identifier) {
    return new Order($identifier->getEntityId());
  }
}
```
### guard conditions on transitions
Guard conditions are dynamically evaluated boolean expressions that either allow or disallow a transition. Guards should never have side effects and should only calculate the boolean result.
If a guard is not specified on a transition then the transition will be allowed by default. Guards can operate on a domain model returned by an EntityBuilder.
There are multiple ways to set guard conditions on transitions:
* **callables**: closures/anonymous methods, instance methods and static methods that return a boolean. This is easy to use and possibly decoupled from domain models. The drawback is that all code should always be defined and in memory when the transitions are defined.
* **rules**: business rules that are fully qualified classnames of instances of izzum/rules/Rule that shall accept a domain model (via the EntityBuilder) in their constructor and an implemented `Rule::applies()` method that returns a boolean. This is the most formal and most powerful guard to use because it operates on domain models in a noninvasive, loosely coupled way. Furthermore, the code (possibly expensive to run, eg: when accessing databases or network services) is only instantiated and used when needed, in contrast to all other methods for which the code should  should always be fully available at runtime when the transitions are defined.
* **event handlers**: called on a specified domain object (via the EntityBuilder) in the Context. This is flexible and convenient since you define the event handlers on your domain model that is accessible by the statemachine.
* **hooks**: used by overriding a specific method `StateMachine::__onCheckCanTransition()` when subclassing the statemachine itself. This is then tailored to your application domain and offers less flexibility than the other methods since you will need to 'switch' on the transition to take a specific action.
* **hooks as event emitters**: by subclassing the statemachine you can implement your own event handling/emitting library of choice.

### guard conditions 1. using callables: closures, static methods, instance methods
A [callable comes in multiple forms in php](https://php.net/manual/en/language.types.callable.php). In the next example, a [closure, or anonymous function](https://php.net/manual/en/functions.anonymous.php), is used to evaluate the boolean expression by operating on any context variables it has in it's scope and by using the automatically provided arguments of $entity and $event. $entity is the domain model returned by the Context of the statemachine (via the EntityBuilder). The event is only set when the transition was initiated by an event. The guard can operate on the $entity (query if for data) to calculate the boolean result.
In general, all callables will be passed the 2 arguments $entity and $event and should have a method signature of `[static] public function <name>($entity, $event = null): boolean`. 
Check the example in `examples\inheritance` for using instance methods as callables. see `tests/izzum/statemachine/TransitionTest::shouldAcceptMultipleCallableTypes` for all possible implementations of callables in the izzum statemachine.
```php
$forbidden = new State('forbidden');
$closure = function($entity, $event){return false;};
$transition = new Transition($new, $forbidden, 'thoushaltnotpass', null, null, $closure);
// or: $transition->setGuardCallable($closure);
$machine->addTransition($transition);
echo $machine->hasEvent('thoushaltnotpass');
# true
echo $machine->handle('thoushaltnotpass');//transition will not be made
# false
echo $machine->transition('new_to_forbidden');
# false
echo $machine->getCurrentState();//still in the same state
# new
```
### guard conditions 2. using business rules
A business rule is provided by using a fully qualified class name of an implemenation of `\izzum\rules\IRule`. The Rule class is dynamically instantiated only when necessary for checking the transition and wil have the domain model (provided by the Context via the EntityBuilder) injected in it's constructor. The Rule should have a `Rule::applies()` method that will return a boolean value that will be calculated by querying the domain model or any other data source (eg: services, apis, database etc).
The `False` rule is provided as an example. you should write your own specifcally for your problem domain. See `examples/trafficlight` for an implementation using rules and a domain object with an EntityBuilder.
```php
$forbidden = new State('forbidden');
$rule = '\izzum\rules\False';
$transition = new Transition($new, $forbidden, 'thoushaltnotpass', $rule);
// or: $transition->setRuleName($rule);
$machine->addTransition($transition);
echo $machine->hasEvent('thoushaltnotpass');
# true
echo $machine->handle('thoushaltnotpass');//transition will not be made
# false
echo $machine->transition('new_to_forbidden');
#> false
echo $machine->getCurrentState();//still in the same state
# new
```
A Rule subclass will be passed your domain object in it's constructor (which is made by the EntityBuilder) and will query the object to see if the business rule will apply. 
```php
class IsAllowedToShip extends Rule {
  public function __construct(Order $order) { $this->order = $order;}
  protected function _applies() { return $this->order->isPaid(); }
}
```
The configuration of a Transition with a rule should be done by providing a fully qualified classname.
The php application must be able to find the class via autoloading (which is a wrapper around including files)
```php
$rule = '\izzum\rules\IsAllowedToShip';
$transition = new Transition($action, new State('shipping'), 'ship', $rule);
```
The advantage of using Rules as guards is that there is no coupling between your domain model and the statemachine, making your application code much cleaner and more testable.
### guard conditions 3. using event handlers
The class returned by the EntityBuilder subclass can implement event handlers: callbacks that are triggered when a transition takes place. This can be done both for guards and for transition logic. Note that that class can be a subclass of a statemachine, a client class of the statemachine or an existing domain model.
The class could implement the predefined event handler `public function onCheckCanTransition($identifier, $transition, $event):boolean` which gets an Identifier and Transition object and an optional event (if the transition was triggered by an event via $statemachine->handle('event')). It must return a boolean value. The transition object can then be used to take a certain action depending on the transition name or 'from' and 'to' state. The method must return a boolean value.
```php
class MyEventHandlingClass {
  public function onCheckCanTransition($identifier, $transition, $event) { 
    echo "checking transition (" . $identifier->getEntityId() . ") " . $transition->getName() ' for event: ' . $event;
    //normally, you would put your guard logic here...
    return true;
  }
}
```
There is a special subclass of EntityBuilder: `ModelBuilder` that always returns the model injected in the constructor.
this is useful if you are implementing event handlers and want to use the event handling clas in the statemachine.
```php
$builder = new ModelBuilder(new MyEventHandlingClass());
$context = new Context($identifier, $builder);
$statemachine = new StateMachine($context);
//load the machine here and assume we are in the 'new' state in which a transition can be triggered by the 'go' event
$statemachine->handle('go');
# checking transition (198442) new_to_action for event: go
```
The drawback of event handlers as guards is that there is a tighter coupling between the statemachine and the handling class compared to using Rules as guards.

### guard conditions 4. using a hook/overriden method
By subclassing the statemachine you can implement a hook that is called as a guard: `protected function _onCheckCanTransition(Transition $transition, $event = null):boolean`. See `examples/inheritance` for an example of using a subclass of a statemachine with hooks/overriden methods. The advantage is that you won't need an EntityBuilder and can easily override the methods needed. a Disadvantage is that your model that needs state (domain model) is now tightly coupled via inheritance to your statemachine.
A big Advantage of subclassing is that it allows you to use the hooks to add your own logic to satisfy your needs. If you want to add eventlisteners to your statemachine, then just add an event emitter in the subclass and you can respond to the statemachines actions in any way you would want (eg: using transition data to send out a specific event).
### guard conditions 5. using a hook with an event dispatcher
A big Advantage of subclassing is that it allows you to use the hooks to add your own logic to satisfy your needs. If you want to add eventlisteners to your statemachine, then just add an event emitter in the subclass and you can respond to the statemachines actions in any way you would want (eg: using transition data to send out a specific event).
Add a public method to the statemachine to add event listeners and dispatch your events from the subclass's hooks.
A good event dispatcher to use would be the [Symfony EventDispatcher component](http://symfony.com/doc/current/components/event_dispatcher/introduction.html) which you can use to return the boolean evaluation parameter for the guard to allow or disallow the transition.



### state entry action, state exit actions and transition actions
There are 4 distinct phases when trying to perform a transition:
* check the guard if the transition is allowed and if true:
* perform state exit logic: associated with the 'from' state, independent of the state the transition is going.
* perform transition logic: asoociated with the transition itself which has a specific 'from' and 'to' state and enters the new state.
* perform state entry logic: associated with the 'to' state, independent from the state the transition came from.

Comparable to the logic of using guards, the exit~ transition~ and exit logic can be performed by `callables`, `commands` [see the Command design pattern](https://en.wikipedia.org/wiki/Command_pattern), `event handlers` and `hooks`.
### actions 1. callables, commands, events, hooks
TO DESCRIBE
### using a persistance adapter to store state
Persistence adapters provide an abstraction to write state data and transition history to a persistence backend of choice. Out of the box there are adapters for sql based backends (postgres, mysql, sqlite via PDO), redis, mongoDB. A semi persitant adapter is the php session adapter and a non-persistent adapter is the memory adapter (the default). Custom adapters can easily be written. An adapter is a subclass of `persistence\Adapter` and is the third argument of the Context object. The persistent adapters provided have a fully defined backend structure, examples of which can be found in `assets/<backend>`. If you use one of those backend adapters you should explicitely 'add' the state/history data (only once) to the backend by calling `$statemachine->add()` with an optional message to specify why or where the machine was created. The main reason to use backend adapter is so you can permanently store all records of transitions in a history structure (for analysis and accounting) and to store the current state. When recreating the statemachine in a different process later in time, it will automatically retrieve the current state and you can continue with transitions where you left off.
The Adapters in the example all support the full range of abillities that their drivers support. All drivers are well known php modules (PDO, redis, mongo) and more information can be found in the phpdocs in the classes.
```php
$adapter = new PDO('pgsql:host=localhost;port=5432;dbname=izzum');
//or
$adapter = new Redis('127.0.0.1', 6379);
//or
$adapter = new MongoDB('mongodb://localhost:27017');
$context = new Context($identifier, $builder, $adapter);
$statemachine = new StateMachine($context);
$statemachine->add('creation of machine...');
```
### persistance 1. storing state data in memory
The Memory persistence adapter is the default one. You don't have to provide it to the Context object.
It is only useful in 1 php process, since the state is only persisted in memory and therefore lost.
good examples to use it are a php daemon or an interactive php process with a limited lifetime. See `examples/interactive` for an implementation of a statemachine in memory.
### persistance 2. storing state data in a session
Pph sessions can be used to store data in. They are also limited in lifetime but persist between page refreshes as long as the session is valid. Therefore they are good for shopping carts, wizard like forms and other html based frontends with page refreshes. See `examples/session` for an example using the colors of the rainbow (defined in a statemachine) in page refreshes.
### persistance 3. storing transition history and state data in sql backends
SQL based backends are abundantly available in most applications. the PDO adapter provides access to all backends made available via the PDO driver. There are full sql schemas in `assets/sql` for postgresql, mysql and sqlite available. Once you create those tables you and provide the right credentials to the PDO adapter you are ready to start storing your state in your database and you can also fully define your machines including states and transitions with their associated actions in the tables.
### persistance 4. storing transition history and state data in redis or mongodb
Redis is a great nosql key/value database and MongoDB is a great nosql document based database.
They are schemaless and as such need no configuration to start storing state and transition history.
### loading statemachine configuration
TO DESCRIBE
### loading statemachine configuration 1. via xml or an xml file
TO DESCRIBE
### loading statemachine configuration 2.  via json or a json file
TO DESCRIBE
### loading statemachine configuration 3. via sql backends
TO DESCRIBE
### loading statemachine configuration 4. via redis
TO DESCRIBE
### loading and storing data from different backends: ReaderWriterDelegator
TO DESCRIBE
###installation
use [composer](https://getcomposer.org/) to install the project.
Create a file called composer.json with these lines: 
```
{
    "require": {
        "rolfvreijdenberger/izzum-statemachine": "~3.2"
    }
}
```
and install the package with:
```
composer install
```
You will find the izzum package in ./vendor/rolfvreijdenberger/izzum-statemachine.
You can also download it directly from github. The package should be included via an autoloader (provided by composer by default)
###running unittests
you can run the testsuite with phpunit (installable via composer) in the tests directory from the command line.
```
cd ./vendor/rolfvreijdenberger/izzum-statemachine/tests
phpunit -c phpunit.xml
```
Not all tests are run by default, since the persistence layer tests depend on the different backends being available (postgres, mysql, sqlite, mongodb, redis) and/or php modules (yaml, redis, mongodb). These can be run by adjusting the phpunit-xall.xml file, installing the correct php modules and having the correct backends in place.
```
phpunit -c phpunit-all.xml
```

###generated state diagram for the traffic light machine (see examples)
![traffic light state diagram](https://raw.githubusercontent.com/rolfvreijdenberger/izzum-statemachine/master/assets/images/state-diagram-plantuml-traffic-light.png )

###output for the traffic light machine (see examples)
![traffic light state diagram](https://raw.githubusercontent.com/rolfvreijdenberger/izzum-statemachine/master/assets/images/traffic-light-output.png )


