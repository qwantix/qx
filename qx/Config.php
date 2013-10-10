<?php
namespace qx;
/**
 * Description of Config
 *
 * @author Brice Dauzats
 */
class Config
{
	static private $_Config = array();
	static private $_ConfigIndex = array();
	
	/**
	 * Get config
	 * @param string $name
	 * @return Config
	 */
	static public function Of($name)
	{
		if(!array_key_exists($name, self::$_ConfigIndex))
			self::$_ConfigIndex[$name] = new Config($name);
		return self::$_ConfigIndex[$name];
	}

	static public function Load($filename, $clear = false)
	{
		if(file_exists($filename))
		{
				$conf = json_decode(file_get_contents($filename),true);
				if(!empty($conf))
					self::$_Config = Tools::merge ($clear?array():self::$_Config,$conf);
			self::$_ConfigIndex = array(); //Reset cache
		}
		else
			throw new \Exception("Missing configuration file $filename");
	}

	private $name;
	private $filename;
	private $conf = array();
	private function __construct($name)
	{
		$this->name = strtolower($name);
		if(!isset(self::$_Config[$name]))
			self::$_Config[$name] = array();
		$this->conf = &self::$_Config[$name];
	}

	/**
	 *	Check if conf exists
	 * @param string $key
	 * @return bool
	 */
	public function exists($key)
	{
		return array_key_exists($key, $this->conf);
	}

	/**
	 * Set data in conf
	 * @param string $key
	 * @param mixed $value
	 */
	public function set($key,$value)
	{
		$this->conf[$key] = $value;
		return $this;
	}
	public function setFromArray(array $assoc)
	{
		$this->conf = array_merge($this->conf,$assoc);
		return $this;
	}
	/**
	 *	Get data from conf
	 * @param string $key
	 * @return mixed
	 */
	public function get($key, $default = null)
	{
		return array_key_exists($key, $this->conf) ? $this->conf[$key] : $default;
	}
	/**
	 * Get all datas
	 * @return stdCls
	 */
	public function getAll()
	{
		return (object)$this->conf;
	}
	/**
	 * Clear conf
	 * @param string $key
	 */
	public function clear($key = null)
	{
		if($key)
			unset($this->conf[$key]);
		$this->conf = array();
	}
	
	public function dump()
	{
		var_dump($this->conf);
	}
}
