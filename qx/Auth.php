<?php
namespace qx;
/**
 *
 * @author Brice Dauzats
 */
class Auth extends Controller
{
	static private $_Instance;
	static public function Instance()
	{
		if(!self::$_Instance)
			self::$_Instance = new self();
		return self::$_Instance;
	}

	protected $session;
	protected $sessionKey = '__auth__';
	protected $auth; 
	protected $initialized = false;

	public function __construct()
	{   
	}
	
	public function init()
	{
		if($this->initialized)
			return;
		$s = Session::Of($this->sessionKey);
		if($s && isset($s->id))
		{
			$this->session = $s;
			$this->auth->restore($this->session->id, $this->session->identity);
		}
		$this->initialized = true;
	}

	public function setCredentials(ICredentials $auth)
	{
		$this->auth = $auth;
	}
	public function login($datas)
	{
		try 
		{
			if(!$this->auth->authenticate($datas))
				throw new Exception("Authentication error");
				
			$this->session = Session::Of($this->sessionKey);
			$this->session->id = $this->auth->getId();
			$this->fire('logged');
			//$this->session->identity = $this->auth->getIdentity();
			return true;
		}
		catch(Exception $e)
		{
			
		}
		return false;
	}
	public function logout()
	{
		$this->auth->logout($datas);
		$this->session->destroy();
	}
	public function hasRight($right)
	{
		return $this->auth->hasRight($right);
	}
	
	public function isLogged()
	{
		return !!$this->auth->getIdentity();
	}
	public function getIdentity()
	{
		return $this->auth->getIdentity();
	}
	public function getCredentials()
	{
		return $this->auth;
	}
}

