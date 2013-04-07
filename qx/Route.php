<?php
namespace qx;

/**
 * @author Brice Dauzats
 */
class Route 
{
	const DIR = 'dir';
	const ACTION = 'action';
	const REMOTE_METHOD = 'remote_method';
	
	private $_type;
	public function type()
	{
		return $this->_type;
	}
	
	private $_name;
	public function name()
	{
		return $this->_name;
	}
	
	private $_pattern;
	public function pattern()
	{
		return $this->_pattern;
	}
	
	private $_action;
	public function action()
	{
		return $this->_action;
	}
	
	private $_argsDef;
	public function argsDef()
	{
		return $this->_argsDef;
	}
	
	private $_args;
	public function args()
	{
		return $this->_args;
	}
	
	public function setArgs(array $args)
	{
		$this->_args = $args;
	}


	private $_datas;
	/**
	 *
	 * @param bool $all get datas from parents
	 * @return stdClass
	 */
	public function datas($all = false)
	{
		if($all)
		{
			if($this->_type == self::DIR)
			{
				//On récupère directement le parent si c'est un dossier, car la donnée à ce niveau ne nous concerne pas
				$d = array();
				$r = $this->parent();
			}
			else
			{
				$d = (array)$this->_datas;
				$r = $this; 
			}
			while($r)
			{
				$d = $d + (array)$r->_datas;
				$r = $r->parent();
			}
			return (object)$d;
		}
		return $this->_datas;
	}
	
	private $_rest;
	public function rest()
	{
		return $this->_rest;
	}
	
	private $_scope;
	/**
	 * @return ViewController
	 */
	public function scope()
	{
		return $this->_scope;
	}
	
	private $_parent;
	/**
	 * Parent route
	 * @return Route
	 */
	public function parent()
	{
		if($this->_parent == null && $this->_scope != null && $this->_scope->owner())
			$this->_parent = $this->_scope->owner()->route();
		return $this->_parent;
	}
	
	public function __construct($type, $name, $pattern, $action, array $argsDef = null)
	{
		$this->_type = $type;
		$this->_name = $name;
		if(is_array($pattern) || is_object($pattern))
		{
			$pattern = (object)$pattern;
			$this->_pattern = @$pattern->pattern;
			$this->_contentType = @$pattern->contentType;
			$this->_method = @$pattern->method;
			
		}
		else
			$this->_pattern = $pattern;
		
		$this->_action = $action;
		$this->_argsDef = $argsDef;
	}
	/**
	 * Match $uri,
	 * return true if success otherwise return false
	 * @param string $uri
	 * @return bool
	 */
	public function match($uri)
	{
		$p = $this->_pattern;
		
		if(!empty($this->_method))
		{
			$rm = strtoupper($_SERVER['REQUEST_METHOD']);
			if(is_array($this->_method) && !in_array($rm, array_map('strtoupper', $this->_method)))
				return false;
			else if($rm !== $this->_method)
				return false;
		}
		if(!empty($this->_contentType))
		{
			$accepts = $_SERVER['HTTP_ACCEPT'];
			//TODO
		}
		
		if(($p && $p{0} != '`') || !$p)
		{
			$p = preg_replace(
				array('`\:(\w+)`',	'`#(\w+)`',	'`~(\w+)`'),
				array('(?P<\\1>[^/]+)',	'(?P<\\1>\d+)',	'(?P<\\1>.*)'	),
			($p)//TODO make a custom preg_quote
			);
			$p = $this->_type == self::DIR ? "`^$p/?`" : "`^$p$`";
		}
		
		if(!preg_match($p, $uri, $m))
			return false;
		array_shift($m);
		
		$argsDef = $this->_argsDef;
		if(is_null($argsDef))
		{   //If not args def specified, search args in pattern
			$keys = array_filter(array_keys($m),'is_numeric');
			$this->_args = array();
			foreach(array_filter(array_keys($m),'is_numeric') as $i)
				$this->_args[] = $m[$i];
			
			$this->_datas = new \stdClass();
			foreach(array_filter(array_keys($m),'is_string') as $k)
				$this->_datas->$k = $m[$k];

		}
		else
		{
			$args = array();
			if(!empty($argsDef))
				foreach($argsDef as $name)
				{
					$defValue = null;
					if(is_array($name))
						if(sizeof($name)>1)
						{
							$defValue = $name[1];
							$name = $name[0];
						}
						else
						{
							$defValue = $name[0];
							$name = null;
						}
						
					$args[$name] = isset($m[$name]) ? $m[$name] : $defValue;
				}
				$this->_datas = (object)$args;
				$this->_args = array_values($args);
		}
		$this->_rest = preg_replace($p, '', $uri);
		return true;
			
	}
	
	/**
	 * Get copy of this route for scpecified scope
	 * @param object $scope
	 * @return Route 
	 */
	public function forScope(ViewController $scope)
	{
		$r = clone $this;
		$r->_scope = $scope;
		$r->_parent = $scope->route(); //Normalement la route n'est pas défini dans le scope à ce moment là...
		return $r;
	}

	public function isDir()
	{
		return $this->_type == self::DIR;
	}
	public function isAction()
	{
		return $this->_type == self::ACTION;
	}

	public function __toString()
	{
		//On appelle d'abord l'accesseur parent() afin de forcer le calcul du parent
		return ($this->parent()?$this->_parent.'.':'').$this->_name;
	}




}

