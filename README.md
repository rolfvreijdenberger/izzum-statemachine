izzum [![Build Status](https://travis-ci.org/rolfvreijdenberger/izzum.svg?branch=master)](https://travis-ci.org/rolfvreijdenberger/izzum/) [![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/rolfvreijdenberger/izzum/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/rolfvreijdenberger/izzum/?branch=master) [![Code Coverage](https://scrutinizer-ci.com/g/rolfvreijdenberger/izzum/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/rolfvreijdenberger/izzum/?branch=master)
=====
'_Yo man, who gots the izzum for tonights festivities?_'


- see [http://documentup.com/rolfvreijdenberger/izzum](http://documentup.com/rolfvreijdenberger/izzum/recompile "navigable version on documentup.com") for a navigable
version of this document.
- Dont' want to read? Just skip to [examples](#examples)

##about##
###A superior, extensible and flexible statemachine library###
A [finite statemachine](https://en.wikipedia.org/wiki/Finite-state_machine "finite statemachine on wikipedia") 
implementation that allows you to add state to any domain object and to define
the logic of transitions between any and all states for that object.
Itrevolves around the [Command pattern](https://en.wikipedia.org/wiki/Command_pattern "command pattern on wikipedia") 
for executing transition logic and uses business rules for the transition guard logic.

By using the [open/closed principle](https://en.wikipedia.org/wiki/Open/closed_principle "open/closed principle on wikipedia")
we give you the means to adjust the logic provided by this library to your needs.
Subclassing and/or using hooks in the code allow you to add logging, event dispatching etc.

###thoroughly documented###
find out how it works and what matters in clean code and excellent inline
documentation with explanations of how and why you'd use it and what you can 
potentially use it for. The unittests can serve as extra documentation on 
how to use it.

###Use your own specific domain models###
It can be tailored to your application domain by using an adapter for 
your persistence layer (postgresql/mysql, memcache, sessions etc), a loader 
for your purpose (yaml~, json~, postgresql) and an abstraction of your 
[application specific domain models](https://en.wikipedia.org/wiki/Domain_model "domain model on wikipedia") 
by using a subclassed builder that creates the domain model you want to associate
with a statemachine. 

This allows you to operate on your own domain models by using them as an argument to your 
Command (transition logic) and Rule (transition guard) classes.


###Easy configuration###
Izzum makes it simple to define a statemachine with transitions and states by
providing simple objects that handle the loading logic for you.
A LoaderData object contains data to fully configure one transition between 2 states
and a Loader class can handle the loading of the statemachine via LoaderData for you.
Implement your own loader to adapt it to your configuration wishes like getting
the data from a database or file.

###Well designed interface###
Clients of your code (your application) will only need to use a couple of lines
of code to interact with your statemachine and have access to a well designed
interface in case there is a need for more advanced manipulation.
```php
<?php
$id = $order->getId();
//abstract factory pattern is used to create a family of related objects
$factory = new ConcreteFactory();
$machine = $factory->getStateMachine($id);
$machine->run();


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

###Formal ways to encapsulate the logic for transitions###
All logic for a single transition is encapsulated in two classes:
- a subclass of Rule (see the package) which makes sure that a transition is 
allowed/disallowed by checking if a business rule applies.
- a subclass of Command (see the package) which executes logic that is part
of the transition that is currently being executed between two states.

Although you have to do some work to create the rules and commands and have them
accept your domain model as a dependency in their constructor, it provides you
with a great way to seperate your concerns in their own (testable) classes.

###Battle proven and fully unittested with high code coverage### 
Using industry best practices for writing code with tests to back it up. 
Making use of proven design patterns to allow you to tailor it to your needs.

It is used in a high load commercial environment with a postgresql backend 
for one of the best Dutch fiber ISP organisations for their order management system.

###no dependencies###
There are no dependencies on third party libraries. php 5.3 or higher

###License###
MIT

###Automated uml state diagram creation###
Create uml state diagrams from a statemachine [with plantuml](http://plantuml.sourceforge.net/ "plantuml on sourceforge") 
It is a great way to visualize your machine with all the Rule/Command logic, 
making it easy to communicate with business users or stakeholders.

see the examples section for some diagrams.

##Examples##

###uml diagram for an order system###
![generated plant uml statediagram from izzum statemachine](https://raw.githubusercontent.com/rolfvreijdenberger/izzum/master/assets/state-diagram-plantuml.png )

###php example for the simplest case###
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

###plantuml diagram and the code to create it###
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
![generated plant uml statediagram from izzum statemachine](https://raw.githubusercontent.com/rolfvreijdenberger/izzum/master/assets/state-diagram-plantuml-coffee.png )


##contributors and thank you's##
- Richard Ruiter
- Romuald Villetet
- Harm de Jong
- the statemachine package was influenced by the [yohang statemachine](https://github.com/yohang/Finite "Finite on github") , thanks for some good work.
- creation of README.md markdown with the help of [dillinger.io/](http://dillinger.io/)
- nice layout of this file: [documentup.com](http://documentup.com/rolfvreijdenberger/izzum)
- continuous integration servers: https://travis-ci.org and https://scrutinizer-ci.com





