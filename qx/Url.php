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
	static public function FromRoute(Route $routeFrom,$routePath = null,array $args = null)
	{
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
		$url = '';
		if(!isset(self::$_Path2Url[$path]))
		{
			//Si le chemin n'a pas été calculé on créé l'url
			$url = Config::Of('app')->get('wwwroot').'/';

			$pathToks = explode('.',$path);
			$routes = $scope->routes();
			$currentPath = '';

			foreach($pathToks as $tok)
			{
				$currentPath .= ($currentPath === ''?'':'.').$tok;
				if(isset(self::$_Path2Url[$currentPath]))
				{
					$url = self::$_Path2Url[$currentPath];
					$routes = self::$_Path2Rte[$currentPath]; //on considère qu'il a déjà été calculé car déjà en cache
					continue;
				}
				$tokLower = strtolower($tok);
				$route = $routes->findByName($tok);
				if($route) //Si la route existe
				{
					$type = $route->type();
					if($type == Route::DIR)
					{
						if(!isset(self::$_Path2Rte[$currentPath]))
						{   //Si la route n'est pas en cache
							$class = Controller::UseController($route->action());
							self::$_Path2Rte[$currentPath] = $class::RoutesDefinition();
						}
						$routes = self::$_Path2Rte[$currentPath];
						$url .= $route->pattern();
								self::$_Path2Url[$currentPath] = $url; //On stocke l'url intermediaire
								continue;
					}
					else if($type == Route::ACTION)
					{
						$url .= $route->pattern();
					}
					else if($type == Route::REMOTE_METHOD)
					{
						$url .= $route->pattern();
					}
					else
						throw new Exception('Unknow route type during uri compilation');

				}
				self::$_Path2Url[$path] = $url;
				break; //On sort dans tout les cas, la boucle se fait par le "continue" dans le premier "if"    
			}
		}
		else
		{   //On récupère l'url du cache
			$url = self::$_Path2Url[$path];
		}
		
		//Paramètres
		$get = array();
		if(!empty($args))
		{
			foreach($args as $k=>$v)
			{
				$url = preg_replace('`([#:~])'.$k.'`', $v, $url, -1, $c);
				if($c == 0)
					$get[$k] = $v; 
			}
		}
		//Replace empty number arguments
		$url = preg_replace('([#][\w]+)', '0', $url);

		if(!empty($get))
			$url .= '?'.http_build_query($get); //Sera mis à jour en http_build_query($get,null,null,PHP_QUERY_RFC3986)
		
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

