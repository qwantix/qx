<?php
namespace qx;
/**
 * @author Brice Dauzats
 */
class ResponsePart
{
	
	private $_standalone = false;
	public function standalone()
	{
		if(func_num_args() > 0)
		{;
			$this->_standalone = (bool)func_get_arg(0);
			return $this;
		}
		return $this->_standalone;
	}

	private $_encapsulation = null;
	public function encapsulation()
	{
		if(func_num_args() > 0)
		{;
			$this->_encapsulation = (string)func_get_arg(0);
			return $this;
		}
		return $this->_encapsulation;
	}

	private $_standaloneUseParentsDatas = false;
	public function standaloneUseParentsDatas()
	{
		if(func_num_args() > 0)
		{;
			$this->_standaloneUseParentsDatas = (bool)func_get_arg(0);
			return $this;
		}
		return $this->_standaloneUseParentsDatas;
	}
	
	private $_controller;
	public function controller()
	{
		return $this->_controller;
	}

	private $_privateDatas;
	public function privateDatas()
	{
		if(func_num_args() > 0)
		{
			$this->_privateDatas = func_get_arg(0);
			return $this;
		}
		return $this->_privateDatas;
	}

	private $_datas;
	public function datas()
	{
		if(func_num_args() > 0)
		{
			$value = func_get_arg(0);
			if(!($value instanceof Data))
				$value = new Data($value);
			$this->_datas = $value;
			return $this;
		}
		return $this->_datas;
	}

	private $_action;
	public function action()
	{
		if(func_num_args() > 0)
		{
			$this->_action = (string)func_get_arg(0);
			return $this;
		}
		return $this->_action;
	}

	private $_namespace;
	public function ns()
	{
		if(func_num_args() > 0)
		{
			$this->_namespace = (string)func_get_arg(0);
			return $this;
		}
		return $this->_namespace;
	}

	private $_view;
	public function view()
	{
		if(func_num_args() > 0)
		{
			$this->_view = (string)func_get_arg(0);
			return $this;
		}
		return $this->_view ? $this->_view : ($this->_action ? $this->_action : 'default');
	}

	public function createView($type = null, $name = null)
	{
		if(!$name)
			$name = $this->view();
		$view = $this->viewDir() . $name;
		if(!$type)
			$type = $this->type();
		return View::Create($view, $type);
	}

	private $_viewDir;
	public function viewDir()
	{
		if(!$this->_viewDir)
		{
			$conf = Config::Of('app');
			$appNs = $conf->get('namespace').'\\'.$conf->get('controller.namespace');
			$viewNs = $conf->get('view.namespace');

			$viewBase = '';
			if(!($this->_controller instanceof App))
			{
				$viewBase = strtolower(get_class($this->_controller));
				$viewBase = Tools::shiftNamespace($appNs,$viewBase);
				if(!empty ($viewNs))
					$viewBase = Tools::shiftNamespace($viewNs,$viewBase);
				$viewBase = str_replace('\\', DIRECTORY_SEPARATOR, $viewBase) . DIRECTORY_SEPARATOR;
			}
			$this->_viewDir = $viewBase;
		}
		return $this->_viewDir;
	}

	private $_wrapInMain = true;
	public function wrapInMain()
	{
		if(func_num_args() > 0)
		{
			$this->_wrapInMain = (bool)func_get_arg(0);
			return $this;
		}
		return $this->_wrapInMain;
	}
	
	private $_type;
	public function type()
	{
		if(func_num_args() > 0)
		{
			$this->_type = (string)func_get_arg(0);
			return $this;
		}

		if(empty($this->_type))
		{
			$ct = $_SERVER['HTTP_ACCEPT'];
			if (strpos($ct, 'json') !== false)
				$type = 'json';
			elseif (strpos($ct, 'html') !== false ||
					strpos($ct, '*/*') !== false //IE
				)
				$type = 'html';
			elseif (strpos($ct, 'xml') !== false)
				$type = 'xml';

			$this->_type = $type;
		}
		return $this->_type;
	}
	
	private $_header = array();
	public function header($key = null, $value = null)
	{
		$nArgs = func_num_args();
		if($nArgs == 0)
		{
			return $this->_header;
		}
		elseif($nArgs == 1)
		{
			return isset($this->_header[$key]) ? $this->_header[$key] : null;
		}
		else
		{
			$this->_header[$key] = $value;
			return $this;
		}
	}

	public function isError()
	{
		if(func_num_args() > 0)
		{
			$this->header('status',(bool)func_get_arg(0) ? 'error' : 'success');
			return $this;
		}
		return $this->header('status') == 'error';
	}
	public function isSuccess()
	{
		if(func_num_args() > 0)
			return $this->isError(false);
		return !$this->isError();
	}

	public function addError($errMsg, $errDetail = null)
	{
		$this->isError(true);
		$a = $this->header('error');
		if(!$a)
			$a = array();
		$a[] = array($errMsg, $errDetail);
		$this->header('error',$a);
	}
	public function addScript($file)
	{
		if(!isset($this->_datas->__scripts))
			$this->_datas->__scripts = array();
		if(!in_array($file, $this->_datas->__scripts))
			$this->_datas->__scripts = array_merge($this->_datas->__scripts,array($file));

		return $this;
	}

	public function addStyle($file)
	{
		if(!isset($this->_datas->__styles))
			$this->_datas->__styles = array();
		if(!in_array($file, $this->_datas->__styles))
			$this->_datas->__styles = array_merge($this->_datas->__styles, array($file));
		return $this;
	}

	public function __construct(ViewController $ctrl)
	{
		$this->_controller = $ctrl;
		$this->_datas = new Data;
		$this->_privateDatas = new Data;
	}
	
	public function createSubResponse(ViewController $ctrl)
	{
		$r = new self($ctrl);
		$r->_datas = $this->_datas;
		$r->_privateDatas = $this->_privateDatas;
		return $r;
	}
	
	
	public function __set($name, $value)
	{
		$this->_datas->$name = $value;
	}
	public function __get($name)
	{
		return $this->_datas->$name;
	}

}
