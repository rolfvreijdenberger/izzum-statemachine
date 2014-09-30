<?php
namespace izzum\statemachine\utils\uml;
use izzum\statemachine\StateMachine;
use izzum\statemachine\Exception;
class PlantUml {
    
    
     /**
     * create an alias for a state that has a valid plantuml syntax
     * @param string $original
     * @return string
     */
    private static function plantUmlStateAlias($original) {
        $alias = ucfirst(implode("", array_map('ucfirst',explode("-", $original))));
        return $alias;
    }

    /**
     * fix for shorter path output of rules and commands
     * @param string $path
     * @return string
     */
    private function plantUmlPathFix($path)
    {
        $path = str_replace("izzum", "", $path);
        $path = str_replace("\\rules\\", "\\R\\", $path);
        $path = str_replace("\\command\\", "\\C\\", $path);
        $path = str_replace("\\\\", "\\", $path);
        return $path;
    }

    private static function getPlantUmlTimestampFormatted($timestamp)
    {
        if(is_null($timestamp)) {
            $output = null;
        } else {
            $output = strtotime($timestamp);
            $output =date('Y-m-d H:i',$output);
        }
        return $output;
    }


    /**
     * get skins for layout
     * @return string
     * @link http://plantuml.sourceforge.net/skinparam.html
     */
    private static function getPlantUmlSkins()
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
     * creates plantuml output for a statemachine
     * @param string $machine
     * @param boolean $with_orphaned_states also show orphaned states (no incoming/outgoing)
     * @param string $placement placement of main note values: 'right' | 'left'
     * @return string plant uml code
     * @link http://plantuml.sourceforge.net/state.html
     * @throws Exception
     */
    public static function createStateDiagram(StateMachine $machine, $direction = 'right')
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
        $uml .= self::getPlantUmlSkins() . PHP_EOL;

        //only one begin state
        $initial = self::getInitialState($machine);
        $initial_alias = self::plantUmlStateAlias($initial);
        $uml .=  'state "' . $initial . '" as ' . $initial_alias . PHP_EOL;
        $uml .= "[*] --> $initial_alias". PHP_EOL;

        //note
        $uml .= "note $direction of New $NEWLINE" ;
        $uml .= "state diagram for machine '" . $machine->getMachine() . "'$NEWLINE";
        $uml .= "created by izzum uml generator $NEWLINE";
        $uml .= "@link http://plantuml.sourceforge.net/state.html\"" . $NEWLINE;
        $uml .= "end note" . $NEWLINE;


        //create the diagram
        foreach ($transitions as $t)
        {
            //get states and aliases
            $from = $t->getName();
            $from_alias = self::plantUmlStateAlias($from);
            $to = $t->getStateTo();
            $to_alias = self::plantUmlStateAlias($to->getName());

            
            $commands = self::plantUmlPathFix($t->getCommandName());
            $rules = self::plantUmlPathFix($t->getRuleName());
            $name_transition = $t->getName();


            //only write aliases if not done before
            if($from_alias != "" && !isset($aliases[$from_alias])) {
                //$description = $t['description_from'] ? "" . $t['description_from'] : "";
                $uml .= 'state "' . $from . '" as '. $from_alias . PHP_EOL;
                //$uml .= "$from_alias : $description" . PHP_EOL;
                $uml .= "$from_alias" . PHP_EOL;
                $aliases[$from_alias] = $from_alias;
            }

            //a state can be orphaned, in that case $to_alias is undefined
            if($to_alias != "" && !isset($aliases[$to_alias])) {
                //$description = $t['description_to'] ? "" . $t['description_to'] : "";
                $uml .= 'state "' . $to . '" as '. $to_alias . PHP_EOL;
                //$uml .= "$to_alias : $description" . PHP_EOL;
                $uml .= "$to_alias" . PHP_EOL;
                $aliases[$to_alias] = $to_alias;
            }

            //only write transitions if defined (when a state is not orphaned)
            //if($name_transition != "" && !is_null($name_transition)) {
                //write transition
                $uml .= $from_alias .' --> '. $to_alias;

             //   $uml .= " :prio: $priority" . $EOL;
                $uml .= "rule: $rules" . $EOL;
                $uml .= "cmd: $commands" . $EOL;
                //$description = $t['description'];
                //$uml .= "$description";
            //}
            $uml .= PHP_EOL;

            //store possible end states aliases
            if($t->getStateFrom()->isFinal()) {
                $end_states[$from_alias] = $from_alias;
            }
            if($t->getStateTo()->isFinal()) {
                $end_states[$to_alias] = $to_alias;
            }

        }

        //add end states
        foreach ($end_states as $end) {
            $uml .= "$end --> [*]" . PHP_EOL;
        }

        //close plantuml
        $uml .= "@enduml" . PHP_EOL;
        return $uml;
    }
    
    private static function getInitialState(StateMachine $machine) {
        $transitions = $machine->getTransitions();
        foreach($transitions as $transition) {
            if($transition->getStateFrom()->isInitial())
            {
                return $transition->getStateFrom();
            }
        }
        throw new Exception('no initial state found');
    }

}
