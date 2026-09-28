<?php

namespace Izzum\Rules;

/**
 * RuleResult contains a result for a certain rule, indicating that the
 * rule wants to store some extra information for a client to consume.
 *
 * This could be when there are multiple steps or paths in rule execution and
 * the client wants to know which one of those were executed after a
 * rule->applies() call has been made.
 *
 * This will most probably happen in a rule where multipe business rules are
 * combined or where there are multiple conditional paths.
 *
 * @author Rolf Vreijdenberger
 * @author Richard Ruiter
 */
class RuleResult
{
    public function __construct(private readonly Rule $rule, private string $result) {}

    public function getRule(): Rule
    {
        return $this->rule;
    }

    public function getResult(): string
    {
        return $this->result;
    }
}
