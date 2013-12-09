<?php
namespace qx;

/**
 *
 * @author Brice Dauzats
 */
class Url
{
	static private $_Path2Url = array();
	static private $_Path2Rte = array();
	
	/**
	 *	
	 * @param Route $routeFrom
	 * @param string $routePath
	 * @param array $args
	 * @return string 
	 */
	static public function FromRoute(Route $routeFrom = null,$routePath = null,array $args = null, $replaceEmptyArgs = true)
	{
		if($routeFrom == null)
		{
			$routeFrom = new Route(null,null,null,null);
			$routeFrom = $routeFrom->forScope(App::Instance(),false);
		}
		$scope = $routeFrom->scope();
		
		if($routePath)
		{
			$path = $routePath;
			if($path{0} === '.')
			{
				$path = substr($path, 1);
				while($path{0} === '.')
				{
					$scope = $scope->owner();
					$path = substr($path, 1);
				}
			}
			else
				$scope = $scope->app();
		}
		else
		{
			$path = (string)$routeFrom;
		}
		
		if($scope && $scope->owner())
		{
			//$path = $scope->owner()->route() . '.' . $path; 
			$path = $scope->route()->parent() . '.' . $path; 
			
			$args = $args ? $args : array();
			$args = array_merge((array)$routeFrom->datas(true),$args);
			$scope = $scope->app();
		}
		
		$path = strtolower($path);
		$urlParts = array();
		if(!isset(self::$_Path2Url[$path]))
		{
			//Si le chemin n'a pas été calculé on créé l'url
			$urlParts[] = Config::Of('app')->get('wwwroot').'/';

			$pathToks = explode('.',$path);
			$routes = $scope->routes();
			$currentPath = '';

			foreach($pathToks as $tok)
			{
				$currentPath .= ($currentPath === ''?'':'.').$tok;
				if(isset(self::$_Path2Url[$currentPath]))
				{
					$urlParts = self::$_Path2Url[$currentPath];
					$routes = self::$_Path2Rte[$currentPath]; //on considère qu'il a déjà été calculé car déjà en cache
					continue;
				}
				$tokLower = strtolower($tok);
				$route = $routes->findByName($tok);
				
				if($route) //Si la route existe
				{
					$type = $route->type();
					$isExtern = $route->isExternRoute();
					if($type == Route::DIR || $isExtern)
					{
						$action = $route->action();
						if($isExtern)
							list($action,$pathToks[]) = explode('.', $route->action());
						
						if(!isset(self::$_Path2Rte[$currentPath]))
						{   //Si la route n'est pas en cache
							$class = Controller::UseController($action);
							self::$_Path2Rte[$currentPath] = $class::RoutesDefinition();
						}
						$routes = self::$_Path2Rte[$currentPath];
						$urlParts[] = $route->writer() ? $route->writer() : $route->pattern();
						self::$_Path2Url[$currentPath] = $urlParts; //On stocke l'url intermediaire
						continue;
					}
					else if($type == Route::ACTION || $type == Route::REMOTE_METHOD)
					{
						$urlParts[] = $route->writer() ? $route->writer() : $route->pattern();
					}
					else
						throw new Exception('Unknow route type during uri compilation');

				}
				self::$_Path2Url[$path] = $urlParts;
				break; //On sort dans tout les cas, la boucle se fait par le "continue" dans le premier "if"    
			}
		}
		else
		{   //Get url from cache
			$urlParts = array_merge(self::$_Path2Url[$path]); 
		}

		$url = '';

		//Parameters
		$get = !empty($args) ? (array)$args : array();
		foreach ($urlParts as $urlPart)
		{
			if($urlPart instanceof \Closure)
			{
				$url .= $urlPart($args,$rest);
				foreach ($get as $k => $v)
					if(!array_key_exists($k, $rest))
						unset($get[$k]);
			}
			else
			{
				$s = $urlPart;
				if(!empty($args))
				{
					foreach($args as $k=>$v)
					{
						$s = preg_replace('`([#:~])'.$k.'`', $v, $s, -1, $c);
						if($c != 0)
							unset($get[$k]); 
					}
					if($replaceEmptyArgs)
					{
						//Replace empty number arguments
						$s = preg_replace('([#][\w]+)', '0', $s);
					}
				}
				$url .= $s;
			}
		}
		if(!empty($get))
		{
			$get = http_build_query($get);
			if(!empty($get))
				$url .= '?'.$get; //Sera mis à jour en http_build_query($get,null,null,PHP_QUERY_RFC3986)
		}
		
		return $url;
	}
	
	static public function Redirect($url, $permanent = false)
	{
		if($permanent)
			header('HTTP/1.1 301 Moved Permanently',true,301);
		header('Location: '.$url);
		exit;
	}

	static public function Sanitize($url)
	{
		$s = trim($s);
		$s = Tools::StripAccent($s);
		$s = preg_replace(
				array(
					'/\s+/',
					'/[^\w-]+/'
				),
				array(
					'-',
					''
				),
				$s);
		$s = strtolower($s);
		return $s;
	}

}

