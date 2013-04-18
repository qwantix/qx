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
			$this->_route->setDatas($datas);
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

	public function pathToUrl($routePath = null,array $args = null)
	{
		return Url::FromRoute($this->route(), $routePath, $args);
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
		$uri = $this->owner() ? $this->owner()->_route : null;
		$route = $this->routes()->match($uri);
		if($route)
		{
			$this->_route = $route;
			$this->preExec();
			$handler = $route->action();
			switch ($route->type())
			{
				case Route::DIR:
					$ctrl = $this->createSubController($handler, $route->datas());
					if($ctrl instanceof ViewController)
						$ctrl->execController($ctrl);
					else
						throw new Exception("Controller mustbe an instance of ViewController");
					break;
				case Route::ACTION:
					$this->execAction($handler, $route->args());
					break;
				case Route::REMOTE_METHOD:
					$this->execRemoteMethod($handler, $route->args());
					break;
			}
			$this->app()->mainResponse()->append($this->response());
			$this->postExec();
			return true;
		}
		return false;
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
}
