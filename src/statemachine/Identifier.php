<?php
namespace izzum\statemachine;
/**
 * an instance of Identifier uniquely identifies the statemachine to be used.
 * 
 * A statemachine is always uniquely identified by the combination of an entity id and a machine name (that
 * provides the context of the statemachine the entity is governed by).
 * 
 * This object thus stores the minimum data needed from other processes in your application domain to
 * succesfully work with the statemachine.
 * 
 * 
 * @author Rolf Vreijdenberger
 *
 */
class Identifier {
	
	const NULL_ENTITY_ID = "-1";
	const NULL_STATEMACHINE = 'null-machine';
	
	/**
	 * an entity id that represents the unique identifier for an application
	 * domain specific object (entity) like 'Order', 'Customer' etc.
	 * @var string
	 */
	protected $entity_id;
	
	/**
	 * the statemachine that governs the state behaviour for this entity (eg 'order').
	 * this is the name of the statemachine itself and is used in conjunction
	 * with the entity_id to define what a statemachine is about.
	 * @var string
	 */
	protected $machine_name;
	
	/**
	 * Constructor
	 * @param mixed $entity_id the id of the domain specific entity (it will internally be converted to a string)
	 * @param string $machine the name of the statemachine (eg: 'order')
	 */
	public function __construct($entity_id, $machine_name)
	{
		//convert $entity_id to string (it will likely be an int but a string gives more flexibility)
		$this->entity_id            = trim("$entity_id");
		$this->machine_name         = $machine_name;
	}
	
	
	/**
	 * gets the statemachine name that handles the entity
	 * @return string
	 */
	public function getMachine()
	{
		return $this->machine_name;
	}
	
	/**
	 * gets the entity id that represents the unique identifier for the
	 * application domain specific model.
	 *
	 * @return string
	 */
	public function getEntityId()
	{
		return $this->entity_id;
	}
	
	
	
	/**
	 * get the unique identifier representation for an Identifier, which consists of the machine
	 * name and the entity_id in parseable form.
	 *
	 * @param boolean $readable human readable or not. defaults to false
	 * @return string
	 */
	public function getId($readable = false)
	{
		$output = '';
		if($readable) {
			$output = "machine: '" . $this->getMachine() . "', id: '". $this->getEntityId()  . "'";
		} else {
			$output = $this->getMachine() . "_" . $this->getEntityId() ;
		}
		return $output;
	}
	
	/**
	 * @return string
	 */
	public function toString()
	{
		return get_class($this) . ' ' . $this->getId(true);
	}
	
	/**
	 * @return string
	 */
	public function __toString()
	{
		return $this->toString();
	}
	
	
}
