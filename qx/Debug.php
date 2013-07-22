<?php
namespace qx;
/**
 * Description of Config
 *
 * @author Brice Dauzats
 */
class Debug
{
	static private $_Instance;
	static public function Instance()
	{
		if(!self::$_Instance)
			self::$_Instance = new self;
		return self::$_Instance;
	}

	private $_queries = array();

	public function logQuery($sql, $args)
	{
		$this->_queries = array($sql, $args);
		/*
		echo "<pre>$sql</pre>";
		var_dump($args);
		/**/
	}

	public function getQueries()
	{
		return $this->_queries;
	}
}
