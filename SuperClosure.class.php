<?php
/**
 * Super Closure Class
 * 
 * The SuperClosure class encapsulates a PHP Closure and adds new capabilities
 * like serialization and code retrieval. It uses the ReflectionFunction class
 * heavily to acquire information about the closure.
 * @author		Jeremy Lindblom
 * @copyright	(c) 2010 Synapse Studios, LLC.
 */
class SuperClosure {

	protected $closure = NULL;
	protected $reflection = NULL;
	protected $code = NULL;
	protected $used_variables = array();
	protected $serialized_variables = array();

    /**
     * @throws InvalidArgumentException
     * @param $function
     */
	public function __construct($function)
	{
		if ( ! $function instanceOf Closure)
			throw new InvalidArgumentException();

		$this->closure = $function;
		$this->reflection = new ReflectionFunction($function);
		$this->code = $this->_fetchCode();
		$this->used_variables = $this->_fetchUsedVariables();
	}

    /**
     * @return mixed
     */
	public function __invoke()
	{
		$args = func_get_args();
		return $this->reflection->invokeArgs($args);
	}

    /**
     * @return null|function|closure
     */
	public function getClosure()
	{
		return $this->closure;
	}

    /**
     * Parse source file to get declarative function code
     * @return array|string
     */
	protected function _fetchCode()
	{
		// Open file and seek to the first line of the closure
		$file = new SplFileObject($this->reflection->getFileName());
		$file->seek($this->reflection->getStartLine()-1);

		// Retrieve all of the lines that contain code for the closure
		$code = '';
		while ($file->key() < $this->reflection->getEndLine())
		{
			$code .= $file->current();
			$file->next();
		}

		// Only keep the code defining that closure
		$begin = strpos($code, 'function');
		$end = strrpos($code, '}');
		$code = substr($code, $begin, $end - $begin + 1);

		return $code;
	}

    /**
     * @return array|null|string
     */
	public function getCode()
	{
		return $this->code;
	}

    /**
     * @return array
     */
	public function getParameters()
	{
		return $this->reflection->getParameters();
	}

    /**
     * Parse source code to get used variables
     * within declarative function code
     *
     * @return array
     */
	protected function _fetchUsedVariables()
	{
		// Make sure the use construct is actually used
		$use_index = stripos($this->code, 'use');
		if ( ! $use_index)
			return array();

		// Get the names of the variables inside the use statement
		$begin = strpos($this->code, '(', $use_index) + 1;
		$end = strpos($this->code, ')', $begin);
		$vars = explode(',', substr($this->code, $begin, $end - $begin));

		// Get the static variables of the function via reflection
		$static_vars = $this->reflection->getStaticVariables();

		// Only keep the variables that appeared in both sets
		$used_vars = array();
		foreach ($vars as $var)
		{
			$var = trim($var, ' $');
            if( $static_vars[$var] instanceOf Closure ){
                $used_vars[$var] = serialize( new SuperClosure( $static_vars[$var] ) );
                $this->serialized_variables[] = $var;
            }else{
                $used_vars[$var] = $static_vars[$var];
            }
		}

		return $used_vars;
	}

    /**
     * @return array
     */
	public function getUsedVariables()
	{
		return $this->used_variables;
	}

    /**
     * Used to correctly serialize object
     * @return array
     */
	public function __sleep()
	{
		return array('code', 'used_variables', 'serialized_variables');
	}

    /**
     * Used to rebuild object
     *
     * @throws Exception
     * @return void
     */
	public function __wakeup()
	{
        foreach( $this->used_variables as $used_var_name=>$used_var ){
            if( in_array($used_var_name, $this->serialized_variables) ){
                $used_var = unserialize($used_var);
            }
            if( $used_var instanceof SuperClosure ){
                $$used_var_name = $used_var->closure;
            }else{
                $$used_var_name = $used_var;
            }
        }

		eval('$_function = '.$this->code.';');
		if (isset($_function) AND $_function instanceOf Closure)
		{
			$this->closure      = $_function;
			$this->reflection   = new ReflectionFunction($_function);
		}
		else
			throw new Exception("Not a function, or closure");
	}
}
