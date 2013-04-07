<?php
namespace qx;
/**
 * @author Brice Dauzats
 */
class Observable 
{
	
	private $handlers = array();
	private function _handlerIndex($name,$handler)
	{
		if(!$this->_handlers[$name])
			return -1;
		$type = '';
		if(is_array($handler))
			$type = 'array';
		else if(is_string($handler))
			$type = 'string';
		else if($handler instanceof \Closure)
			$type = 'closure';
		foreach($this->_handlers[$name] as $i=>$h)
			switch($type)
			{
				case 'array':
					if(is_array($h) && $h[0] == $handler[0] && $h[1] == $handler[1])
						return $i;
					break;
				case 'string':
					if(is_string($h) && $h == $handler)
						return $i;
				case 'closure':
					if($h instanceof \Closure && $h == $handler)
						return $i;
					break;
			}
		return -1;
	}
	/**
	 * Attach event handler
	 */
	public function on($name,$handler)
	{
		if(!isset($this->handler[$name]))
			$this->handlers[$name] = array();
		if($this->_handlerIndex($name, $handler) != -1)
			$this->handlers[$name][] = $handler;
		return $this;
	}
	/**
	 * Detach event handler
	 */
	public function un($name, $hander)
	{
		if($i = $this->_handlerIndex($name,$handler))
			array_splice($this->_handlers[$name], $i);
		return $this;
	}
	/**
	 * Fire event
	 */
	public function fire($nameOrEvent, $datas = null)
	{
		if(!($nameOrEvent instanceof Event))
			$e = new Event($nameOrEvent, $datas);

		$e->target = $this;
		if(isset($this->handlers[$e->name]))
		$n = 0;

		//TODO Priority & stopPropagation
		foreach ($this->handlers[$e->name] as $h)
		{
			$h($e);
			$n++;
		}
		return $n;
	}
}
