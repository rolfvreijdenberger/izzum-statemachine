-- https://sqlite.org/
-- sql for the creation of sqlite tables for storing statemachine data.

-- for full explanation and comments, see the postgresql.sql file

-- this database can be used with the izzum\statemachine\persistence\PDO adapter with
-- a correct dsn to connect to sqlite ("sqlite:path/to/sqlite.db")
-- http://php.net/manual/en/ref.pdo-sqlite.php

DROP TABLE IF EXISTS statemachine_history;
DROP TABLE IF EXISTS statemachine_entities;
DROP TABLE IF EXISTS statemachine_transitions;
DROP TABLE IF EXISTS statemachine_states;
DROP TABLE IF EXISTS statemachine_machines;



-- machines
CREATE TABLE statemachine_machines (
	machine VARCHAR NOT NULL PRIMARY KEY, 
        description text,
	factory text 
);

-- states
CREATE TABLE statemachine_states (
	machine VARCHAR NOT NULL, 
	state VARCHAR NOT NULL, 
	type VARCHAR DEFAULT 'normal' NOT NULL CHECK(type IN ('final','initial','normal')), 
	entry_command VARCHAR(255) NULL,
	exit_command VARCHAR(255) NULL,
	description text,
        PRIMARY KEY (machine, state),
        FOREIGN KEY (machine) REFERENCES statemachine_machines(machine) ON UPDATE CASCADE
);

--transitions
CREATE TABLE statemachine_transitions (
	machine VARCHAR  NOT NULL, 
	state_from VARCHAR  NOT NULL,
	state_to VARCHAR  NOT NULL, 
	rule VARCHAR  DEFAULT '\izzum\rules\True' NOT NULL,
	command VARCHAR  DEFAULT '\izzum\command\Null' NOT NULL,
	priority int4 DEFAULT 1 NOT NULL, 
	description text,
        PRIMARY KEY (machine, state_from, state_to),
        FOREIGN KEY (machine, state_from) REFERENCES statemachine_states(machine, state) ON UPDATE CASCADE,
        FOREIGN KEY (machine, state_to) REFERENCES statemachine_states(machine, state) ON UPDATE CASCADE
);

--entities
CREATE TABLE statemachine_entities (
	machine VARCHAR NOT NULL,
	entity_id VARCHAR NOT NULL, 
	state VARCHAR NOT NULL, 
	changetime TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY (machine, entity_id),
        FOREIGN KEY (machine, state) REFERENCES statemachine_states(machine, state)
);
CREATE INDEX i_statemachine_entities_entity_id ON statemachine_entities (entity_id);

--history
CREATE SEQUENCE s_statemachine_history_id;
CREATE TABLE statemachine_history (
	id INTEGER PRIMARY KEY, 
	machine VARCHAR  NOT NULL,
	entity_id VARCHAR NOT NULL,
	state VARCHAR NOT NULL, 
	changetime TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
	message text,
	exception INT NOT NULL DEFAULT 0 	
);
CREATE INDEX i_statemachine_history_entity_id ON statemachine_history (entity_id);


---------------------------------------------
---------------------------------------------
------------- TEST DATA ---------------------
---------------------------------------------
---------------------------------------------
-- create an izzum machine
INSERT INTO statemachine_machines
(machine, factory, description)
VALUES 
('izzum', '\izzum\statemachine\factory\SqliteExampleFactory', 'this izzum: an example statemachine');

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

INSERT INTO statemachine_transitions
(machine, state_from, state_to, rule, command, priority, description)
VALUES
('izzum', 'new', 'ok','\izzum\rules\True', 'izzum\command\Null', 1, 'new_to_ok transition'),
('izzum', 'ok', 'fine','\izzum\rules\True', 'izzum\command\Null', 2, 'ok_to_fine transition'),
('izzum', 'fine', 'excellent','\izzum\rules\True', 'izzum\command\Null', 2, 'fine_to_excellent transition'),
('izzum', 'excellent', 'done','\izzum\rules\True', 'izzum\command\Null', 2, 'excellent_to_done transition'),
('izzum', 'new', 'bad','\izzum\rules\True', 'izzum\command\Null', 2, 'new_to_bad transition'),
('izzum', 'ok', 'bad','\izzum\rules\False', 'izzum\command\Null', 1, 'ok_to_bad transition'),
('izzum', 'fine', 'bad','\izzum\rules\False', 'izzum\command\Null', 1, 'fine_to_bad transition'),
('izzum', 'excellent', 'bad','\izzum\rules\False', 'izzum\command\Null', 1, 'excellent_to_bad transition'),
('izzum', 'bad', 'done','\izzum\rules\ExceptionRule', 'izzum\command\Null', 1, 'bad_to_done transition');

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
