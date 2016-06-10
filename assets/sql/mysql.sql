-- https://www.mysql.com/
-- sql for the creation of mysql tables for storing statemachine data.

-- for full explanation and comments, see the postgresql.sql file

-- this database can be used with the izzum\statemachine\persistence\PDO adapter with
-- a correct dsn(data source name) to connect to mysql ("mysql:host=localhost;dbname=test")
-- http://php.net/manual/en/ref.pdo-mysql.connection.php

DROP TABLE IF EXISTS statemachine_history;
DROP TABLE IF EXISTS statemachine_entities;
DROP TABLE IF EXISTS statemachine_transitions;
DROP TABLE IF EXISTS statemachine_states;
DROP TABLE IF EXISTS statemachine_machines;



-- machines
CREATE TABLE statemachine_machines (
	machine VARCHAR(255) NOT NULL PRIMARY KEY, 
        description text,
	factory text 
);

-- states
CREATE TABLE statemachine_states (
	machine VARCHAR(255) NOT NULL, 
	state VARCHAR(255) NOT NULL, 
	type VARCHAR(255) DEFAULT 'normal' NOT NULL CHECK(type IN ('final','initial','normal', 'regex')), 
	entry_command VARCHAR(255) NULL,
	exit_command VARCHAR(255) NULL,
	description text,
        PRIMARY KEY (machine, state),
        FOREIGN KEY (machine) REFERENCES statemachine_machines(machine) ON UPDATE CASCADE
);

--transitions
CREATE TABLE statemachine_transitions (
	machine VARCHAR(255) NOT NULL, 
	state_from VARCHAR(255) NOT NULL,
	state_to VARCHAR(255) NOT NULL, 
	event VARCHAR(255) NULL,
	rule VARCHAR(255) DEFAULT '\izzum\rules\TrueRule' NOT NULL,
	command VARCHAR(255) DEFAULT '\izzum\command\NullCommand' NOT NULL,
	priority TINYINT DEFAULT 1 NOT NULL, 
	description text,
        PRIMARY KEY (machine, state_from, state_to),
        FOREIGN KEY (machine, state_from) REFERENCES statemachine_states(machine, state) ON UPDATE CASCADE,
        FOREIGN KEY (machine, state_to) REFERENCES statemachine_states(machine, state) ON UPDATE CASCADE
);

--entities
CREATE TABLE statemachine_entities (
	machine VARCHAR(255) NOT NULL,
	entity_id VARCHAR(255) NOT NULL, 
	state VARCHAR(255) NOT NULL, 
	changetime TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY (machine, entity_id)
        -- only add foreign keys if you use the database for both 
        -- 1. the configuration of the statemachine and 
        -- 2. for persistence of state data.
        -- FOREIGN KEY (machine, state) REFERENCES statemachine_states(machine, state)
);
CREATE INDEX i_statemachine_entities_entity_id ON statemachine_entities (entity_id);

--history
CREATE TABLE statemachine_history (
	id INTEGER PRIMARY KEY auto_increment NOT NULL, 
	machine VARCHAR(255)  NOT NULL,
	entity_id VARCHAR(255) NOT NULL,
	state VARCHAR(255) NOT NULL, 
	changetime TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
	message text,
	exception TINYINT DEFAULT 0 	
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
('izzum', '\\izzum\\statemachine\\factory\\MysqlExampleFactory', 'this izzum: an example statemachine');

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
('izzum', 'new', 'ok','\\izzum\\rules\\TrueRule', 'izzum\\command\\NullCommand', 1, 'new_to_ok transition'),
('izzum', 'ok', 'fine','\\izzum\\rules\\TrueRule', 'izzum\\command\\NullCommand', 2, 'ok_to_fine transition'),
('izzum', 'fine', 'excellent','\\izzum\\rules\\TrueRule', 'izzum\\command\\NullCommand', 2, 'fine_to_excellent transition'),
('izzum', 'excellent', 'done','\\izzum\\rules\\TrueRule', 'izzum\\command\\NullCommand', 2, 'excellent_to_done transition'),
('izzum', 'new', 'bad','\\izzum\\rules\\TrueRule', 'izzum\\command\\NullCommand', 2, 'new_to_bad transition'),
('izzum', 'ok', 'bad','\\izzum\\rules\\FalseRule', 'izzum\\command\\NullCommand', 1, 'ok_to_bad transition'),
('izzum', 'fine', 'bad','\\izzum\\rules\\FalseRule', 'izzum\\command\\NullCommand', 1, 'fine_to_bad transition'),
('izzum', 'excellent', 'bad','\\izzum\\rules\\FalseRule', 'izzum\\command\\NullCommand', 1, 'excellent_to_bad transition'),
('izzum', 'bad', 'done','\\izzum\\rules\\ExceptionRule', 'izzum\\command\\NullCommand', 1, 'bad_to_done transition');

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
