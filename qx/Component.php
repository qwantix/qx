<?php
namespace qx;
/**
 * @author Brice Dauzats
 */
class Component extends Observable {
	static private $_Uses = array();
	/**
	 * Include component from name
	 * @param string $component
	 * @return string $component name
	 */
	static public function UseComponent($component)
	{
		if(!isset(self::$_Uses[$component]))
		{
			$ctrlName;
			
			$conf = Config::Of('app');
			$ctrlName = $conf->get('namespace').
				'\\'.$conf->get('component.namespace').
				'\\'.$component
			;
			$path = $conf->get('namespace')
				.DIRECTORY_SEPARATOR.$conf->get('component.namespace')
				.DIRECTORY_SEPARATOR.strtolower($component)
				.DIRECTORY_SEPARATOR.Tools::ClassName($component)
				.'.php'
			;
			$path = str_replace('\\', DIRECTORY_SEPARATOR, $path);
			if(!file_exists($path))
				throw new Exception("Missing component $component");
			
			require_once $path;

			self::$_Uses[$component] = $ctrlName;
		}
		return self::$_Uses[$component];
	}

	/**
	 * Create component
	 * @param string $component
	 * @param Component $owner
	 * @return Component 
	 */
	static public function CreateComponent($component, ViewController $owner = null)
	{   
		$ctrlName = is_object($component)?get_class($component):self::UseComponent($component);
		return new $ctrlName($owner);
	}

	public function __construct(ViewController $owner = null)
	{
		$this->_owner = $owner;
		$this->init();
	}
	
	
	private $_owner;
	/**
	 * Return owner of this component
	 * @return ViewController
	 */
	public function owner()
	{
		return $this->_owner;
	}
	
	protected function setOwner(ViewController $owner = null)
	{
		$this->_owner = $owner;
	}

	public function response()
	{
		return $this->owner()->response();
	}

	private $_session;
	/**
	 * Return session for this controller
	 * @return Session
	 */
	protected function session()
	{
		if(!$this->_session)
			$this->_session = new Session ($this);
		return $this->_session;
	}
	
	protected function init()
	{
		
	}

	public function render()
	{
		
	}

	private $_cid;
	public function componentId()
	{
		if(!$this->_cid)
			$this->_cid = uniqid();
		return $this->_cid;
	}
	private $_dir;
	protected function getDir()
	{
		if(!$this->_dir)
		{
			$this->_dir = Config::Of('app')->get('root').
						DIRECTORY_SEPARATOR.
						Config::Of('app')->get('components').
						DIRECTORY_SEPARATOR.
						strtolower(
							Tools::ShiftNamespace(Config::Of('app')->get('namespace')
								.'\\'
								.Config::Of('app')->get('component.namespace')
							, $this)
						);
			$this->_dir = str_replace('\\', DIRECTORY_SEPARATOR , $this->_dir);
		}
		return $this->_dir;
	}
	protected function createView($name)
	{
		$v = $this->owner()->response()->createView('html',$name); //Get view in current context
		
		if(!$v->exists())
		{
			$v = View::Create($name,'html');
			$v->setRootViewDir($this->getDir());
		}
		return $v;
	}
	protected function renderView($name, $datas = null)
	{
		if(!($datas instanceof \qx\Data))
			$datas = new \qx\Data($datas);
		$datas->componentId = $this->componentId();
		return $this->createView($name)->render($datas,$this->owner());
	}
}