<?php
namespace izzum\statemachine\loader;
use izzum\statemachine\loader\Loader;
use izzum\statemachine\StateMachine;
use izzum\statemachine\State;
use izzum\statemachine\Transition;
use izzum\statemachine\Exception;

/**
 * JSON loader. accepts a json string and loads a machine from it.
 * The json string can contain one or more machine definitions. 
 * The correct machine will be found from the json structure.
 * 
 * This class provides a way to load json from a file on your file system (fast access)
 * and is also used by the Redis adapter to load a json string from a redis server.
 * 
 * The format of the data to be loaded is specified via a json-schema. 
 * The schema can be retrieved via JSON::getJSONSchema() and the schema itself and 
 * a full example of the data can be found in 'assets/json'
 * 
 * @link https://en.wikipedia.org/wiki/JSON
 * @Link http://json-schema.org
 * @link http://jsonschemalint.com/draft4/ for validating according to a json-schema (useful for building your own)
 * @link https://php.net/manual/en/function.json-decode.php
 * @link https://php.net/manual/en/function.file-get-contents.php
 * @author Rolf Vreijdenberger
 *
 */
class JSON implements Loader {
    /**
     * an undecoded json string
     * @var string
     */
    private $json;

    /**
     * 
     * @param string $json optional a valid json string according to the schema
     */
    public function __construct($json)
    {
        $this->json = $json;
    }

    /**
     * creates an instance of this class with the data loaded from a file.
     * @param string $filename eg: __DIR__ . '/../../configuration.json'
     * @return JSON an instance of JSON with the data read from the file
     * @throws Exception
     */
    public static function createFromFile($filename)
    {
        if (!file_exists($filename)) {
            throw new Exception(sprintf('Failed to load json from file %s. The file does not exist', $filename), Exception::BAD_LOADERDATA);
        }
        //suppres warning with @ operator. we are explicitely testing the return value.
        $json = @file_get_contents($filename);
        if (false === $json) {
            throw new Exception(sprintf('Failed to read json data from file %s. Unknown error (permissions?)', $filename), Exception::BAD_LOADERDATA);
        }
        return new static($json);
    }
    public function getJSON()
    {
        return $this->json;
    }

    /**
     * gets the json schema used for the datastructure of the statemachine definitions
     * @return string
     */
    public function getJSONSchema()
    {
        $schema = '{"$schema":"http://json-schema.org/draft-04/schema#","type":"object","title":"izzum statemachines definitions schema","description":"This is a json-schema for the statemachines as defined by the php izzum library. see http://jsonschemalint.com/draft4/# to validate your json definitions","required":["machines"],"properties":{"comment":{"type":["string","null"],"description":"comments for the description of the file contents can be placed here."},"machines":{"minItems":1,"uniqueItems":true,"type":"array","description":"All machines are defined here","items":{"required":["name","description","states","transitions"],"type":"object","description":"A full machine definition","properties":{"name":{"type":"string","description":"the name of the machine","pattern":"^([a-z0-9])+((-)?([a-z0-9])+)*$"},"factory":{"type":["string","null"],"description":"\\fully\\qualified\\Factory class name"},"description":{"type":["string","null"],"description":"a description of the machine"},"states":{"type":"array","description":"All state definitions for a machine go in this array","minItems":2,"uniqueItems":true,"items":{"type":"object","description":"A state definition","required":["name","type"],"properties":{"name":{"type":"string","description":"the state name","pattern":"^([a-z0-9])+((-)?([a-z0-9])+)*$|^(not-)?regex:(.*)$"},"type":{"enum":["initial","normal","final","regex"],"description":"the type of state: initial (1), normal(0..n), final (1..n) or regex (0..n)"},"entry_command":{"type":["string","null"],"description":"\\fully\\qualified\\Command (multiple can be comma seperated) that will be executed on entry of the state"},"exit_command":{"type":["string","null"],"description":"\\fully\\qualified\\Command name (multiple can be comma seperated) that will be executed on exit of the state"},"entry_callable":{"type":["string","null"],"description":"A php callable for state entry. can only be in form of fully\\qualified\\Class::staticMethod"},"exit_callable":{"type":["string","null"],"description":"A php callable for state exit. can only be in form of fully\\qualified\\Class::staticMethod"},"description":{"type":["string","null"],"description":"A description of the state"}}}},"transitions":{"type":"array","description":"A list of transitions, referring to the states","minItems":1,"uniqueItems":true,"items":{"type":"object","description":"A transition definition","required":["state_from","state_to","event"],"properties":{"state_from":{"type":"string","description":"the state from which the transition is made. this can be a regex.","pattern":"^([a-z0-9])+((-)?([a-z0-9])+)*$|^(not-)?regex:(.*)$"},"state_to":{"type":"string","description":"the state to which the transition is made. this can be a regex.","pattern":"^([a-z0-9])+((-)?([a-z0-9])+)*$|^(not-)?regex:(.*)$"},"event":{"type":["string","null"],"description":"an event name by which you can call this transition","pattern":"^[a-zA-Z0-9]+$"},"rule":{"type":["string","null"],"description":"\\fully\\qualified\\Rule name (multiple can be comma seperated) that will be checked as boolean guard logic before the transition"},"command":{"type":["string","null"],"description":"\\fully\\qualified\\Command name (multiple can be comma seperated) that will be executed as the transition logic"},"guard_callable":{"type":["string","null"],"description":"A php callable for guard logic. can only be in form of fully\\qualified\\Class::staticMethod"},"transition_callable":{"type":["string","null"],"description":"A php callable for transition logic. can only be in form of fully\\qualified\\Class::staticMethod"},"description":{"type":["string","null"],"description":"The description of the transition"}}}}}}}}}';
        return $schema;
    }

    /**
     * {@inheritDoc}
     */
    public function load(StateMachine $stateMachine)
    {
        //decode the json in a php object structure
        $decoded = json_decode($this->getJSON(), false);
        if (!$decoded) {
            //could not decode (make sure that fully qualified names are escaped with 
            //2 backslashes: \\izzum\\commands\\NullCommand and that only double quotes are used.
            throw new Exception(sprintf('could not decode json data. did you only use double quotes? check the json format against %s', 'http://jsonlint.com/'), Exception::BAD_LOADERDATA);
        }
        $name = $stateMachine->getContext()->getMachine();
        $found = false;
        $data = null;
        if(is_array(@$decoded->machines)) {
            foreach ($decoded->machines as $data) {
                if ($data->name === $name) {
                    $found = true;
                    break;
                }
            }
        }
        if (!$found) {
            //no name match found
            throw new Exception(sprintf('no machine data found for %s in json. seems like a wrong configuration.', $name), Exception::BAD_LOADERDATA);
        }
        //accessing json as an object with an @ error suppresion operator ('shut the fuck up' operator),
        //allows you to get properties, even if they do not exist, without notices.
        //this lets us be a little lazy in mapping the json properties to the state and transition properties
        $states = array();
        foreach ($data->states as $state) {
            $tmp = new State($state->name, $state->type, @$state->entry_command, @$state->exit_command, @$state->entry_callable, @$state->exit_callable);
            $tmp->setDescription(@$state->description);
            $states [$tmp->getName()] = $tmp;
        }
        
        $transitions = array();
        foreach ($data->transitions as $transition) {
            $tmp = new Transition($states [$transition->state_from], $states [$transition->state_to], @$transition->event, @$transition->rule, @$transition->command, @$transition->guard_callable, @$transition->transition_callable);
            $tmp->setDescription(@$transition->description);
            $transitions [] = $tmp;
        }
        
        //delegate to loader
        $loader = new LoaderArray($transitions);
        return $loader->load($stateMachine);
    }

    public function toString()
    {
        return get_class($this);
    }
    
    public function __toString()
    {
        return $this->toString();
    }
}

