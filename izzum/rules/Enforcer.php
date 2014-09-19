<?php
namespace izzum\rules;
use izzum\command\Null;
use izzum\command\ICommand;
use izzum\rules\Rule;
/**
 * The Enforcer class binds rules with commands to execute.
 * 
 * It can be used as an object instance, so the enforcer can be passed around
 * in application code, or it can be used statically.
 * @author Rolf Vreijdenberger
 *
 */
class Enforcer {
    /**
     * @var Rule $rule
     */
    private $rule;
    /**
     * @var ICommand
     */
    private $true;
    
    /**
     * @var ICommand
     */
    private $false;
    
    /**
     * 
     * @param Rule $rule
     * @param ICommand $true the (composite) command to execute if the rule applies
     * @param ICommand $false the (composite) command to execute if the rule does not apply
     *     [optional, defaults to \Biblio\Command\Null]
     *     this implies that we mostly expect to enforce 'happy flows'
     */
    public function __construct(Rule $rule, ICommand $true, $false = null){
        $this->rule = $rule;
        $this->true = $true;
        $this->false = $false;
    }
    
    /**
     * enforce the rule and apply the commands
     * @return boolean if the rule applied or not
     */
    public function enforce()
    {
        return self::obey($this->rule, $this->true, $this->false);
    }
    
  
    /**
     * Give a direct order
     * @param Rule $rule the (composite) rule to check
     * @param ICommand $true the (composite) command to execute if the rule applies
     * @param ICommand $false the (composite) command to execute if the rule does not apply
     *     [optional, defaults to \Biblio\Command\Null]
     *     this implies that we mostly expect to enforce 'happy flows'
     * @throws izzum\rules\Exception
     * @throws Biblio\Command\Exception
     * @return boolean if the rule applied or not
     */
    public static final function obey(Rule $rule, ICommand $true, $false = null)
    {
        $result = false;
        if($rule->applies()) {
            $true->execute();
            $result = true;
        } else {
            if($false == null)
            {
                $false = new Null();
            }
            $false->execute();
        }
        return $result;
    }
    
    /**
     * @return string
     */
    public function toString()
    {
        $rule = $this->rule->toString();
        $true = $this->true->toString();
        $false = $this->false ? $this->false : new Null();
        $false = $false->toString();
        return "Enforcer('$rule', '$true', '$false')";
    }
    
}