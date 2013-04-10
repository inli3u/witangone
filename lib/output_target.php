<?php

class OutputTarget
{
    const EXPRESSION = 1;
	const STDOUT = 2;
	const VARIABLE = 3;
    
    public $type = null;
    public $hits = 0;
    public $variable_ident = null;
    
    
    public static function StdOut()
    {
        $target = new OutputTarget();
        $target->type = self::STDOUT;
        return $target;
    }
    
    public static function Expression()
    {
        $target = new OutputTarget();
        $target->type = self::EXPRESSION;
        return $target;
    }
    
    public static function Variable($variable_ident)
    {
        $target = new OutputTarget();
        $target->type = self::VARIABLE;
        $target->variable_ident = $variable_ident;
        return $target;
    }
    
    public function is_stdout()
    {
        return $this->type === self::STDOUT;
    }
    
    public function is_expression()
    {
        return $this->type === self::EXPRESSION;
    }
    
    public function is_variable()
    {
        return $this->type === self::VARIABLE;
    }
}