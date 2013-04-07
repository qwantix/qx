<?php
namespace qx;
/**
 * Tiny php template classe
 * @author Brice Dauzats
 */
class PhpTemplate 
{
	
	private $_filename;
	private $_datas;
	private $_host;

	public function __construct($filename, array $datas = array())
	{
		$this->_filename = $filename;
	}

	public function __get($name)
	{
		if(isset($this->_datas[$name]))
			return $this->_datas[$name];
		return null;
	}

	public function __set($name, $value)
	{
		$this->_datas[$name] = $value;
	}

	public function __isset($name)
	{
		return isset($this->_datas[$name]);
    }

    public function __unset($name)
    {
		unset($this->_datas[$name]);
    }

    public function __call($name, $args)
    {
		if($this->_host)
			return call_user_func_array (array($this->_host,$name), $args);
		return null;
    }

    public function setHost($host)
    {
		$this->_host = $host;
    }

	public function getHost()
	{
		return $this->_host;
	}

    public function get($name,$defaultValue = null)
    {
		return isset($this->_datas[$name]) ? $this->_datas[$name] : $defaultValue;
    }
    
	public function render(array $datas = null)
	{
		if(!empty($datas))
			$this->_datas = array_merge($this->_datas,$datas);
		$fn = $this->_filename;
		if(file_exists($fn))
		{
			header('Content-Type: text/html; charset=UTF-8');
			ob_start();
			include $fn;
			return ob_end_clean();
		}
		return "";
	}
}