<?php
namespace qx;

//chdir('../');

/**
 *
 * @author Brice Dauzats
 */
class App extends ViewController 
{
	static private $_Instance;
	static public function Instance()
	{
		$class = get_called_class();
		if(!self::$_Instance)
			self::$_Instance = $class::Create();
		return self::$_Instance;
	}


	/**
	 * Create an instance of App
	 * @return App 
	 */
	static public function Create()
	{
		$class = get_called_class();
		$app = new $class();
		return $app;
	}
	
	
	private $_auth;
	/**
	 * Get auth instance
	 */
	public function auth()
	{
		if(!$this->_auth)
			$this->_auth = Auth::Instance();
		return $this->_auth;
	}
	
	private $_mainResponse;
	/**
	 * Get main response object
	 * 
	 * @return Response
	 */
	public function mainResponse()
	{
		if(!$this->_mainResponse)
			$this->_mainResponse = new Response($this);
		return $this->_mainResponse;
	}
	
	public function __construct()
	{
		$this->preInit();
		parent::__construct(null);
	}

	private function preInit()
	{
		Config::Of('app')
			->set('root', getcwd().'/app')
			->set('wwwroot', '')
			->set('namespace', 'app')
			->set('controller.namespace', 'controllers')
			
			->set('views', 'views')
			->set('view.namespace', '')

			->set('view.mainName', 'main')

			->set('components', 'components')
			->set('component.namespace', 'components')
			
			->set('db.dsn', '')
		;
		
		Config::Load('app/config.json');

		if($this->isDebug() && php_sapi_name() != 'cli')
		{
			$ips = Config::Of('app')->get('debug.ip');
			if(!empty($ips) && !in_array(@$_SERVER['REMOTE_ADDR'], $ips))
				Config::Of('app')->set('debug',false); //Disable debug
		}

	}

	protected function init()
	{
		$this->auth()->init();
	}

	public function exec()
	{
		parent::exec();
		echo $this->mainResponse()->generate();
		$this->postGlobalExec();
	}
	
	public function updateTranslations()
	{
		$compiler = new tools\LocaleCompiler(Config::Of('app')->get('locale.source','en'));
		$compiler->process(Config::Of('app')->get('locales',array('en')));
		return $compiler;
	}

	protected function postGlobalExec()
	{
		
	}
	
	public function isDebug()
	{
		return Config::Of('app')->get('debug',false);
	}
	
}
