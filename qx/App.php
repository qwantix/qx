<?php
namespace qx;

//chdir('../');

/**
 *
 * @author Brice Dauzats
 */
class App extends ViewController 
{
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
			$this->_auth = new Auth::Instance();
		return $this->_auth();
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
			->set('db.dsn', '')
		;
		
		Config::Load('app/config.json');
		
		Session::Start();
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
		$compiler = new tools\LocaleCompiler();
		$compiler->process(Config::Of('app')->get('locales',array('en')));
		return $compiler;
	}

	public function postGlobalExec()
	{
		
	}
	
	public function isDebug()
	{
		return Config::Of('app')->get('debug',false);
	}
	
}
