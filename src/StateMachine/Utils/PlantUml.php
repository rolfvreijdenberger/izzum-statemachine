<?php

namespace Izzum\StateMachine\Utils;

use Izzum\StateMachine\StateMachine;
use Izzum\StateMachine\Exception;

/**
 * creates a uml statediagram in plantuml format for a statemachine.
 *
 * This mainly serves as a simple demo.
 *
 * More diagrams can be created from the data in a persistence layer.
 * for example:
 * - activity diagrams: which transitions has en entity actually made (and when)
 * - flow diagrams: a combination of the state diagram and the activity diagram
 * (show the full state diagram and highlight the states the entity has
 * actually gone through)
 * - heat maps: a count of every state for every entity.
 *
 * @link http://www.plantuml.com/plantuml/ for the generation of the diagram
 *       after the output is created from createStateDiagram
 */
class PlantUml
{
    /**
     * create an alias for a state that has a valid plantuml syntax
     */
    private function plantUmlStateAlias(string $original): string
    {
        $alias = ucfirst(implode("", array_map(ucfirst(...), explode("-", $original))));
        return $alias;
    }

    /**
     * get skins for layout
     *
     * @link http://plantuml.sourceforge.net/skinparam.html
     * @link http://plantuml.com/classes.html#Skinparam
     */
    private function getPlantUmlSkins(): string
    {
        $output = <<<SKINS
skinparam state {
    FontColor  black
    FontSize 11
    FontStyle bold
    BackgroundColor  orange
    BorderColor black
    ArrowColor red
    StartColor lime
    EndColor black

}
skinparam stateArrow {
    FontColor  blue
    FontSize 9
    FontStyle italic
}
skinparam stateAttribute {
    FontColor  black
    FontSize 9
    FontStyle italic
}
SKINS;
        return $output;
    }

    private static function escape(string $string): string
    {
        $string = addslashes($string);
        return $string;
    }

    /**
     * creates plantuml state output for a statemachine
     *
     * @return string plant uml code, this can be used to render an image
     * @link http://www.plantuml.com/plantuml/
     * @link http://plantuml.sourceforge.net/state.html
     * @throws Exception
     */
    public function createStateDiagram(StateMachine $machine): string
    {
        $transitions = $machine->getTransitions();

        // all states are aliased so the plantuml parser can handle the names
        $aliases = [];
        $endStates = [];
        $EOL = "\\n\\" . PHP_EOL; /* for multiline stuff in plantuml */
        $NEWLINE = PHP_EOL;

        // start with declaration
        $uml = "@startuml" . PHP_EOL;

        // skins for colors etc.
        $uml .= $this->getPlantUmlSkins() . PHP_EOL;

        // the order in which transitions are executed
        $order = [];

        // create the diagram by drawing all transitions
        foreach ($transitions as $t) {
            // get states and state aliases (plantuml cannot work with certain
            // characters, so therefore we create an alias for the state name)
            $from = $t->getStateFrom();
            $fromAlias = $this->plantUmlStateAlias($from->getName());
            $to = $t->getStateTo();
            $toAlias = $this->plantUmlStateAlias($to->getName());

            // get some names to display
            $command = $t->getCommandName();
            $rule = self::escape($t->getRuleName());
            $nameTransition = $t->getName();
            $description = $t->getDescription() ? ("description: '" . $t->getDescription() . "'" . $EOL) : '';
            $event = $t->getEvent() ? ("event: '" . $t->getEvent() . "'" . $EOL) : '';
            $fDescription = $from->getDescription();
            $tDescription = $to->getDescription();
            $fExit = $from->getExitCommandName();
            $fEntry = $from->getEntryCommandName();
            $tExit = $to->getExitCommandName();
            $tEntry = $to->getEntryCommandName();

            // only write aliases if not done before
            if (!isset($aliases [$fromAlias])) {
                $uml .= 'state "' . $from . '" as ' . $fromAlias . PHP_EOL;
                $uml .= "$fromAlias: description: '" . $fDescription . "'" . PHP_EOL;
                $uml .= "$fromAlias: entry / '" . $fEntry . "'" . PHP_EOL;
                $uml .= "$fromAlias: exit / '" . $fExit . "'" . PHP_EOL;
                $aliases [$fromAlias] = $fromAlias;
            }

            // store order in which transitions will be handled
            if (!isset($order [$fromAlias])) {
                $order [$fromAlias] = 1;
            } else {
                $order [$fromAlias] = $order [$fromAlias] + 1;
            }

            // get 'to' alias
            if (!isset($aliases [$toAlias])) {
                $uml .= 'state "' . $to . '" as ' . $toAlias . PHP_EOL;
                $aliases [$toAlias] = $toAlias;
                $uml .= "$toAlias: description: '" . $tDescription . "'" . PHP_EOL;
                $uml .= "$toAlias: entry / '" . $tEntry . "'" . PHP_EOL;
                $uml .= "$toAlias: exit / '" . $tExit . "'" . PHP_EOL;
            }

            // write transition information
            $uml .= $fromAlias . ' --> ' . $toAlias;
            $uml .= " : <b><size:10>$nameTransition</size></b>" . $EOL;
            $uml .= $event;
            $uml .= "transition order from '$from': <b>" . $order [$fromAlias] . "</b>" . $EOL;
            $uml .= "rule/guard: '$rule'" . $EOL;
            $uml .= "command/action: '$command'" . $EOL;
            $uml .= $description;
            $uml .= PHP_EOL;

            // store possible end states aliases
            if ($t->getStateFrom()->isFinal()) {
                $endStates [$fromAlias] = $fromAlias;
            }
            if ($t->getStateTo()->isFinal()) {
                $endStates [$toAlias] = $toAlias;
            }
        }

        // only one begin state
        $initial = $machine->getInitialState();
        $initial = $initial->getName();
        $initialAlias = $this->plantUmlStateAlias($initial);
        if (!isset($aliases [$initialAlias])) {
            $uml .= 'state "' . $initial . '" as ' . $initialAlias . PHP_EOL;
        }
        $uml .= "[*] --> $initialAlias" . PHP_EOL;

        // note for initial alias with explanation
        $uml .= "note right of $initialAlias $NEWLINE";
        $uml .= "state diagram for machine '" . $machine->getContext()->getMachine() . "'$NEWLINE";
        $uml .= "created by izzum plantuml generator $NEWLINE";
        $uml .= "@link http://plantuml.sourceforge.net/state.html\"" . $NEWLINE;
        $uml .= "end note" . $NEWLINE;

        // add end states to diagram
        foreach ($endStates as $end) {
            $uml .= "$end --> [*]" . PHP_EOL;
        }

        // close plantuml
        $uml .= "@enduml" . PHP_EOL;
        return $uml;
    }
}
