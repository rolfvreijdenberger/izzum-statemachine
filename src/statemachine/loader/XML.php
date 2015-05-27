<?php
namespace izzum\statemachine\loader;
use izzum\statemachine\loader\Loader;
use izzum\statemachine\StateMachine;
use izzum\statemachine\State;
use izzum\statemachine\Transition;
use izzum\statemachine\Exception;

/**
 * XML loader. accepts an xml string and loads a machine from it.
 * This class also provides a way to load xml from a file.
 * The format of the data to be loaded is specified via an xml schema definition. see getXSD
 * 
 * @link https://en.wikipedia.org/wiki/XML
 * @author rolf
 *
 */
class XML implements Loader {
    /**
     * an xml string
     * @var string
     */
    private $xml;

    /**
     * 
     * @param string $xml optional a valid xml string according to the schema
     */
    public function __construct($xml)
    {
        $this->xml = $xml;
    }

    /**
     * creates an instance of this class with the data loaded from a file.
     * @param string $filename
     * @throws Exception
     * @return XML an instance of XML with the data from the file
     */
    public static function createFromFile($filename)
    {
        if (!file_exists($filename)) {
            throw new Exception(sprintf('Failed to load xml from file %s. The file does not exist', $filename), Exception::BAD_LOADERDATA);
        }
        //suppress warning with @ operator, since we explicitely check the return value
        $xml = @file_get_contents($filename);
        if (false === $xml) {
            throw new Exception(sprintf('Failed to read xml data from file %s. Unknown error (permissions?)', $filename), Exception::BAD_LOADERDATA);
        }
        return new static($xml);
    }

    public function getXML()
    {
        return $this->xml;
    }

    /**
     * gets the xsd used for the datastructure of the statemachine definitions
     * @return string
     */
    public function getXSD()
    {
        $schema = '<?xml version="1.0" encoding="UTF-8"?><xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema" attributeFormDefault="unqualified" elementFormDefault="qualified"><xs:element name="machines"><xs:complexType><xs:sequence><xs:element type="xs:string" name="comment" minOccurs="0" /><xs:element name="machine" maxOccurs="unbounded" minOccurs="0"><xs:complexType><xs:sequence><xs:element name="name"><xs:annotation><xs:documentation>The machine name</xs:documentation></xs:annotation><xs:simpleType><xs:restriction base="xs:token"><xs:pattern value="([a-z0-9])+((-)?([a-z0-9])+)*" /></xs:restriction></xs:simpleType></xs:element><xs:element type="xs:string" name="factory" minOccurs="0" /><xs:element type="xs:string" name="description" /><xs:element name="states"><xs:complexType><xs:sequence><xs:element name="state" maxOccurs="unbounded" minOccurs="2"><xs:complexType><xs:sequence><xs:element name="name"><xs:simpleType><xs:restriction base="xs:token"><xs:pattern value="([a-z0-9])+((-)?([a-z0-9])+)|(not-)?regex:(.*)*" /></xs:restriction></xs:simpleType></xs:element><xs:element name="type"><xs:simpleType><xs:restriction base="xs:string"><xs:enumeration value="initial" /><xs:enumeration value="normal" /><xs:enumeration value="final" /><xs:enumeration value="regex" /></xs:restriction></xs:simpleType></xs:element><xs:element type="xs:string" name="entry_command" minOccurs="0" /><xs:element type="xs:string" name="exit_command" minOccurs="0" /><xs:element type="xs:string" name="entry_callable" minOccurs="0" /><xs:element type="xs:string" name="exit_callable" minOccurs="0" /><xs:element type="xs:string" name="description" minOccurs="0" /></xs:sequence></xs:complexType></xs:element></xs:sequence></xs:complexType></xs:element><xs:element name="transitions"><xs:complexType><xs:sequence><xs:element name="transitition" maxOccurs="unbounded" minOccurs="2"><xs:complexType><xs:sequence><xs:element name="state_from"><xs:simpleType><xs:restriction base="xs:token"><xs:pattern value="([a-z0-9])+((-)?([a-z0-9])+)*|(not-)?regex:(.*)" /></xs:restriction></xs:simpleType></xs:element><xs:element name="state_to"><xs:simpleType><xs:restriction base="xs:token"><xs:pattern value="([a-z0-9])+((-)?([a-z0-9])+)*|(not-)?regex:(.*)" /></xs:restriction></xs:simpleType></xs:element><xs:element name="event"><xs:simpleType><xs:restriction base="xs:token"><xs:pattern value="[a-zA-Z0-9]+" /></xs:restriction></xs:simpleType></xs:element><xs:element type="xs:string" name="rule" minOccurs="0" /><xs:element type="xs:string" name="command" minOccurs="0" /><xs:element type="xs:string" name="guard_callable" minOccurs="0" /><xs:element type="xs:string" name="transition_callable" minOccurs="0" /><xs:element type="xs:string" name="description" minOccurs="0" /></xs:sequence></xs:complexType></xs:element></xs:sequence></xs:complexType></xs:element></xs:sequence></xs:complexType></xs:element></xs:sequence></xs:complexType></xs:element></xs:schema>';
        return $schema;
    }

    public function load(StateMachine $stateMachine)
    {
        //load the xml in a php object structure. suppres warning with @ operator since we explicitely check the return value
        $xml = @simplexml_load_string($this->getXML());
        if ($xml === false) {
            //could not load
            throw new Exception(sprintf('could not load xml data. check the xml format'), Exception::BAD_LOADERDATA);
        }
        $name = $stateMachine->getContext()->getMachine();
        $data = null;
        foreach ($xml->machine as $data) {
            if ($data->name === $name) {
                break;
            }
        }
        if (!$data) {
            //no name match found
            throw new Exception(sprintf('no machine data found for %s in xml. seems like a wrong configuration.', $name), Exception::BAD_LOADERDATA);
        }
        //accessing xml as an object allows you to get properties, even if they do not exist, without notices.
        $states = array();
        foreach ($data->states->state as $state) {
            $tmp = new State((string) $state->name, (string) $state->type, (string) $state->entry_command, (string) $state->exit_command, (string) $state->entry_callable, (string) $state->exit_callable);
            $tmp->setDescription((string) $state->description);
            $states [$tmp->getName()] = $tmp;
        }
        
        $transitions = array();
        foreach ($data->transitions->transition as $transition) {
            $tmp = new Transition($states [(string) $transition->state_from], $states [(string) $transition->state_to], (string) $transition->event, (string) $transition->rule, (string) $transition->command, (string) $transition->guard_callable, (string) $transition->transition_callable);
            $tmp->setDescription((string) $transition->description);
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