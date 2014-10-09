<?php
namespace izzum\statemachine\utils\uml;
use izzum\statemachine\StateMachine;
use izzum\statemachine\Transition;
use izzum\statemachine\State;
use izzum\statemachine\Exception;
/**
 * creates a uml statediagram in plantuml format for a statemachine.
 * 
 * This mainly serves as a simple demo.
 * 
 * More diagrams can be created from the data in a persistence layer.
 * for example:
 * - activity diagrams: which transitions has en entity actually made (and when)
 * - flow diagrams: a combination of the state diagram and the activity diagram
 *          (show the full state diagram and highlight the states the entity has
 *          actually gone through)
 * - heat maps: a count of every state for every entity.
 * 
 * @link http://www.plantuml.com/plantuml/ 
 */
class PlantUml {
    
    
     /**
     * create an alias for a state that has a valid plantuml syntax
     * @param string $original
     * @return string
     */
    private function plantUmlStateAlias($original) {
        $alias = ucfirst(implode("", array_map('ucfirst',explode("-", $original))));
        return $alias;
    }


    /**
     * get skins for layout
     * @return string
     * @link http://plantuml.sourceforge.net/skinparam.html
     * @link http://plantuml.com/classes.html#Skinparam
     */
    private function getPlantUmlSkins()
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

    /**
     * creates plantuml state output for a statemachine
     * @param string $machine
     * @return string plant uml code, this can be used to render an image
     * @link http://www.plantuml.com/plantuml/
     * @link http://plantuml.sourceforge.net/state.html
     * @throws Exception
     */
    public function createStateDiagram(StateMachine $machine)
    {
        $transitions = $machine->getTransitions();

        //all states are aliased so the plantuml parser can handle the names
        $aliases = array();
        $end_states = array();
        $EOL = "\\n\\" . PHP_EOL;/* for multiline stuff in plantuml */
        $NEWLINE = PHP_EOL;

        //start with declaration
        $uml = "@startuml" . PHP_EOL;

        //skins for colors etc.
        $uml .= $this->getPlantUmlSkins() . PHP_EOL;

        //only one begin state
        $initial = $machine->getInitialState();
        $initial = $initial->getName();
        $initial_alias = $this->plantUmlStateAlias($initial);
        $aliases[$initial_alias] = $initial_alias;
        $uml .=  'state "' . $initial . '" as ' . $initial_alias . PHP_EOL;
        $uml .= "[*] --> $initial_alias". PHP_EOL;

        //note
        $uml .= "note right of $initial_alias $NEWLINE" ;
        $uml .= "state diagram for machine '" . $machine->getMachine() . "'$NEWLINE";
        $uml .= "created by izzum plantuml generator $NEWLINE";
        $uml .= "@link http://plantuml.sourceforge.net/state.html\"" . $NEWLINE;
        $uml .= "end note" . $NEWLINE;

        //the order in which transitions are executed
        $order = array();
        
        //create the diagram by drawing all transitions
        foreach ($transitions as $t)
        {
            //get states and state aliases (plantuml cannot work with certain 
            //characters, so therefore we create an alias for the state name)
            $from = $t->getStateFrom()->getName();
            $from_alias = $this->plantUmlStateAlias($from);
            $to = $t->getStateTo();
            $to_alias = $this->plantUmlStateAlias($to->getName());

            //get some names to display
            $command = $t->getCommandName();
            $rule = $t->getRuleName();
            $name_transition = $t->getName();


            //only write aliases if not done before
            if(!isset($aliases[$from_alias])) {
                $uml .= 'state "' . $from . '" as '. $from_alias . PHP_EOL;
                $uml .= "$from_alias" . PHP_EOL;
                $aliases[$from_alias] = $from_alias;
                
            }
            
            //store order in which transitions will be handled
            if(!isset($order[$from_alias])){
                $order[$from_alias] = 1;
            }else {
                $order[$from_alias] = $order[$from_alias] + 1;
            }

            //get 'to' alias
            if(!isset($aliases[$to_alias])) {
                $uml .= 'state "' . $to . '" as '. $to_alias . PHP_EOL;
                $aliases[$to_alias] = $to_alias;
            }

            //write transition information
            $uml .= $from_alias .' --> '. $to_alias;
            $uml .= " : <b><size:10>$name_transition</size></b>" . $EOL;
            $uml .= "transition order from '$from': <b>" . $order[$from_alias] . "</b>" . $EOL;
            $uml .= "rule: $rule" . $EOL;
            $uml .= "command: $command" . $EOL;
            $uml .= PHP_EOL;

            //store possible end states aliases
            if($t->getStateFrom()->isFinal()) {
                $end_states[$from_alias] = $from_alias;
            }
            if($t->getStateTo()->isFinal()) {
                $end_states[$to_alias] = $to_alias;
            }

        }

        //add end states to diagram
        foreach ($end_states as $end) {
            $uml .= "$end --> [*]" . PHP_EOL;
        }

        //close plantuml
        $uml .= "@enduml" . PHP_EOL;
        return $uml;
    }
    

}
