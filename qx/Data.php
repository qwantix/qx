<?php
namespace qx;
/**
 * @author Brice Dauzats
 */
class Data implements \Serializable, \IteratorAggregate, \ArrayAccess
{
	private $datas = array();
	private $value;
	
	public function __construct($datas = null)
	{
		$this->import($datas);
	}

	public function __isset($name)
	{
		return isset($this->datas[$name]);
	}

	public function __get($name)
	{
		return isset($this->datas[$name]) ? $this->datas[$name] : null;
	}

	public function __set($name, $value)
	{
		$this->datas[$name] = $value;
	}

	public function __unset($name)
	{
		unset ($this->datas[$name]);
	}

	public function __invoke($datas = null)
	{
		if(func_num_args() == 0)
			return $this->toObject();
		$this->merge($datas);
		return $this;
	}

	public function __call($name, $arguments)
	{
		if(sizeof($arguments)>0)
		{
			if(is_array($datas) || $datas instanceof \stdClass)
			{
				$this->import ($arguments[0]);
			}
			else
				$this->datas[$name] = $arguments[0];
			return $this;
		}
		return $this->__get($name);
	}

	public function __clone()
	{
		//return clone $this;
	}

	public function merge($datas)
	{
		if($datas instanceof self)
			$this->datas = array_merge ($this->datas, $datas->datas);
		else if((is_array($datas) && Tools::isAssoc($datas)) || $datas instanceof \stdClass)
		{
			$this->datas = array_merge($this->datas,(array)$datas);
		}
		else
			$this->value = $datas;
	}

	public function import($datas)
	{
		$this->merge($datas);
	}

	public function dump()
	{
		var_dump($this->datas);
	}

	public function clear()
	{
		$this->datas = array();
	}

	public function toObject()
	{
		if(empty($this->datas))
			return $this->value;
		$o = new \stdClass();
		foreach($this->datas as $k=>$v)
			$o->$k = $v instanceof self?$v->toObject():$v;
		return $o;
	}
	
	/// ArrayAccess implementation
	
	public function offsetSet($offset, $value) 
	{
		if (is_null($offset))
			$this->datas[] = $value;
		else
			$this->datas[$offset] = $value;
		
	}

	public function offsetExists($offset)
	{
		return isset($this->datas[$offset]);
	}

	public function offsetUnset($offset)
	{
		unset($this->datas[$offset]);
	}

	public function offsetGet($offset)
	{
		return isset($this->datas[$offset]) ? $this->datas[$offset] : null;
	}
	
	
	/// IteratorAgregate implementation
	
	public function getIterator()
	{
		return new ArrayIterator($this->datas);
	}
	
	/// Serializable implementation
	
	public function serialize()
	{
		return serialize($this->toObject());
	}
	
	public function unserialize($data)
	{
		$this->import(unserialize($data));
	}
	
}
