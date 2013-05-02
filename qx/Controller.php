<?php
namespace qx;
/**
 * @author Brice Dauzats
 */
class Controller extends Observable 
{
	static private $_Uses = array();
	/**
	 * Include cntroller from name
	 * @param string $controller
	 * @return string $controller name
	 */
	static public function UseController($controller)
	{
		if(!isset(self::$_Uses[$controller]))
		{
			$ctrlName;
			
			if(!is_object($controller))
			{
				$conf = Config::Of('app');
				$ctrlName = $conf->get('namespace').
				'\\'.$conf->get('controller.namespace').
				'\\'.$controller
				;
			}
			else
				$ctrlName = get_class ($controller);

			$path = implode(DIRECTORY_SEPARATOR,explode('\\',$ctrlName)) . '.php';
			
			if(!file_exists($path))
				throw new Exception("Missing controller $controller");
			
			require_once $path;
			self::$_Uses[$controller] = $ctrlName;
		}
		return self::$_Uses[$controller];
		
		
	}

	/**
	 * Create controller
	 * @param string $controller
	 * @param Controller $owner
	 * @return Controller 
	 */
	static public function CreateController($controller, $owner = null)
	{   
		$ctrlName = is_object($controller)?get_class($controller):self::UseController($controller);
		return new $ctrlName($owner);
	}
	
	public function __construct(Controller $owner = null)
	{
		$this->_owner = $owner;
		$this->init();
	}
	
	
	private $_owner;
	/**
	 * Return owner of this controller
	 * @return Controller
	 */
	public function owner()
	{
		return $this->_owner;
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
	
	public function __toString()
	{
		return get_class($this);
	}
	
}
