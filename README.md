
[![Build Status](https://travis-ci.org/rolfvreijdenberger/izzum-statemachine.svg?branch=master)](https://travis-ci.org/rolfvreijdenberger/izzum-statemachine/) 
[![Total Downloads](https://poser.pugx.org/rolfvreijdenberger/izzum-statemachine/downloads.svg)](https://packagist.org/packages/rolfvreijdenberger/izzum-statemachine) 
[![Latest Stable Version](https://poser.pugx.org/rolfvreijdenberger/izzum-statemachine/v/stable.svg)](https://packagist.org/packages/rolfvreijdenberger/izzum-statemachine) 
[![Code Coverage](https://scrutinizer-ci.com/g/rolfvreijdenberger/izzum-statemachine/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/rolfvreijdenberger/izzum-statemachine/?branch=master)
[![License](https://poser.pugx.org/rolfvreijdenberger/izzum-statemachine/license.svg)](https://packagist.org/packages/rolfvreijdenberger/izzum-statemachine)

###A superior, extensible and flexible statemachine library
A [finite statemachine](https://en.wikipedia.org/wiki/Finite-state_machine "finite statemachine on wikipedia") implementation that allows you to add states and to define the logic of transitions between any and all states for a domain object while keeping that object unaware that it is governed by a statemachine.
### walkthrough
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

### get information about the statemachine
The context provides contextual information about the machine and as such holds the metadata for that machine: the machine name, the entity id (both in the Identifier), a persistence adapter, used to store your state and transition data (there are adapters for Memory (the default), Session, Redis, and PDO (sql for postgres, sqlite and mysql)) and a domain model builder ('EntityBuilder') that serves as a factory to create your domain model with the help of the entity id (explained later)
```php
$context = $machine->getContext();
echo $context->getPersistenceAdapter();//echo works because of Memory::__toString()
>>> Memory
echo $context->getEntityId();//get the id for your domain model (entity)
>>> 198442
echo $context->getMachine();//get the name of the statemachine
>>> order
echo count($machine->getStates());//get the defined states directly from the machine
>>> 3
echo count($machine->getTransitions());//get the defined transitions directly from the machine
>>> 2
```

### get information about the states
The current state will be the initial state when the machine is initialized.
The state can be set directly or it can be retrieved from a persistance backend when one is used. (explained later)
```php
$state = $machine->getCurrentState();
echo $state->getName();
>>> new
echo $state->getType();
>>> initial
echo $machine->getInitialState();//echo works because of State::__toString()
>>> new
foreach($machine->getStates() as $state) {
    echo $state->getName();
}
>>> new, action, done
```
### adding regular expression states that expand to multiple transitions
TO DESCRIBE
### get information about the transitions
Transitions can be triggered by an event string, by their name (which is a concatenation of their 'from' state and 'to' state) or anonymously by trying a transition from the current state. The statemachine can be queried about the transitions that are allowed and how they are allowed in the current state the machine is in.
```php
echo $machine->hasEvent('go');//the current state 'new', has a transition that can be triggered by the 'go' event
>>> true
echo $machine->hasEvent('finish');//current state is 'new' and 'finish' is only a valid event for the 'action' state
>>> false
echo $machine->canHandle('go');//this will check the guard logic for the 'go' event (explanation later)
>>> true
echo $machine->canTransition('new_to_action');//transitions have a name derived from their 'from' and 'to' states
>>> true
echo $machine->canTransition('action_to_done');//not in the 'action' state
>>> false
echo $machine->getCurrentState()->hasTransition('new_to_action');//check the state itself for a transition
>>> true
foreach ($machine->getTransitions() as $transition) {
    echo $transition->getName() . ":" . $transition->getEvent(); 
}
>>> new_to_action:go, action_to_done:finish
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
Transitions can be opportunistically performed by trying to run the first transition that is allowed from the current state a statemachine is in. Transitions can be allowed or disallowed by using 'guards': specific pieces of code for that transition that can check business rules (explained later)
```php
echo $machine->run();//perform the first transition from the current state that can run
>>> true
//perform as many transitions as the machine allows: 
//each transition will go to the 'to' state and will try the next transition from there,
//until it is in a final state or transitions are not possible for that current state.
echo $machine->runToCompletion();//suppose we started in 'new', then 2 transitions will be made
>>> 2
```

### guard conditions on transitions
Guard conditions are dynamically evaluated boolean expressions that either allow or disallow a transition. Guards should never have side effects and should only calculate the boolean result.
If a guard is not specified on a transition then the transition will be allowed by default.
There are multiple ways to set guard conditions on transitions:
* **callables**: closures/anonymous methods, instance methods and static methods that return a boolean. This is easy to use and possibly decoupled from domain models. The drawback is that all code should always be defined and in memory when the transitions are defined.
* **rules**: business rules that are fully qualified classnames of instances of izzum/rules/Rule that accept a domain model in their constructor and an implemented `Rule::applies()` method that returns a boolean. This  is the most formal and most powerful guard to use because it operates on domain models in a noninvasive way. Furthermore, the code (possibly expensive to run, eg: when accessing databases or network services) is only instantiated and used when needed, in contrast to all other methods for which the code should  should always be fully available at runtime when the transitios are defined.
* **event handlers**: called on a specified domain object via the EntityBuilder in the Context. This is flexible and convenient since you define the event handlers on your domain model that is accessible by the statemachine.
* **hooks**: used by overriding a specific method `StateMachine::__onCheckCanTransition()` when subclassing the statemachine itself. This is then tailored to your application domain and offers less flexibility than the other methods since you will need to 'switch' on the transition to take a specific action.

### guard conditions 1. using callables: closures, static methods, instance methods
A [callable comes in multiple forms in php](https://php.net/manual/en/language.types.callable.php). In the next example, a [closure, or anonymous function](https://php.net/manual/en/functions.anonymous.php), is used to evaluate the boolean expression by operating on any context it has in it's scope and by using the automatically provided arguments of $entity and $event. $entity is the domain model returned by the Context of the statemachine, event is only set when the transition was initiated by an event. The guard can operate on the $entity to calculate the boolean result.
In general, all callables will be passed the 2 arguments $entity and $event and should have a method signature of `[static] public function <name>($entity, $event = null): boolean`. 
Check the example in `examples\inheritance` for using instance methods as callables. see `tests/izzum/statemachine/TransitionTest::shouldAcceptMultipleCallableTypes` for all possible implementations of callables in the izzum statemachine.
```php
$forbidden = new State('forbidden');
$closure = function($entity, $event){return false;};
$transition = new Transition($new, $forbidden, 'thoushaltnotpass', null, null, $closure);
// or: $transition->setGuardCallable($closure);
$machine->addTransition($transition);
echo $machine->hasEvent('thoushaltnotpass');
>>> true
echo $machine->handle('thoushaltnotpass');//transition will not be made
>>> false
echo $machine->transition('new_to_forbidden');
>>>> false
echo $machine->getCurrentState();//still in the same state
>>> new
```
### guard conditions 2. using business rules
A business rule is provided by using a fully qualified class name of an implemenation of `\izzum\rules\IRule`. The Rule class is dynamically instantiated and wil have the domain model (provided by the Context) injected in it's constructor. The Rule should have a `Rule::applies()` method that will return a boolean value that will be calculated by querying the domain model that was previously injected.
The `False` rule is provided as an example. you should write your own specifcally for your problem domain. (see `examples/trafficlight` for an implementation using rules)
```php
$forbidden = new State('forbidden');
$rule = '\izzum\rules\False';
$transition = new Transition($new, $forbidden, 'thoushaltnotpass', $rule);
// or: $transition->setRuleName($rule);
$machine->addTransition($transition);
echo $machine->hasEvent('thoushaltnotpass');
>>> true
echo $machine->handle('thoushaltnotpass');//transition will not be made
>>> false
echo $machine->transition('new_to_forbidden');
>>>> false
echo $machine->getCurrentState();//still in the same state
>>> new
```
### guard conditions 3. using event handlers
TO DESCRIBE
### guard conditions 4. using hooks
TO DESCRIBE
### state entry action, state exit actions and transition actions
TO DESCRIBE
### actions 1. callables, commands, events, hooks
TO DESCRIBE
### using an EntityBuilder to build your domain model to be used
TO DESCRIBE
### using a persistance adapter to store state
TO DESCRIBE
### persistance 1. storing state data in memory
TO DESCRIBE
### persistance 2. storing state data in a session
TO DESCRIBE
### persistance 3. storing transition history and state data in sql backends
TO DESCRIBE
### persistance 4. storing transition history and state data in redis
TO DESCRIBE
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


###generated state diagram for the traffic light machine (see examples)
![traffic light state diagram](https://raw.githubusercontent.com/rolfvreijdenberger/izzum-statemachine/master/assets/images/state-diagram-plantuml-traffic-light.png )

###output for the traffic light machine (see examples)
![traffic light state diagram](https://raw.githubusercontent.com/rolfvreijdenberger/izzum-statemachine/master/assets/images/traffic-light-output.png )


