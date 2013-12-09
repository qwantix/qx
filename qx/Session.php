<?php
namespace qx;
/**
 * @author Brice Dauzats
 */
class Session 
{
	static public $AutoStart = true;
	static public $KEY_SESSION_HASH = '__$SESSION_HASH$__';
	static public $KEY_SESSION_TOKEN = '__$SESSION_TOKEN$__';
	static public $SESSION_TOKEN_FIELD = 'session_token';

	static public function CheckAutoStart()
	{
		if(self::$AutoStart && !self::$started)
			self::Start();
	}
	static protected function SessionHash()
	{
		self::CheckAutoStart();
		return session_id().'/'.
				md5(
					Config::Of('app')->get('session.salt','')
					
					//'$'.@$_SERVER['HTTP_USER_AGENT'].
					//'|'.$_SERVER['HTTP_HOST'].
					.'-'.@$_SERVER['REMOTE_ADDR']
					//'/'.$_SERVER['HTTP_ACCEPT_LANGUAGE'].
				);
	} 

	static public function Token($regenerate = false)
	{
		self::CheckAutoStart();
		if(!self::Started()) 
			return null;
		if($regenerate || empty( $_SESSION[self::$KEY_SESSION_TOKEN]))
			$_SESSION[self::$KEY_SESSION_TOKEN] = Tools::CreateUID();
		return $_SESSION[self::$KEY_SESSION_TOKEN];
	}

	static public function Start()
	{
		if(Request::HasGet(session_name()))
			ini_set('session.use_cookies', '0'); //Important!, session.use_only_cookies must be to 0!!!!!
		session_name(\qx\Config::Of('app')->get('session.name','qxs'));
		session_start();
		self::$started = true;
		if(@$_SESSION[self::$KEY_SESSION_HASH] != self::SessionHash())
		{
			session_destroy();
			session_start();
			$_SESSION[self::$KEY_SESSION_HASH] = self::SessionHash();
		}
	}

	static private $started = false;
	static public function Started()
	{
		return self::$started;
	}

	static public function Terminate()
	{
		if(self::Started())
			session_destroy();
	}
	static public function Release()
	{
		if(self::$started)
		{
			session_write_close();
			self::$started = false;
		}
	}
	/**
	 * @depreceated
	 */
	static public function Close()
	{
		self::Release();
	}

	static public function Check()
	{
		self::CheckAutoStart();
		if(isset($_REQUEST[self::$SESSION_TOKEN_FIELD]) && 
			$_REQUEST[self::$SESSION_TOKEN_FIELD] == self::Token())
			return true;
		if(\qx\Request::GetHeader(self::$SESSION_TOKEN_FIELD) == self::Token())
			return true;
		return false;
	}

	static public function Get($key, $default = null)
	{
		self::CheckAutoStart();
		return self::Exists($_SESSION,$key) ? $_SESSION[$key] : $default;
	}

	static public function Exists($owner, $identifier = '')
	{
		self::CheckAutoStart();
		return !empty($_SESSION)?array_key_exists(self::CreateKey($owner,$identifier), $_SESSION):false;
	}

	static private function CreateKey($owner, $identifier = '')
	{
		return (is_object($owner)?get_class($owner):$owner).':'.$identifier;
	}
	
	/**
	 *
	 * @param mixed $owner
	 * @param string $identifier
	 * @return Session
	 */
	static public function Of($owner, $identifier = '')
	{
		return new self($owner,$identifier);
	}

	private $key;
	public function __construct($owner, $identifier = '')
	{
		self::CheckAutoStart();
		$this->key = self::CreateKey($owner,$identifier);
		if(!array_key_exists($this->key,$_SESSION))
			$_SESSION[$this->key] = new \stdClass();
	}

	public function __set($name, $value)
	{
		$_SESSION[$this->key]->$name = $value;
	}

	public function &__get($name)
	{
		return $_SESSION[$this->key]->$name;
	}

	public function __isset($name)
	{
		return isset($_SESSION[$this->key]->$name);
	}
	public function __unset($name)
	{
		unset($_SESSION[$this->key]->$name);
	}
	
	public function destroy()
	{
		unset($_SESSION[$this->key]);
	}
}

