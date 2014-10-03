<?php
namespace izzum\statemachine\loader;
use izzum\statemachine\StateMachine;
use izzum\statemachine\Exception;
use izzum\statemachine\State;
use izzum\statemachine\utils\Utils;
use izzum\statemachine\Transition;
/**
 * LoaderArray supports configuration of a StateMachine by loading it from
 * an array of LoaderObjects.
 * 
 * This should always be the object that is used by other Loader implementations.
 * This means other LoaderInterface implementations should delegate the loading
 * to this class. This means you should implement the Loader interface and 
 * use object composition for the LoaderArray. In other words: your custom loader
 * should act as a decorator for LoaderArray.
 * 
 * @see LoaderData
 * @author Rolf Vreijdenberger
 *
 */
class LoaderArray implements Loader {
    /**
     * 
     * @var LoaderData[]
     */
    protected $loaderdata;
    
    /**
     * 
     * @param LoaderData[] $objects
     */
    public function __construct($loaderdata = array()){
        //either an empty array, or one configured with the right objects
        foreach($loaderdata as $data)
        {
            if(!is_a($data, 'izzum\statemachine\loader\LoaderData')){
                throw new Exception('Expected LoaderData, found something else' . 
                        get_class($data),
                        Exception::BAD_LOADERDATA);
            }
        }
        
        $this->loaderdata = $loaderdata;
    }
    

    public function load(StateMachine $stateMachine)
    {
        $transitions = $this->getTransitions();
        //add the transitions. the transitions added will set the
        //states (from/to) on the statemachine
        foreach($transitions as $transition)
        {
            $stateMachine->addTransition($transition);
        }
    }
    
    /**
     * This method will return the transitions created by the input of LoaderData[]
     * It will create the correct Transtion and State instances with the correct
     * references to each other.
     * @return Transition[]
     */
    public function getTransitions()
    {
        $states = array();
        $transitions = array();
        $output = array();
        foreach ($this->loaderdata as $data)
        {
            //origin states
            $name_from = $data->getStateFrom();
            if(!isset($states[$name_from])) {
                $states[$name_from] = new State($name_from, $data->getStateTypeFrom());
            }
            $state_from = $states[$name_from];
            
            //destination states
            $name_to = $data->getStateTo();
            if(!isset($states[$name_to])) {
                $states[$name_to] = new State($name_to, $data->getStateTypeTo());
            } 
            $state_to = $states[$name_to];
            
            //transitions
            $transition_name = Utils::getTransitionName($name_from, $name_to);
            if(!isset($transitions[$transition_name])) {
                $transitions[$transition_name] = new Transition($state_from, $state_to, $data->getRule(), $data->getCommand());
                //the order in which transitions are created actually does matter.
                //it matters insofar that when a statemachine::run() is called,
                //the first transition in a state will be tried first.
                $output[] = $transitions[$transition_name];
            }
        }
        return $output;
    }
    
    /**
     * counts the number of contained loader objects.
     * @return int
     */
    public function count() {
        return (int) count($this->loaderdata);
    }
}