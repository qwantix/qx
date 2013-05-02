<?php
namespace qx;
/**
 * @author Brice Dauzats
 */
class Routes implements \IteratorAggregate
{
	static public function Create($scope = null)
	{
		return new self($scope);
	}
	
	private $_routes;
	private $_index;
	private $_root;
	private $_otherwise;
	private $_scope;
	
	public function scope()
	{
		return $this->_scope;
	}
	public function __construct($scope = null)
	{
		$this->_routes = array();
		$this->_index = array();
		$this->_scope = $scope;
	}
	
	public function args($pattern, $args = array())
	{
		
	}


	public function root($action,$args = array())
	{
		$this->_root = new Route(Route::ACTION,$action,'',$action);
		if($args != null && !is_array($args))
			$args = array($args);
		$this->_root->setArgs($args);
		return $this;
	}

	public function action($pattern, $method = null,array $argsDef = null, $routeName = null, $customData = null)
	{
		if(empty($method))
			$method = preg_replace('`[^\w]`', '', $pattern);
		return $this->add(Route::ACTION, $routeName?$routeName:$method, $pattern, $method, $argsDef, $customData);
	}

	public function method($name, $method = null, array $argsDef = null, $routeName = null, $customData = null)
	{
		if(empty($method))
			$method = preg_replace('`[^\w]`', '', $name);
		return $this->add(Route::REMOTE_METHOD, $routeName?$routeName:$method, $name, $method, $argsDef, $customData);
	}

	public function dir($pattern, $controller ,array $argsDef = null, $routeName = null, $customData = null)
	{
		if(strpos($pattern, '/') === false)
			$pattern .= '/';
		return $this->add(Route::DIR, $routeName?$routeName:$controller, $pattern, $controller, $argsDef, $customData);
	}

	public function otherwise($action, $args = array())
	{
		$this->_otherwise = new Route(Route::ACTION,$action,'',$action);
		if($args != null && !is_array($args))
			$args = array($args);
		$this->_otherwise->setArgs($args);
		return $this;
	}

	public function add($type,$name,$pattern,$action = null,array $argsDef = null, $customData = null)
	{
		$name = strtolower($name);
		
		$r = new Route($type,$name,$pattern,$action,$argsDef, $customData);
		$this->_routes[] = $r;
		
		$this->_index[$name] = $r; //Indexe by realname
		$this->_index[Tools::className($name)] = $r; //Index by class name
		
		return $this;
	}

	/**
	 *
	 * @param mixed $uriOrRoute
	 * @return Route 
	 */
	public function match($uriOrRoute = null)
	{
		$parent = null;
		if($uriOrRoute instanceof Route)
		{
			$parent = $uriOrRoute;
			$uri = $parent->rest();
		}
		else
			$uri = $uriOrRoute;
		if(is_null($uri))
		{
			$wwwroot = Config::Of('app')->get('wwwroot','');
			if(!empty($wwwroot) && $wwwroot[0] != '/')
				$wwwroot = "/$wwwroot";
			$tok = preg_split('`[?#]`',substr($_SERVER['REQUEST_URI'], strlen($wwwroot)));
			$uri = preg_replace('`^/`', '', $tok[0]);
		}
		$uri = trim($uri);
		if( ($uri == '' || $uri == '/') && $this->_root )
			return $this->_root->forScope($this->_scope);
		
		foreach($this->_routes as $r)
			if($r->match($uri))
				return $r->forScope($this->_scope);
			if($this->_otherwise)
				return $this->_otherwise->forScope($this->_scope);
	}
		
	public function findByName($name)
	{
		$name = strtolower($name);
		$name = str_replace(':','\\', $name); //Remplace le : par \, permet d'éviter de nommer les route avec des  \ dans le cas d'utilisation de namespace
		$routes = $this;
		$route = null;
		foreach (explode('.', $name) as $name)
		{
			if(isset($routes->_index[$name]))
			{
				$route = $routes->_index[$name]->setParent($route);
				$routes = $route->routes();
			}
			else if($routes->hasRoot($name))
			{
				$route = $routes->_root->setParent($route);
			}
			else
				return null;
		}
		return $route;
		//return isset($this->_index[$name]) ? $this->_index[$name] : null;
	}

	public function hasRoot($name = null)
	{
		if(empty($this->_root))
			return false;
		if($name)
			return strtolower($this->_root->name()) == strtolower($name);
		return true;
	}

	public function hasOtherwise($name = null)
	{
		if(empty($this->_otherwise))
			return false;
		if($name)
			return strtolower($this->_otherwise->name()) == strtolower($name);
		return true;
	}

	public function getRootAction()
	{
		return $this->_root->action();
	}

	public function getIterator()
	{
		return new ArrayIterator($this->_routes);
	}
}
