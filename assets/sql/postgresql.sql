-- http://www.postgresql.org/
-- This file contains a fully normalized, indexed and functioning backend implementation 
-- for the izzum statemachine for a postgresql database

-- this database can be used with the izzum\statemachine\persistence\PDO adapter with
-- a correct dsn to connect to postgres.
-- http://php.net/manual/en/ref.pdo-pgsql.php


-- each table has extensive comments on what data they contain and what it means
-- and also on how to use them from the application code.


-- what should you do?
	-- define the machines in statemachine_machines.
	-- define the states for the machines in statemachine_states.
	-- define the transitions between states for the machines in statemachine_transitions
		-- this will also define the rules and commands for those transitions.
		-- read the definition for the statemachine via a join on statemachine_transitions and statemachine_states.
	-- add an entity to the persisten storage in statemachine_entities. 
		-- do this via application logic (see table comments).
		-- an entity_id is the unique id from your application specific domain model you wish to add stateful behaviour to.
		-- retrieve the current state for an entity in a machine.
		-- set the new current state for an entity in a machine.
	-- write history records for entities and their transitions in a machine in statemachine_history.
		-- do this via application logic (see table comments, you can use the entities or history table, or both).


DROP TABLE IF EXISTS statemachine_history;
DROP TABLE IF EXISTS statemachine_entities;
DROP TABLE IF EXISTS statemachine_transitions;
DROP TABLE IF EXISTS statemachine_states;
DROP TABLE IF EXISTS statemachine_machines;
DROP SEQUENCE IF EXISTS s_statemachine_history_id;




-- machines
CREATE TABLE statemachine_machines (
	machine varchar NOT NULL, -- the machine name, for your reference and for a reference in the application code. It is a natural key.
	description text, -- optional: a descriptive text
	factory text -- optional: the fully qualified name of the factory to be instantiated (if you want to be able to use this dynamically)
);

COMMENT ON TABLE statemachine_machines IS '
The different statemachines used are defined here. 
A human readable description is used for documentation purposes.
changes in the name of a machine will be cascaded through the other tables.
The factory column contains the fully qualified class path to an 
instance of the AbstractFactory for creating a statemachine';
CREATE UNIQUE INDEX u_statemachine_machines_machine ON statemachine_machines (machine);
ALTER TABLE statemachine_machines ADD PRIMARY KEY (machine);



-- states
CREATE TABLE statemachine_states (
	machine varchar NOT NULL, -- a foreign key to the machine name.
	state varchar NOT NULL, -- a state for the machine. use lowercase and hyphen seperated. eg: my-state.
							-- or use a regex specifier: <[not-]regex:/<regular-expresion-here>/>
	type varchar DEFAULT 'normal'::character varying NOT NULL, -- one of initial, normal, regex or final
	entry_command varchar NULL, -- the fully qualified name of a Command class to execute as part of entering this state 
	exit_command varchar NULL, -- the fully qualified name of a Command class to instantiate as part of exiting this state
	description text -- optional: a descriptive text
);
COMMENT ON TABLE statemachine_states IS 'Valid states for a specific machine type.
Each statemachine MUST have ONE state of type "initial".
This is used to create the initial state if an entity is not yet represented in this system.
The implicit assumption is that a statemachine always has (and can only have) ONE initial state,
which is the entry point. The default name for this state is "new".
As part of a transition a state can have an exit action and the new state an entry action.
These actions will execute a command. The order is: exit-command, transition-command, entry-command
All states must be lowercase and use hyphens instead of spaces eg: my-state unless it is a regex state,
then you need to use the regex format of [not-]regex:/<regular-expression-here>/.
changes in the name of a state will be cascaded through the other tables';
CREATE UNIQUE INDEX u_statemachine_states_m_s ON statemachine_states (machine, state);
ALTER TABLE statemachine_states ADD CHECK ((state)::text = lower((state)::text));
ALTER TABLE statemachine_states ADD CHECK ((type)::text = ANY ((ARRAY['normal'::character varying, 'final'::character varying, 'initial'::character varying, , 'regex'::character varying])::text[]));
ALTER TABLE statemachine_states ADD PRIMARY KEY (state, machine);
ALTER TABLE statemachine_states ADD FOREIGN KEY (machine) REFERENCES statemachine_machines (machine) ON DELETE NO ACTION ON UPDATE CASCADE;




--transitions
CREATE TABLE statemachine_transitions (
	machine varchar  NOT NULL, 
	state_from varchar  NOT NULL, -- the state this transition is from
	state_to varchar  NOT NULL, -- the state this transition is to
	event varchar NULL, --optional: can be used for giving 'event' input to the statemachine.
	rule varchar  DEFAULT '\izzum\rules\TrueRule'::character varying NOT NULL, -- the fully qualified name of a Rule class to instantiate
	command varchar  DEFAULT '\izzum\command\NullCommand'::character varying NOT NULL, -- the fully qualified name of a Command class to instantiate
	priority int4 DEFAULT 1 NOT NULL, -- optional: can be used if you want your rules to be tried in a certain order. make sure to ORDER in your retrieval query.
	description text -- optional: a descriptive text
);
COMMENT ON TABLE statemachine_transitions IS '
Define the transitions to be used per statemachine.
A rule is used to check a transition possibility (use a fully qualified classname). 
The default TrueRule rule always allows a transition. 
A command is used to execute the transition logic (use a fully qualified classname).
The default NullCommand command does nothing.
Priority is only relevant for the unique combination of {machine, state_from} and 
has context in the preferred order of checking rules for the transition from a state,
since this allows you to check a higher priority rule first, followed by transition with a 
TrueRule rule if the first rule does not apply. 
Priority can be used to order the transitions for the statemachine.
Event can be used to trigger the statemachine with an event name. event names do not have to be unique for 
transitions in a statemachine (see the difference in mealy vs moore statemachines).

All data for a statemachine can be retrieved via a join on this 
table and the statemachine_state table.
This should be done by an implementation of izzum\statemachine\loader\Loader.';
CREATE UNIQUE INDEX u_statemachine_transitions_m_sf_st ON statemachine_transitions (machine, state_from, state_to);
ALTER TABLE statemachine_transitions ADD PRIMARY KEY (machine, state_from, state_to);
ALTER TABLE statemachine_transitions ADD FOREIGN KEY (machine, state_from) REFERENCES statemachine_states (machine, state) ON DELETE NO ACTION ON UPDATE CASCADE;
ALTER TABLE statemachine_transitions ADD FOREIGN KEY (machine, state_to) REFERENCES statemachine_states (machine, state) ON DELETE NO ACTION ON UPDATE CASCADE;



-- entities. store current states coupled to an entity_id
-- this table actually contains denormalized data in case you also use the 
-- history table. But it might be worth it in terms of retrieval performance for
-- getting the current state.
CREATE TABLE statemachine_entities (
	machine varchar NOT NULL,
	entity_id varchar(255) NOT NULL, -- the unique id of your application specific domain model (eg: an Order)
	state varchar NOT NULL, -- the current state
	changetime timestamp(6) DEFAULT now() NOT NULL -- when the current state was set
);
COMMENT ON TABLE statemachine_entities IS '
This table contains the current states for specific entities in machines. 
This makes it easy to look up a current state for an entity in a machine.
there can be only ONE entry per {entity_id, machine} tuple.

The actual state is stored here. Transition information will be stored in the
statemachine_history table, where the latest record should equal the actual state.
The first entry in this table should default to the 
only state of the machine with type "initial" (for theoretical purposes)


The data that will be written to this table by a subclass 
of izzum\statemachine\persistence\Adapter specifically written for postgres. 
Entities should be explicitely added to the statemachine by application logic. 
This will be done in the method "$context->add($state)" which should write 
the first entry for this entity: it should be the only 'initial' state, the "new" state.

After a transition, the new state will be set in this table and will overwrite the current value.
This will be done in the overriden method "processSetState($identifier, $state)".

The current state should be read from this table via the overriden 
method "processGetState($identifier)".

All entity_ids for a machine in a specific state should be retrieved from this 
table via the method "getEntityIds($machine, $state)".';
CREATE INDEX i_statemachine_entities_entity_id ON statemachine_entities (entity_id);
ALTER TABLE statemachine_entities ADD PRIMARY KEY (machine, entity_id);
-- only add foreign keys if you use the database for both 
-- 1. the configuration of the statemachine and 
-- 2. for persistence of state data.
-- ALTER TABLE statemachine_entities ADD FOREIGN KEY (machine, state) REFERENCES statemachine_states (machine, state) ON DELETE NO ACTION ON UPDATE CASCADE;




-- history. for accounting purposes. optional
-- we could only use a history table to store all information about states and 
-- the current state (making the entities table optional), 
-- but this would make the retrieval of the current state
-- expensive, since it would mean we would have to get all the records for an 
-- entity and sort them to get the last/current one.
-- the performance penalty will be dependent on your backend implementation,
-- but just using the history table and not the entities table will make
-- application logic of the Adapter subclass easier.
CREATE SEQUENCE s_statemachine_history_id;
CREATE TABLE statemachine_history (
	id int4 DEFAULT nextval('s_statemachine_history_id'::regclass) NOT NULL, -- we use a surrogate key since we have no natural primary key
	machine varchar  NOT NULL,
	entity_id varchar NOT NULL,
	state varchar NOT NULL, -- the state to which the transition was (partially in case of error) done
	changetime timestamp(6) DEFAULT now() NOT NULL, -- when the transition was made
	message text, 	-- optional: this should only be set when there is an error thrown from the statemachine.
			-- the state will then reflect the actual current state for the machine; either
			-- the from state in case the transition was only partially succesful or 
			-- the to state in case the transition was only partial succesful but 
			-- got so far as to enter the next state. 
			-- This field will be filled,
			-- preferably with json, to store both exception code and message.
			-- application code will then be able to display this.
			-- If/when the state and the previous state are the same AND this field is empty,
			-- it will mean a succesfull self transition has been made.
	exception boolean DEFAULT FALSE -- if it is an exceptional transition or not. 
);
COMMENT ON TABLE statemachine_history IS '
Each transition made by a state machine should write a record in this table and 
this will provide the full history overview.

State contains the state the transition was made to.
It should be equal the state of the last added {machine,entity_id} tuple 
in the statemachine_entities table.

Changetime contains the timestamp of the transition.

The message column is used to store information about transition failures. 

A transition failure will occur when there is an exception during the transition phase, 
possibly thrown or generated from a command. 
This should result in one or multiple records in this table with 
the exception field set and a message (the same state will be there multiple times:
one for entering the state, one or more for staying in 
that state because of a failed transitions)

This is different from a self transition, since the message field will be 
filled with exception data and the exception field will be used.

The message column could store json so we can use the exception code 
and message in this field.

Entities should be explicitely added to the statemachine by application logic. 
This will be done in a subclass of izzum\statemachine\persistence\Adapter. 
The logic will be implemented in the method "$context->add($state)" for the first entry,
and in the method "processetState($identifier, $state)" for all subsequent entries.';
CREATE INDEX i_statemachine_history_entity_id ON statemachine_history (entity_id);
ALTER TABLE statemachine_history ADD PRIMARY KEY (id);
-- only add foreign keys if you use the database for both 
-- 1. the configuration of the statemachine and 
-- 2. for persistence of state data.
-- ALTER TABLE statemachine_history ADD FOREIGN KEY (machine, state) REFERENCES statemachine_states (machine, state) ON DELETE NO ACTION ON UPDATE CASCADE;


---------------------------------------------
---------------------------------------------
------------- TEST DATA ---------------------
---------------------------------------------
---------------------------------------------
-- create an izzum machine
INSERT INTO statemachine_machines
(machine, factory, description)
VALUES 
('izzum', '\izzum\statemachine\factory\PostgresExampleFactory', 'this izzum: an example statemachine');

-- insert states into the izzum machine
INSERT INTO statemachine_states
(machine, state, type, description)
VALUES
('izzum', 'new', 'initial', 'the only initial state'),
('izzum', 'done', 'final', 'a final state, on of more final states possible'),
('izzum', 'ok', 'normal', 'ok: a normal state'),
('izzum', 'fine', 'normal', 'fine: a normal state'),
('izzum', 'excellent', 'normal', 'excellent: a normal state'),
('izzum', 'bad', 'normal', 'bad: do not go here, ');

-- create the transitions for the izzum machine.
-- true/false rules in combination with priorities make it so that only
-- a named transition by using StateMachine::apply('new_to_bad') can be made 
-- to follow the 'bad' path. In all other cases, there is a false rule. 
-- even though those false rules will be tried when we use StateMachine::run(),
-- they will not trigger a transition and run() will then follow the path trough
-- the happy transitions with the true rule.
-- the bad to done path will throw an exception on the rule
INSERT INTO statemachine_transitions
(machine, state_from, state_to, rule, command, priority, description)
VALUES
('izzum', 'new', 'ok','\izzum\rules\TrueRule', 'izzum\command\NullCommand', 1, 'new_to_ok transition'),
('izzum', 'ok', 'fine','\izzum\rules\TrueRule', 'izzum\command\NullCommand', 2, 'ok_to_fine transition'),
('izzum', 'fine', 'excellent','\izzum\rules\TrueRule', 'izzum\command\NullCommand', 2, 'fine_to_excellent transition'),
('izzum', 'excellent', 'done','\izzum\rules\TrueRule', 'izzum\command\NullCommand', 2, 'excellent_to_done transition'),
('izzum', 'new', 'bad','\izzum\rules\TrueRule', 'izzum\command\NullCommand', 2, 'new_to_bad transition'),
('izzum', 'ok', 'bad','\izzum\rules\FalseRule', 'izzum\command\NullCommand', 1, 'ok_to_bad transition'),
('izzum', 'fine', 'bad','\izzum\rules\FalseRule', 'izzum\command\NullCommand', 1, 'fine_to_bad transition'),
('izzum', 'excellent', 'bad','\izzum\rules\FalseRule', 'izzum\command\NullCommand', 1, 'excellent_to_bad transition'),
('izzum', 'bad', 'done','\izzum\rules\ExceptionRule', 'izzum\command\NullCommand', 1, 'bad_to_done transition');

INSERT INTO statemachine_entities
(machine, entity_id, state)
VALUES
('izzum','1', 'new'),('izzum','2', 'done'),
('izzum','3', 'excellent'),('izzum','4', 'new'),
('izzum','5', 'new'),('izzum','6', 'new'),
('izzum','7', 'ok'),('izzum','8', 'new'),
('izzum','9', 'bad'),('izzum','10', 'fine'),
('izzum','11', 'done'),('izzum','12', 'ok'),
('izzum','13', 'bad'),('izzum','14', 'ok');
