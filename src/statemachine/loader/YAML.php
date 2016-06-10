<?php
namespace izzum\statemachine\loader;
use izzum\statemachine\loader\Loader;
use izzum\statemachine\StateMachine;
use izzum\statemachine\State;
use izzum\statemachine\Transition;
use izzum\statemachine\Exception;

/**
 * YAML loader. accepts a yaml string and loads a machine from it.
 * The yaml string can contain one or more machine definitions.
 * The correct machine will be found from the yaml structure.
 *
 * This class provides a way to load yaml from a file on your file system (fast access).
 *
 * This class needs the php yaml module to operate, which can be found via php.net
 *
 *
 * @link https://en.wikipedia.org/wiki/YAML
 * @link https://php.net/manual/en/intro.yaml.php
 * @link http://pecl.php.net/package/yaml the needed yaml library
 * @author Rolf Vreijdenberger
 *
 */
class YAML implements Loader {
    /**
     * an undecoded yaml string
     * @var string
     */
    private $yaml;

    /**
     *
     * @param string $yaml optional a valid yaml string as specified in assets/yaml/example.yaml
     */
    public function __construct($yaml)
    {
        $this->yaml = $yaml;
    }

    /**
     * creates an instance of this class with the data loaded from a file.
     * @param string $filename eg: __DIR__ . '/../../configuration.yaml'
     * @return YAML an instance of YAML with the data read from the file
     * @throws Exception
     */
    public static function createFromFile($filename)
    {
        if (!file_exists($filename)) {
            throw new Exception(sprintf('Failed to load yaml from file "%s". The file does not exist', $filename), Exception::BAD_LOADERDATA);
        }
        //suppres warning with @ operator. we are explicitely testing the return value.
        $yaml = @file_get_contents ($filename);
        if (false === $yaml) {
            throw new Exception(sprintf('Failed to read yaml data from file "%s". Unknown error (permissions?)', $filename), Exception::BAD_LOADERDATA);
        }
        return new static($yaml);
    }
    public function getYAML()
    {
        return $this->yaml;
    }


    /**
     * {@inheritDoc}
     */
    public function load(StateMachine $stateMachine)
    {
        //decode the json in a php object structure
        $decoded = \yaml_parse($this->getYaml(), false);

        //yaml decoding returns a php array.
        $name = $stateMachine->getContext()->getMachine();
        $found = false;
        $data = null;
        if(is_array(@$decoded['machines'])) {
            foreach ($decoded['machines'] as $data) {
                if ($data['name'] === $name) {
                    $found = true;
                    break;
                }
            }
        }
        if (!$found) {
            //no name match found
            throw new Exception(sprintf('no machine data found for "%s" in yaml. seems like a wrong configuration.', $name), Exception::BAD_LOADERDATA);
        }
        //accessing an array with an @ error suppresion operator ('shut the fuck up' operator),
        //allows you to get properties, even if they do not exist, without notices.
        //this lets us be a little lazy in mapping the array properties to the state and transition properties
        $states = array();
        foreach ($data['states'] as $state) {
            $tmp = new State($state['name'], $state['type'], @$state['entry_command'], @$state['exit_command'], @$state['entry_callable'], @$state['exit_callable']);
            $tmp->setDescription(@$state['description']);
            $states [$tmp->getName()] = $tmp;
        }

        $transitions = array();
        foreach ($data['transitions'] as $transition) {
            $tmp = new Transition($states [$transition['state_from']], $states [$transition['state_to']], @$transition['event'], @$transition['rule'], @$transition['command'], @$transition['guard_callable'], @$transition['transition_callable']);
            $tmp->setDescription(@$transition['description']);
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

