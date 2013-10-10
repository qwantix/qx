<?php
namespace qx;
/**
 * @author Brice Dauzats
 */

class Cache {
	
	static private $_caches = array();
	static public function Of($name)
	{
		if(!isset(self::$_caches[$name]))
			 self::$_caches[$name] = new self($name);
		return self::$_caches[$name];
	}

	private $of;
	public function __construct($of)
	{
		$this->of = $of;
	}

	private $_fnCache = array();
	private $_dataCache = array();
	private function getFilename($name) {
		if(!isset($this->_fnCache[$name]))
			$this->_fnCache[$name] = Storage::GetDir('cache/'.$this->of,true).Tools::SanitizeFilename($name,true);
		return $this->_fnCache[$name];
	}
	
	public function set($name, $value)
	{
		$data = serialize($value);
		$fn = $this->getFilename($name);
		$this->_dataCache[$fn] = $value;
		return file_put_contents($fn, $data);
	}

	public function exists($name)
	{
		$fn = $this->getFilename($name);
		return isset($this->_dataCache[$fn]) || file_exists($fn);
	}

	public function get($name)
	{
		if($this->exists($name))
		{
			$fn = $this->getFilename($name);
			if(isset($this->_dataCache[$fn]))
			{
				$d = $this->_dataCache[$fn];
			}
			else
			{
				$d = file_get_contents($this->getFilename($name));
				if($d){
					$d = unserialize($d);
				}
			}
			return $d;
		}
		return null;
	}

	public function clear($name = null)
	{
		//TODO
	}
}