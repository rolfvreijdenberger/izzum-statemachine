
[![Build Status](https://travis-ci.org/rolfvreijdenberger/izzum-statemachine.svg?branch=master)](https://travis-ci.org/rolfvreijdenberger/izzum-statemachine/) 
[![Total Downloads](https://poser.pugx.org/rolfvreijdenberger/izzum-statemachine/downloads.svg)](https://packagist.org/packages/rolfvreijdenberger/izzum-statemachine) 
[![Latest Stable Version](https://poser.pugx.org/rolfvreijdenberger/izzum-statemachine/v/stable.svg)](https://packagist.org/packages/rolfvreijdenberger/izzum-statemachine) 
[![Code Coverage](https://scrutinizer-ci.com/g/rolfvreijdenberger/izzum-statemachine/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/rolfvreijdenberger/izzum-statemachine/?branch=master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/rolfvreijdenberger/izzum-statemachine/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/rolfvreijdenberger/izzum-statemachine/?branch=master)
[![License](https://poser.pugx.org/rolfvreijdenberger/izzum-statemachine/license.svg)](https://packagist.org/packages/rolfvreijdenberger/izzum-statemachine)

###A superior, extensible and flexible statemachine library for php version >= 5.3 including php 7
A [finite statemachine](https://en.wikipedia.org/wiki/Finite-state_machine "finite statemachine on wikipedia") is a model for the behaviour of a system that consists of a finite number of states, transitions between those states and guard~ and transition logic for those states and transitions. 

see the [change log here](https://github.com/rolfvreijdenberger/izzum-statemachine/blob/master/CHANGELOG.md).


###about
A proven enterprise grade, fully unittested and high quality statemachine. It has the ability to be used with different backends (postgres, redis, sqlite, mongodb, mysql, session or memory) for storing state data and transition history, and for configuring the statemachine with states, transitions and the logic for those transitions (in yaml, json, xml, sql, redis or mongodb).

It will work seamlessly with existing domain models (like 'Order', 'Customer' etc) by operating on those models instead of having to create new domain models with statemachine logic in them (which is also possible). The examples, extensive (inline) documentation and unittests will make it easy to setup and get going. 

Bitcoin donations are more than welcome on *[1zzumvx7zVHv3AdWXQ1XUuNKyQonx7uHM](https://blockchain.info/address/1zzumvx7zVHv3AdWXQ1XUuNKyQonx7uHM)*.

###upgrade path to 4.y.z release for php 7 from 3.y.z
- upgrade definitions in database/yml/xml/json configuration that use False Rule, True Rule or Null Command: use 'FalseRule', 'TrueRule', 'NullCommand'
- upgrade references in code that use False Rule, True Rule or Null Command: use 'FalseRule', 'TrueRule', 'NullCommand'
 
### Example walkthrough
The following code examples will guide you through using the statemachine and will familiarize you with the different ways to interact with the statemachine. 

###creating a statemachine
An entity id and a machine name provide a unique definition for your statemachine. Together they constitute the simplest way to identify a statemachine in your application. An entity id will most probably be the unique id or primary key of a domain model in your application. The machine name is a name used to distinguish one machine type from another (eg 'order-machine', 'customer-machine'). Together they are stored in the Identifier object.

A Context provides the operational context in which your statemachine operates. Context uses the identifier to uniquely identify a statemachine. A context also provides ways to store your state in a backend of choice and provides ways to operate on your domain specific models via optional constructor arguments (explained later).
A Context needs at least an Identifier. A Statemachine needs a Context to define the environment it operates in and on.
```php
//retrieve the id somewhere from your application (form, database, domain model, user input etc)
$identifier = new Identifier('198442', 'order');//the identifier for your machine
$context = new Context($identifier);//other parameters for Context will be explained later
$machine = new StateMachine($context);//create the statemachine
```

### adding states and transitions
Transitions can take place from one state to another state. A statemachine must have exactly 1 'initial' type state, 0 or more 'normal' type states and 0 or more 'final' type states.
Define your states (with optional types, type defaults to 'normal') and use them to define transtions. 

Transitions can be triggered by an 'event' string, which can be set as the 3d argument to the Transition constructor.
An event name defaults to the Transition name, which is of the form `<state-from>_to_<state-to>`.

When the transitions are defined, add them to the statemachine.
Transitions and states can accept more arguments in their constructors (explained later).
```php
$new = new State('new', State::TYPE_INITIAL);//there must be 1 initial state
$action = new State('action');//a normal state
$done = new State('done', State::TYPE_FINAL);//one of potentially many final states
$machine->addTransition(new Transition($new, $action, 'go'));//add a transition between states that is triggered by an event
$machine->addTransition(new Transition($action, $done, 'finish'));
//result: 3 states and 2 transitions defined on the statemachine
```
Transitions may (and should preferrably) be added via a Loader (more on that later) so you can define your statemachine in configuration files or a persistance backend of choice. This gives you greater flexibility for definining statemachines (and keeping them under version control).

### get information about the statemachine
The context provides contextual information about the machine and as such holds the metadata for that machine: the machine name, the entity id (both in the Identifier), a persistence adapter, used to store your state and transition data (in a backend specified by the adapter) and a domain model builder that serves as a factory to create your domain model that the statemachines logic will act upon with the help of the entity id (explained later)
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
Regular expression states take a [regular expression](https://en.wikipedia.org/wiki/Regular_expression) as their state name. When adding a regex state in a transition to the statemachine, it will expand to transitions for all *known states* in the statemachine that match the regex. This allows you to quickly setup a lot of transitions. It can be used for both the 'from' state as well as the 'to' state. Regex state names shall be prefixed with either 'regex:' or with 'not-regex:' for a negated regular expression.
```php
//action, or any state ending with 'ew'
$regex = new State('regex:/action|.*ew$/', State::TYPE_REGEX);
$pause = new State('pause');
$machine->addTransition(new Transition($regex, $pause), 'pause');
//new->pause, action->pause
```

### get information about the transitions
Transitions can be triggered by an event string, by their name (which is a concatenation of their 'from' state and 'to' state) or anonymously by trying a transition from the current state. The statemachine can be queried about the transitions that are allowed and how they are allowed in the *current state* the machine is in.
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
![interactive example](https://raw.githubusercontent.com/rolfvreijdenberger/izzum-statemachine/master/assets/images/interactive-example.png)

### performing specific transitions
Specific transitions from the current state can be performed by calling `StateMachine::handle('<event>')` (to handle a transition by a defined event name), by calling `Statemachine::<event>()` (by using the event name as a method call) or by calling `StateMachine::transition('<name>')` (by calling the transition by name). All methods will return true if a succesful transition was made.
If an event name for a transition is not specified, the event will default to the transition name, which is always in the format of `<state-from>_to_<state-to>`. Therefore you will also be able to call `Statemachine::<transition-name>()` (in case an event name is not specified).
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
Transitions can be opportunistically performed by trying to run the first transition that is allowed from *the current state* a statemachine is in. Transitions are tried in the order that they were added to the machine. Transitions can be allowed or disallowed by using 'guards': specific pieces of code for that transition that can check business rules (explained later)
```php
echo $machine->run();//perform the first transition from the current state that can run
# true
//perform as many transitions as the machine allows: 
//each transition will go to the 'to' state and will try the next transition from there,
//until it is in a final state or transitions are not possible for that new current state.
echo $machine->runToCompletion();//suppose we started in 'new', then 2 transitions will be made
# 2
```

### using an EntityBuilder to build your domain model for the machine
Transition logic should be performed to do useful work. A statemachine should operate on or with *domain objects from your application*. A subclass of EntityBuilder shall create your application specific domain model that will be used for every transition to operate on. The `EntityBuilder::build(Identifier $identifier):*` method can be overriden to return any domain object that can be used by the statemachine (eg: an Order or a Customer) identified by an entity id that is most probably a primary key for that object in your application. The domain model will be used by both the guard conditions for a transitions and the transition logic (including exit and entry logic). An instance of the of a subclass of EntityBuilder may be passed to the Context object as the third construction parameter which will be injected in the statemachine.

If no specific EntityBuilder is used, then it will default to an EntityBuilder that returns the Identifier object.
This is useful because by default, you will have the Identifier object passed to transition guards and logic so you can operate on the Identifier to get the entity id and use that to do manipulations for your problem domain.
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
* **callables**: closures/anonymous methods, instance methods and static methods that return a boolean. This is easy to use and possibly decoupled from domain models. The drawback is that all code should always be defined and in memory.
* **rules**: [business rules](https://en.wikipedia.org/wiki/Business_rule) that are fully qualified classnames of instances of izzum/rules/Rule that shall accept a domain model (via the EntityBuilder) in their constructor and shall have an implemented `Rule::applies()` method that returns a boolean *after potentially interacting with the domain model injected in the rule*. This is the most formal and most powerful guard to use because it operates on domain models in a noninvasive, loosely coupled way. Furthermore, the code (possibly expensive to run, eg: when accessing databases or network services) is only instantiated and used when needed, in contrast to all other methods for which the code should  should always be fully available in memory.
* **event handlers**: called on a specified domain object (via the EntityBuilder) in the Context. This is flexible and convenient since you define the event handlers on your domain model that is accessible by the statemachine.
* **hooks**: used by overriding a specific method `StateMachine::_onCheckCanTransition()` when subclassing the statemachine itself. This is then tailored to your application domain and offers less flexibility than the other methods since you will need to 'switch' on the transition to take a specific action.
* **hooks as event dispatchers**: by subclassing the statemachine you can implement your own event handling/dispatching library of choice.

### guard conditions 1. using callables: closures, static methods, instance methods
A [callable comes in multiple forms in php](https://php.net/manual/en/language.types.callable.php). In the next example, a [closure, or anonymous function](https://php.net/manual/en/functions.anonymous.php), is used to evaluate the boolean expression by operating on any context variables it has in it's scope and by using the automatically provided arguments of $entity and $event. $entity is the domain model returned by the Context of the statemachine (via the EntityBuilder). The event is only set when the transition was initiated by an event. The guard can operate on the $entity (which defaults to the Identifier if no Builder is used) to calculate the boolean result.

In general, all callables will be passed the 2 arguments $entity and $event and should have a method signature of `[static] public function <name>($entity, $event = null): boolean`. 

If you define your transitions in a php script you have more options than when defining your transitions via configuration that you load via a file or a persistance backend.
When loading the transition configurations you can only use the form `\fully\qualified\Class::staticMethod` for callables since you cannot define closures as a string in your configuration.

Check the example in `examples/inheritance` for using instance methods as callables. see `tests/izzum/statemachine/TransitionTest::shouldAcceptMultipleCallableTypes` for all possible implementations of callables in the izzum statemachine.
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
[A business rule](https://en.wikipedia.org/wiki/Business_rule) is used by creating a Rule class (a subclass of `\izzum\rules\Rule`) and by setting the fully qualified class name as a string on the Transition. The Rule class is dynamically instantiated only when necessary for checking the transition and wil have the domain model (provided by the Context via the EntityBuilder) injected in it's constructor. The Rule should have a `Rule::applies()` method that will return a boolean value that will be calculated by querying the domain model or any other data source (eg: services, apis, database etc).

The `FalseRule` rule is provided as an example. you should write your own specifcally for your problem domain. See `examples/trafficlight` for an implementation using rules and a domain object with an EntityBuilder.

A rule should never have side effects and should only return a boolean. 

Multiple rules can be chained together (using [logical conjunction](https://en.wikipedia.org/wiki/Logical_conjunction): and) by specifying multiple fully qualified rule class names seperated by a `,` comma.

Testing is facilitated because you can inject [test doubles](https://en.wikipedia.org/wiki/Test_double) (mocks/stubs) in your Rule.
```php
$forbidden = new State('forbidden');
$rule = '\izzum\rules\FalseRule';
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
A Rule subclass will be injected with your domain object in it's constructor (which is made by the EntityBuilder) and will query the object to see if the business rule will apply. 
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

### guard conditions 5. using a hook with an event dispatcher
A big Advantage of subclassing is that it allows you to use the hooks to add your own logic to satisfy your needs. If you want to respond to events from your statemachine, add the possibility to add eventlisteners to your statemachine and add an event dispatcher in the subclass and you can respond to the statemachines actions in any way you would want (eg: using transition data to send out a specific event).
Add a public method to the statemachine to add event listeners and dispatch your events from the subclass's hooks, using Transition, State and StateMachine data in the Event if necessary.
A good event dispatcher to use would be the [Symfony EventDispatcher component](http://symfony.com/doc/current/components/event_dispatcher/introduction.html) which you can use to return the boolean evaluation parameter for the guard to allow or disallow the transition.


### logic actions: state entry actions, state exit actions and transition actions
State exit logic, transition logic and state entry logic all provide ways to associate custom domain logic to that phase of the transition from the 'from' state to the 'to state.
If the logic handlers are not specified on a transition of on the states then nothing will happen by default. The logic handlers can operate on a domain model returned by an EntityBuilder.

There are 4 distinct phases when trying to perform a transition:
* check the guard if the transition is allowed and if true:
* perform state exit logic: associated with the 'from' state, independent of the state the transition is going. this is always performed when leaving a state as part of a transition, no matter which state the transition goes to.
* perform transition logic: asoociated with the transition itself which has a specific 'from' and 'to' state and enters the new state. This is always performed when the state has been exited and before a new state is entered.
* perform state entry logic: associated with the 'to' state, independent from the state the transition came from. This is always performed when entering a state as part of a transition, no matter from which state the transition came from.

Comparable to the logic of using guards, the exit~ transition~ and exit logic can be performed by `callables`, `commands` [see the Command design pattern](https://en.wikipedia.org/wiki/Command_pattern), `event handlers` and `hooks`.


There are multiple ways to set logic handlers on transitions:
* **callables**: closures/anonymous methods, instance methods and static methods. This is easy to use and possibly decoupled from domain models. The drawback is that all code should always be defined and in memory when the transitions are defined.
* **commands**: [Commands (the design pattern)](https://en.wikipedia.org/wiki/Command_pattern) are encapsulated reusable logic and are specified as fully qualified classnames of clases of `izzum/command/Command` that shall accept a domain model (via the EntityBuilder) in their constructor and shall have an implemented `Command::execute()` method that can *potentially interact with the domain model injected in the command*. This is the most formal and most powerful way to use handling logic because it operates on domain models in a noninvasive, loosely coupled way. Furthermore, the code (possibly expensive to run, eg: when accessing databases or network services) is only instantiated and used when needed, in contrast to all other methods for which the code  should always be fully in memory.
* **event handlers**: called on a specified domain object (via the EntityBuilder) in the Context. This is flexible and convenient since you define the event handlers on your domain model that is accessible by the statemachine.
* **hooks**: used by overriding a specific methods: `StateMachine::_onExitState()`, `StateMachine::_onTransition()` and `StateMachine::_onEnterState()` when subclassing the statemachine itself. This is then tailored to your application domain and offers less flexibility than the other methods since you will need to 'switch' on the transition to take a specific action.
* **hooks as event dispatchers**: by subclassing the statemachine you can implement your own event handling/dispatching library of choice in the hooks provided.


### logic actions 1. general transition flow (callables, events, hooks)
for general implementation details for callables, events and hooks, see the example section for guard conditions.

The general transition logic sequence is as follows
* exit:
    * hook: `_onExitState($transition)`
    * event handler: `$entity->onExitState($identifier, $transition)`
    * exit command execution
    * callable: `$callable($entity)`
* transition:
    * hook: `_onTransition($transition)`
    * event handler: `$entity->onTransition($identifier, $transition)`
    * transition command execution
    * callable: `$callable($entity)`
* exit:
    * event handler: `$entity->onEnterState($identifier, $transition)`
    * entry command execution
    * callable: `$callable($entity)`
    * hook: `_onEnterState($transition)`


### logic actions 2. commands
A [command](https://en.wikipedia.org/wiki/Command_pattern) is used by creating a seperate Command class (a subclass of `\izzum\command\Command`) and by setting it's fully qualified class name as a string on the transition or states. 

The Command class is dynamically instantiated only when necessary for performing the logic and wil have the domain model (provided by the Context via the EntityBuilder) injected in it's constructor. The Command should have a `Command::execute()` method that will perform the logic by *potentially operating on the domain model* or any other data source (eg: services, apis, database etc).
The `NullCommand` command is provided as an example. you should write your own specifcally for your problem domain. See `examples/trafficlight` for an implementation using commands and a domain object with an EntityBuilder.

Multiple commands can be chained together as a [Composite](https://en.wikipedia.org/?title=Composite_pattern) by specifying multiple fully qualified command class names seperated by a `,` comma. Keep in mind that if you have 3 commands in a transition of which the last one throws an exception you might need to perform the transition again, thereby performing the first 2 again.

A command subclass will be injected with your domain object in it's constructor (which is made by the EntityBuilder) and can use the object to perform it's logic.

The configuration of a Transitions or States with a Command should be done by providing a fully qualified classname. The php application must be able to find the class via autoloading (which is a wrapper around including files)

In contrast to Rules, commands will most probably have side effects as they will have behaviour that affects your program.

The advantage of using Commands for transition logic is that there is no coupling between your domain model and the statemachine, making your application code much cleaner and testable.
Testing is facilitated because you can inject [test doubles](https://en.wikipedia.org/wiki/Test_double) (mocks/stubs) in your command.

![traffic light example](https://raw.githubusercontent.com/rolfvreijdenberger/izzum-statemachine/master/assets/images/traffic-light-output.png )

![traffic light state diagram](https://raw.githubusercontent.com/rolfvreijdenberger/izzum-statemachine/master/assets/images/state-diagram-plantuml-traffic-light.png )

```php
Class OrderDelivery extends Command {
public function __construct(Order $order) { $this->order = $order;}
  protected function _execute() { $this->order->deliver(); }
}
$command = '\izzum\command\OrderDelivery';
//assume we are using the rule from the example
$transition = new Transition($action, new State('shipping'), 'ship', $rule, $command);

```
### using a persistance adapter to store state
Persistence adapters provide an abstraction to write state data and transition history to a persistence backend of choice, so your statemachine can be used in multiple consecutive php processes because it remembers in which state it is. 

Out of the box izzum provides persistance adapters for sql based backends for [postgresql](http://www.postgresql.org), [mysql](http://www.mysql.com), [sqlite](http://www.sqlite.org) (they all function via the [php PDO library](http://www.php.net/PDO)), the [redis key/value database(nosql)](http://www.redis.io), and the [document based mongoDB (nosql)](http://www.mongodb.org). 
These Adapters all support the full range of abilities that their php drivers support. They are implemented using known stable php modules (PDO, redis, mongo) and more information can be found in the phpdocs in the classes and on [php.net](http://php.net).

A semi persistant adapter is the php session adapter and a non-persistent adapter is the memory adapter (the default). 

An adapter is a subclass of `persistence\Adapter` and you provide an instance as the third argument for the Context object. The persistent adapters provided have a fully defined backend structure, examples of which can be found in `assets/<backend>`. If you use one of those backend adapters you should explicitely 'add' the state/history data (only once) to the backend by calling `$statemachine->add()` (with an optional message to specify why, where or by whom the machine was created) to create the initial history record. 

The main reason to use the backend adapter is so you can permanently store all records of transitions in a history structure (for analysis and possibly accounting) and to store the current state. When recreating the statemachine in a different process later in time, it will automatically retrieve the current state and you can continue with transitions where you left off.

All persistence adapters provided do double duty to act as a Loader from which you can load the full statemachine definition that is defined in the backend. Multiple statemachine types can be configured in each backend. see the section on using the Loader for more detail.

Custom adapters for different backends can easily be written for your specific application domain. You would need to create the right datastructures in your backend to support your needs and to write some custom code by subclassing Adapter and to read and write the data to that backend. The provided adapters can guide you through the process of writing your own.

### persistance 1. storing state data in memory
The Memory persistence adapter is the default one used by the context if you do not provide another Adapter explicitly.
It is only useful in 1 single php process, since the state is only persisted in memory and therefore lost when the process stops.
Good examples for where to use it are in a php daemon or an interactive php process with a limited lifetime. See `examples/interactive` and `examples/trafficlight` for an implementation of a statemachine in memory.

### persistance 2. storing state data in a session
Pph sessions can be used to store data in. They are also limited in lifetime but persist between page refreshes as long as the session is valid. Therefore they are good for shopping carts, wizard like forms and other html based frontends with page refreshes. See `examples/session` for an example that switches page colors (of the rainbow, defined as states in the statemachine) for page refreshes. The data is lost when the php session expires.
```php
$adapter = new Session();
$machine = new StateMachine(new Context(new Identifier('session', 'rainbow-machine'), null, $adapter));
$machine->run();

```

![session example](https://raw.githubusercontent.com/rolfvreijdenberger/izzum-statemachine/master/assets/images/session-rainbow-example.png)

### persistance 3. storing transition history and state data in sql backends
SQL based backends are abundantly available in most applications. the PDO adapter provides access to all backends made available via the PDO driver. There are full sql schemas in `assets/sql` for postgresql, mysql and sqlite available with full documentation about the design in `assets/sql/postgresql.sql`. Once you create those tables you and provide the right credentials to the PDO adapter you are ready to start storing your state in your database and you can also fully define your machines including states and transitions with their associated actions in the tables.
The data is permanently stored, providing you with the history of all your machines and a way to keep track of all states without storing state in the tables for your domain objects.
```php
$identifier('UUID-1234-ACD3-2156', 'data-migration-machine');
$adapter = new PDO('pgsql:host=localhost;port=5432;dbname=izzum');
//or for mysql
$adapter = new PDO('mysql:host=localhost;dbname=izzum');
//or for sqlite
$adapter = new PDO('"sqlite:izzum.db"');
$context = new Context($identifier, $builder, $adapter);
$statemachine = new StateMachine($context);
$adapter->load($statemachine);//the adapter can also act as a loader
$statemachine->add('creation of machine...');
```

### persistance 4. storing transition history and state data in redis or mongodb
Redis is a nosql key/value database and MongoDB is a nosql document based database.
Both are schemaless and as such need no configuration to start storing state and transition history.
Both the redis and the mongodb provide the possibility to store full statemachine configurations in JSON format (see the Loader examples for more info).
```php
$identifier('UUID-1234-ACD3-2156', 'data-migration-machine');
$adapter = new Redis('127.0.0.1', 6379);
//or use mongodb
$adapter = new MongoDB('mongodb://localhost:27017');
$context = new Context($identifier, $builder, $adapter);
$statemachine = new StateMachine($context);
$adapter->load($statemachine);//the adapter can also act as a loader
$statemachine->add('creation of machine...');
```

### loading statemachine configurations
By using one of the provided Loader classes you are able to load (multiple) statemachine definitions from [JSON](https://en.wikipedia.org/wiki/JSON), [XML](https://en.wikipedia.org/wiki/XML) or [YAML](https://en.wikipedia.org/wiki/YAML). They all can load data from a file and from a string). Loading data can also be done by using one of the provided persistence adapters (redis and mongodb use the JSON format but can be subclassed to load any other format).

By using a Loader class you do not have to configure your statemachine in a php script and make maintaining and defining statemachines easier and reusable.

Loader itself is an interface with one simple method: `Loader::load($statemachine):int` which populates the statemachine and returns the count of added transitions. Custom loaders can be written and should preferrably delegate the loading to the LoaderArray class which already does some heavy lifting for your (mostly regarding regex States and priorities). The LoaderArray class can work with subclasses of State, Transition and StateMachine if you want to extend the izzum implementation).

### loading statemachine configurations: examples for XML, JSON, YAML
XML example:
see `assets/xml` for an example xml file definition and the xml schema to use with the loader. The loader is `izzum\loader\XML`.
```php
$statemachine = new StateMachine(new Context(new Identifier('198442' , 't-shirt-production-facility-machine')));
$file = __DIR__ . '/machines.xml';
$loader = XML::createFromFile($file);
$loader->load($statemachine);
$statemachine->runToCompletion();
```
JSON example
see `assets/json` for an example json file definition and the json schema to use with the loader. The loader is `izzum\loader\JSON`.
```php
$statemachine = new StateMachine(new Context(new Identifier('btc-data-generator' , 'blockchain-parsing-machine')));
$file = __DIR__ . '/machines.json';
$loader = JSON::createFromFile($file);
$loader->load($statemachine);
$statemachine->runToCompletion();
```

YAML example
see `assets/json` for an example yaml file definition. The loader is `izzum\loader\YAML`.
```php
$statemachine = new StateMachine(new Context(new Identifier('wolverine' , 'mutant-machine')));
$file = __DIR__ . '/machines.yaml';
$loader = YAML::createFromFile($file);
$loader->load($statemachine);
$statemachine->runToCompletion();
```

### loading statemachine configurations: examples for sql backends
See the sql schemas in `assets\sql` (postgresql, mysql and sqlite) and the documentation provided in `assets\sql\postresql.sql` for how to store your configuration data in the schema. Simply create the schema in your sql backend of choice, fill the tables with the statemachine configuration, then in php you configure the adapter with the right connection string (like host, port, database name) via a [data source name](https://en.wikipedia.org/wiki/Data_source_name) and you're off.
```php
$statemachine = new StateMachine(new Context(new Identifier('spiderman' , 'superhero-machine')));
$adapter = new PDO('pgsql:host=208.64.123.130;port=5432;dbname=izzum');
$adapter->load($statemachine);
$statemachine->run();
```

### loading statemachine configurations: examples for mongodb and redis
MongoDb and Redis persistence adapters can also be used as a Loader. The implementation uses JSON as specified in `assets/json`.
You should load the JSON data in the backend in a specific location.
For MongoDB you would store the JSON data (which will internally be converted to a document) in the <database>.configuration collection. You can store multiple configurations in the collection and the adapter will automatically find the one matching the machine name in the collection.
For Redis you would store the JSON string in the `<configurable-prefix>:configuration:<machine-name>` key if you want to use multiple configurations in different keys. Alternatively, you can store the JSON string in the `<configurable-prefix>:configuration` key if you want to store multiple configurations in one key. The adapter will automatically find the configuration by matching the machine name in the specific key and will fallback to the default key.

For both Adapters it will be easier to maintain multiple machines if you put 1 machine definition in one JSON string.
see the `tests\izzum\statemachine\persistence\RedisTest` and `tests\izzum\statemachine\persistence\MongoDBTest` for some more details.
```php
$redis = new Redis('127.0.0.1', 6379);
$machine = new StateMachine(new Context(new Identifier(1988442, 'crazy-machine'), null, $redis));
//set the configuration. Normally, this would be done directly on redis in a 
//seperate process before using the statemachine (eg: during deployment)
$configuration = file_get_contents(__DIR__ .'/redis-configuration-example.json');
$redis->set(Redis::KEY_CONFIGURATION, $configuration);
//load the machine
$redis->load($machine);

```

### loading and storing data from different backends: ReaderWriterDelegator
If you want to load your data from a specific source but want to write your data to a different sink then you should use the ReaderWriterDelegator class. It accepts a Loader and an Adapter instance. In this way, you can mix and match where you want to read data from and where you want to write it to.
```php
$loader = XML::createFromFile(__DIR__ . '/configuration.xml');
$writer = new PDO('pgsql:host=208.64.123.130;port=5432;dbname=izzum');//postgres backend
$identifier = new Identifier('198442', 'awesome-machine');
$delegator = new ReaderWriterDelegator($loader, $writer);
$context = new Context($identifier, null, $delegator);
$machine = new StateMachine($context);
$delegator->load($machine);//loads from xml file
$machine->run();//stores data in postgres
```

###generating uml diagrams from a statemachine
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





