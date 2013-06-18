<?php
namespace qx;

/**
 * @author Brice Dauzats
 */
class Exception extends \Exception {
    
}

class ViewNotFoundException extends Exception
{
    
}
class FormException extends Exception
{
	
}

class DbException extends Exception
{
	public function __construct(\Exception $e, $sql, $args)
	{
		if(\qx\Config::Of('app')->get('debug') == true)
		{
			echo $sql;
			var_dump($args);
			var_dump($e->getMessage());
		}
		parent::__construct($e);
	}
}