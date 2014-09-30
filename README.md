izzum
=====
'_Yo man, who gots the izzum for tonights festivities?_'

[![Build Status](https://travis-ci.org/rolfvreijdenberger/izzum.svg?branch=master)](https://travis-ci.org/rolfvreijdenberger/izzum/)

**An extensible statemachine library** using the [open/closed principle](https://en.wikipedia.org/wiki/Open/closed_principle "open/closed principle on wikipedia"), with a 
[finite statemachine](https://en.wikipedia.org/wiki/Finite-state_machine "finite statemachine on wikipedia") implementation that revolves around the [Command pattern](https://en.wikipedia.org/wiki/Command_pattern "command pattern on wikipedia") for 
doing transitions and using business rules for the transition guard logic.

**Very well documented** so you can find what you want to know.

**It can be tailored to your application domain** by using an adapter for 
your persistence layer (postgresql/mysql, memcache, sessions etc), a loader 
for your purpose (yaml~, json~, postgresql) and an abstraction of your 
[application specific domain models](https://en.wikipedia.org/wiki/Domain_model "domain model on wikipedia") by using a subclassed builder. 

**This allows you to operate on your own domain models** by using them as an argument to your 
Command (transition logic) and Rule (transition guard) classes.

**Easy configuration and a well defined interface** makes it simple to define a statemachine
with transitions and states with bussiness logic that is properly encapsulated.

**It is fully unittested with high code coverage** using best practices. It is also used
in a high load environment with a postgresql backend for one of the best
Netherlands' fiber ISP organisations for their order management system.

**Automated state diagram creation** [with plantuml](http://plantuml.sourceforge.net/ "plantuml on sourceforge") by providing a statemachine is a simple way to
visualize your machine with all the Rule/Command logic, making it easy to communicate with
business users or stakeholders.


**packages**:
- statemachine (transitions guarded by rules and logic executed by commands)
- rules (business rules encapsulated)
- commands (the command pattern)


**contributors**:
- Richard Ruiter
- Romuald Villetet
- Harm de Jong

**thanks**:
- the statemachine package was influenced by the [yohang statemachine](https://github.com/yohang/Finite "Finite on github") , thanks for some good work.
- creation of README.md markdown with the help of [dillinger.io/](http://dillinger.io/)

**examples**
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
//the order with id 15236
$machine = $factory->getMachine('15236');
//run the first transition allowed
$machine->run();



```