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

	private $_writer;
	public function writer()
	{
		return $this->_writer;
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

	private $_customDatas = array();
	public function customDatas($all = false)
	{
		if($all && $this->parent())
			return array_merge($this->parent()->customDatas(true),$this->_customDatas);
		return $this->_customDatas;
	}
	
	public function setCustomDatas(array $datas)
	{
		$this->_customDatas = $datas;
	}

	private $_datas;
	/**
	 *
	 * @param bool $all get datas from parents
	 * @return stdClass
	 */
	public function datas($all = false, $includeSelf = false)
	{
		if($all)
		{
			if($this->_type == self::DIR && $includeSelf)
			{
				$d = array();
				$r = $this->parent();
			}
			else
			{
				$d = array_merge((array)$this->_datas, $this->_customDatas);
				$r = $this; 
			}
			while($r)
			{
				$d = $d + array_merge((array)$r->_datas, $r->_customDatas);
				$r = $r->parent();
			}
			return (object)$d;
		}
		return array_merge((array)$this->_datas, $this->_customDatas);
	}
	
	public function setDatas($datas, $merge = true)
	{
		if(!$merge || !$this->_datas)
			$this->_datas = new \stdClass();
		foreach ($datas as $k => $v)
			$this->_datas->$k = $v;
	}
	private $_rest;
	public function rest()
	{
		return $this->_rest;
	}
	public function setRest($uri)
	{
		$this->_rest = $uri;
		return $this;
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
		{
			$this->_parent = $this->_scope->owner()->route();
			if($this->_parent && $this->_parent->isAction())
				$this->_parent = $this->_parent->parent();
		}
		return $this->_parent;
	}
	private $_customParents = false;
	public function setParent(self $route = null)
	{
		if($route && $route->isAction())
			$route = $route->parent();
		$this->_parent = $route;
		$this->_customParents = true;
		return $this;
	}

	public function isCustomParent()
	{
		return $this->_customParents;
	}
	private $_routes;
	public function routes()
	{
		if(!$this->_routes && $this->isDir())
		{
			$class = Controller::UseController($this->action());
			$this->_routes = $class::RoutesDefinition();
		}
		return $this->_routes;
	}

	public function __construct($type, $name, $pattern, $action, array $argsDef = null, array $customDatas = null)
	{
		$this->_type = $type;
		$this->_name = $name;
		if(is_array($pattern) || is_object($pattern))
		{
			$pattern = (object)$pattern;
			$this->_pattern = @$pattern->pattern;
			$this->_writer = @$pattern->writer;
			$this->_contentType = @$pattern->contentType;
			$this->_method = @$pattern->method;
			
		}
		else
			$this->_pattern = $pattern;
		
		$this->_action = $action;
		$this->_argsDef = $argsDef;
		if($customDatas)
			$this->_customDatas = $customDatas;
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
			if($this->_type == self::DIR)
				$p = $p && $p[strlen($p) - 1] === '/' ? "`^$p?`" : "`^$p/?`";
			else
				$p = "`^$p$`";
		}
		
		if(!preg_match($p, $uri, $m))
			return false;
		array_shift($m);
		$this->computeDatas($m);
		$this->_rest = preg_replace($p, '', $uri);
		return true;
			
	}
	public function computeDatas(array $datas)
	{
		$datas = array_merge($datas, $this->_customDatas);
		
		$argsDef = $this->_argsDef;
		if(is_null($argsDef))
		{   //If not args def specified, search args in pattern
			$keys = array_filter(array_keys($datas),'is_numeric');
			$this->_args = array();
			foreach(array_filter(array_keys($datas),'is_numeric') as $i)
				$this->_args[] = $datas[$i];
			
			$this->_datas = new \stdClass();
			foreach(array_filter(array_keys($datas),'is_string') as $k)
				$this->_datas->$k = $datas[$k];
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
						
					$args[$name] = isset($datas[$name]) ? $datas[$name] : $defValue;
				}
				$this->_datas = (object)$args;
				$this->_args = array_values($args);
		}
	}
	/**
	 * Get copy of this route for scpecified scope
	 * @param object $scope
	 * @return Route 
	 */
	public function forScope(ViewController $scope, $autoDefineParent = true)
	{
		$r = clone $this;
		$r->_scope = $scope;
		if($autoDefineParent && $scope->route())
			$r->_parent = !$scope->route()->isDir() ? $scope->route()->parent() : $scope->route();
		return $r;
	}

	public function isDir()
	{
		return $this->_type === self::DIR;
	}
	public function isAction()
	{
		return $this->_type === self::ACTION;
	}
	public function isMethod()
	{
		return $this->_type === self::REMOTE_METHOD;
	}

	public function isExternRoute()
	{
		return $this->isAction() && strpos($this->_action, '.') !== false; 
	}

	public function __toString()
	{
		//On appelle d'abord l'accesseur parent() afin de forcer le calcul du parent
		return ($this->parent()?$this->_parent.'.':'').$this->_name;
	}




}

