
[![Build Status](https://travis-ci.org/rolfvreijdenberger/izzum-statemachine.svg?branch=master)](https://travis-ci.org/rolfvreijdenberger/izzum-statemachine/) 
[![Total Downloads](https://poser.pugx.org/rolfvreijdenberger/izzum-statemachine/downloads.svg)](https://packagist.org/packages/rolfvreijdenberger/izzum-statemachine) 
[![Latest Stable Version](https://poser.pugx.org/rolfvreijdenberger/izzum-statemachine/v/stable.svg)](https://packagist.org/packages/rolfvreijdenberger/izzum-statemachine) 
[![Code Coverage](https://scrutinizer-ci.com/g/rolfvreijdenberger/izzum-statemachine/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/rolfvreijdenberger/izzum-statemachine/?branch=master)
[![License](https://poser.pugx.org/rolfvreijdenberger/izzum-statemachine/license.svg)](https://packagist.org/packages/rolfvreijdenberger/izzum-statemachine)

###A superior, extensible and flexible statemachine library
A [finite statemachine](https://en.wikipedia.org/wiki/Finite-state_machine "finite statemachine on wikipedia") 
implementation that allows you to add state for any domain object and to define
the logic of transitions between any and all states for that object while keeping your object
unaware that it is governed by a statemachine.

see the inline documentation and the examples folder for how to use.
the old documentation is in README.md.old. this documentation will be upgraded soon.

###installation
use [composer](https://getcomposer.org/) to install the project.
Create a file called composer.json with these lines: 
```
{
    "require": {
        "rolfvreijdenberger/izzum-statemachine": "~3.1"
    }
}
```
and install the package with:
```
composer install
```
You will find the izzum package in ./vendor/rolfvreijdenberger/izzum-statemachine.
You can also download it directly from github.

###generated state diagram for the traffic light machine (see examples)
![traffic light state diagram](https://raw.githubusercontent.com/rolfvreijdenberger/izzum-statemachine/master/assets/images/state-diagram-plantuml-traffic-light.png )

###output for the traffic light machine (see examples)
![traffic light state diagram](https://raw.githubusercontent.com/rolfvreijdenberger/izzum-statemachine/master/assets/images/traffic-light-output.png )


