<?php
namespace qx;
/**
 * Base controller for view
 *
 * @author Brice Dauzats
 */
class ViewController extends Controller
{
	/**
	 * Get routes definition, override this method to add routes
	 */
	static public function RoutesDefinition($scope = null)
	{
		return Routes::Create($scope);
	}

	/**
	 * Return class name without view controller namespace
	 */
	static public function ShiftNamespace($cls)
	{
		$conf = Config::Of('app');
		return Tools::ShiftNamespace($conf->get('namespace').
			'\\'.$conf->get('controller.namespace'),$cls);
	}
	
	/**
	 * Get application instance
	 * @return App
	 */
	public function app()
	{
		return $this instanceof App?$this:$this->owner()->app();
	}
	
	private $_response;
	/**
	 * Get response part for this controller
	 * @return ResponsePart
	 */
	public function response()
	{
		if(!$this->_response)
			$this->_response = new ResponsePart($this);
		return $this->_response;
	}
	
	
	private $_routes;
	/**
	 * 
	 * @return Routes 
	 */
	public function routes()
	{
		if(!$this->_routes)
			$this->_routes = static::RoutesDefinition($this);
		return $this->_routes;
	}
	
	private $_route;
	
	/**
	 * Get current route for this action
	 * @return Route
	 */
	public function route()
	{
		return $this->_route;
		//return $this->_route ? $this->_route : $this->defaultRoute();
	}

	protected function setRoute($r, $datas = null)
	{
		if(is_string($r))
			$r = $this->app()->routes()
					->findByName($r)
					->forScope($this,$r{0} === '.');

		if($r instanceof Route)
			$this->_route = $r;
		if($datas)
			$this->_route->computeDatas((array)$datas);
		return $r;
	}

	private $_defaultRoute;
	public function defaultRoute()
	{
		if(!$this->_defaultRoute)
		{
			$conf = Config::Of('app');
			$n = Tools::ShiftNamespace($conf->get('namespace').
				'\\'.$conf->get('controller.namespace'),get_class($this));

			return $this->routes()->findByName($n);	
		}
		return $this->_defaultRoute;
		
	}
	
	private $_subController;
	public function subController()
	{
		return $this->_subController;
	}
	
	public function createSubController($name, $datas = null, $route  = null, $useSameResponseObject = false)
	{
		$ctrl = self::CreateController($name,$this);
		$this->_subController = $ctrl;
		if(!$ctrl)
			throw new Exception("Controller $handler not found");
		if($useSameResponseObject)
			$ctrl->_response = $this->response()->createSubResponse($ctrl);
		$ctrl->initController($datas);
		if($route)
			$ctrl->setRoute($route,$datas);
		return $ctrl;
	}

	public function pathToUrl($routePath = null,array $args = null, $absolute = false)
	{
		$uri = Url::FromRoute($this->route(), $routePath, $args);
		if($absolute)
		{
			$uri = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on' ? 'https' : 'http')
				. '://' . Config::Of('app')->get('host',$_SERVER['HTTP_HOST']) . $uri;
		}
		return $uri;
	}

	public function redirectTo($routePath,array $args = null, $permanent = false)
	{
		Url::Redirect($this->pathToUrl($routePath,$args),$permanent);
	}
	////////////////////
	
	public function __construct(ViewController $owner = null)
	{
		parent::__construct($owner);
	}

	public function exec()
	{
		
		if(!$this->_route)
		{
			$uri = $this->owner() ? $this->owner()->_route : null;
			$route = $this->routes()->match($uri);
		}
		else
			$route = $this->_route;

		if($route)
		{
			$this->_route = $route;
			if($this->preExec() !== false)
			{
				try 
				{
					$this->execRoute($route);
					//$this->app()->mainResponse()->append($this->response());
				}
				catch(\qx\Exception $e)
				{
					$this->onError($e);
				}
			}
			$this->app()->mainResponse()->append($this->response());
			$this->postExec();
			return true;
		}
		return false;
	}
	protected function execRoute(Route $route, $subRoute = null, $isSubRoute = false)
	{	
		$handler = $route->action();
		switch ($route->type())
		{
			case Route::DIR:
				$ctrl = $this->createSubController($handler, $route->datas());
				if($ctrl instanceof ViewController)
				{
					if($isSubRoute)
						$ctrl->setRoute( $route->forScope($ctrl) );
					if(!empty($subRoute))
					{ 
						//XXXX Fix redundance code of exec method
						if($ctrl->preExec() !== false)
						{
							try 
							{
								$ctrl->execSubRoute($subRoute, (array)$route->datas());
							}
							catch(\qx\Exception $e)
							{
								$ctrl->onError($e);
							}
						}
						$ctrl->app()->mainResponse()->append($ctrl->response());
						$ctrl->postExec();
					}
					else
						$ctrl->execController($ctrl);

					$r = $ctrl->response();
				}
				else
					throw new Exception("Controller must be an instance of ViewController");
				break;
			case Route::ACTION:
				if($isSubRoute)
					$this->setRoute( $route->forScope($this) );
				$this->execAction($handler, $route->args());
				$r = $this->response();
				break;
			case Route::REMOTE_METHOD:
				if($isSubRoute)
					$this->setRoute( $route->forScope($this) );
				$this->execRemoteMethod($handler, $route->args());
				$r = $this->response();
				break;
		}
		return $r;
	}
	protected function preExec()
	{

	}

	protected function postExec()
	{

	}

	protected function initController($datas)
	{

	}

	protected function execController(self $ctrl)
	{
		return $ctrl->exec();
	}
	
	protected function execAction($action, $args, $mergeDatas = true)
	{
		if(strpos($action, '.') !== false)
			$action = explode('.', $action);
		
		if(is_array($action))
		{
			if(is_string($action[0]))
			{
				$action[0] = $this->createSubController($action[0],$this->route()->datas());
			}
			if($action[0] instanceof ViewController)
			{
				$this->execController($action[0]);
				return;
			}
			else
				throw new Exception("Controller must be an instance of ViewController");

			$handler = $action;
		}
		else
			$handler = array($this,$action);
		$result = false;
		if(is_callable($handler))
		{
			$this->response()->action($action);
			if($this->preCallAction($action, $args) !== false)
			{
				$result = call_user_func_array($handler,$args);
				if($mergeDatas)
					$this->response()->datas()->merge($result);
				else
					$this->response()->datas($result);
			}
			$this->postCallAction($action, $args, $result);

		}
		else
			throw new Exception("$action isn't callable!");
		return $result;
	}

	protected function execRemoteMethod($method, $args)
	{
		$this->response()
			->type('json')
			->wrapInMain(false)
			->standalone(true);
		$datas = array();

		if(strpos(@$_SERVER["CONTENT_TYPE"], "text/json") !== false)
			$datas = Request::PostJson();
		else
			$datas = $_REQUEST;
		try {
			$this->execAction($method,array_merge($args, array($datas)),false);
		}
		catch(\Exception $e)
		{
			$this->response()->error = $e->getMessage();
			if(\qx\App::Instance()->isDebug())
			{
				$this->response()->error_file = $e->getFile();
				$this->response()->error_line = $e->getLine();
			}
		}
		
	}

	protected function preCallAction($action,$args)
	{
		return true;
	}

	protected function postCallAction($action,$args,$result)
	{

	}

	protected function subAction($route, $args)
	{

	}

	protected function createComponent($name)
	{
		return Component::CreateComponent($name, $this);
	}

	protected function execSubRoute($routeName,array $datas = null, $fromRoot = false)
	{
		$routeName = is_string($routeName) ? explode('.', $routeName) : $routeName;
		$o = $fromRoot?$this->app():$this;
		if($datas == null)
		{
			$datas = $this->route() ? $this->route()->datas() : array();
		}

		if($r = $o->routes()->findByName(array_shift($routeName)))
		{
			$r->computeDatas($datas);
			$response = $this->execRoute($r, $routeName, true);
			return $response;
		}
	}

	protected function onError(\qx\Exception $e)
	{
		throw $e;
	}
}
