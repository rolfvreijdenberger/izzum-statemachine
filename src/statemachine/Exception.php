<?php
namespace izzum\statemachine;

/**
 * Exception class.
 * used internally in the statemachine.
 * Codes can be used to get more information about the errors or to translate.
 * 
 * @author Rolf Vreijdenberger
 *        
 */
class Exception extends \Exception {
    
    /**
     * add constants, useful for testing exception codes
     * 
     * @var int
     */
    const ADD_TO_SM_FAILED = 1,
        SM_CAN_FAILED = 2,
        SM_FACTORY_GET_INVALID = 3,
        SM_TRANSITION_FAILED = 4,
        FACTORY_CREATE_FAILURE = 5,
        COMBI_ENTITY_MACHINE_BAD = 6,
        BUILDER_FAILURE = 7,
        IO_FAILURE_GET = 8,
        IO_FAILURE_SET = 9,
        IO_BAD_MAPPING = 10,
        RULE_CREATION_FAILURE = 11,
        COMMAND_CREATION_FAILURE = 12,
        SM_LOG_FAILED = 13,
        COMMAND_EXECUTION_FAILURE = 14,
        RULE_APPLY_FAILURE = 15,
        BAD_LOADERDATA = 16,
        SM_NO_CURRENT_STATE_FOUND = 17,
        SM_NO_TRANSITION_FOUND = 18,
        SM_TRANSITION_NOT_ALLOWED = 19,
        SM_CONTEXT_DIFFERENT_MACHINE = 20,
        SM_NO_INITIAL_STATE_FOUND = 21,
        PERSISTENCE_FAILED_TO_CONNECT = 22,
        PERSISTENCE_LAYER_EXCEPTION = 23,
        SM_RUN_FAILED = 24,
        SM_NO_TRANSITION_FOUND_ON_STATE = 25,
        SM_CONTEXT_DIFFERENT_ENTITY = 26,
        SM_UNKNOWN_STATE = 27,
        CALLABLE_FAILURE = 28;
}