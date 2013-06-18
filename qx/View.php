<?php
namespace qx;
/**
 *
 * @author Brice Dauzats
 */
abstract class View {
	
	static private $_Types = array(
		'html'=>'\\qx\\ViewHtml',
		'json'=>'\\qx\\ViewJson',
		'xml'=>'\\qx\\ViewXml'
	);
	static public function Register($type, $handler)
	{
		self::$_Types[$type] = $handler;
	}
	
	static public function Create($name, $type)
	{
		if(!isset(self::$_Types[$type]))
			throw new Exception('Unknow view type '.$type);
		$cls = self::$_Types[$type];
		return new $cls($name);
	}
	
	
	////////////////
	
	protected $name;
	private $rootViewDir;
	protected function __construct($name)
	{
		
		if($name{0} === '@')
		{
			$name = preg_replace('/^@/','', $name);
			$this->rootViewDir = '.';
		}
		else
			$this->rootViewDir = Config::Of('app')->get('root').
								DIRECTORY_SEPARATOR.
								Config::Of('app')->get('views');
		$this->name = $name;
	}

	protected $controller;
	public function setViewController(ViewController $ctrl = null)
	{
		$this->controller = $ctrl;
	}

	public function getViewController()
	{
		return $this->controller;
	}
	
	public function exists()
	{
		return file_exists($this->filename());
	}

	public function getRootViewDir()
	{
		return $this->rootViewDir;
	}
	public function setRootViewDir($dir)
	{
		$this->rootViewDir = $dir;
	}
	public function filename($ext = '.html')
	{
		return $this->rootViewDir.
			DIRECTORY_SEPARATOR.
			"$this->name$ext";
	}
	
	abstract public function render(Data $datas, ViewController $ctrl = null);

}

class ViewHtml extends View
{
	public function render(Data $datas, ViewController $ctrl = null)
	{
		$ctrl = $ctrl ? $ctrl : $this->getViewController();
		$fn = $this->filename();

		$tpl = new PhpTemplate($fn,$datas);
		$this->tpl->setHost($ctrl);
		
		if($this->exists())
		{
			header('Content-Type: text/html; charset=UTF-8');
			ob_start();
			{
				$D = $datas; //Shorthand
				include $fn;
			}
			return ob_end_clean();
		}
		return "";
	}
}
class ViewJson extends View
{

	public function exists()
	{
		return true;
	}
	public function render(Data $datas, ViewController $ctrl = null)
	{
		$ctrl = $ctrl ? $ctrl : $this->getViewController();
		$r = $ctrl->response();
		$r->header('scripts',$r->__scripts);
		$r->header('styles',$r->__styles);
		$r->header('location', $_SERVER['REQUEST_URI']);
		//header('Content-Type: application/json; charset=UTF-8');
		$res = array(
			'header'=>$ctrl->app()->mainResponse()->headers(),
			'body'=>$datas->toObject()
		);
		//$res = $this->toUft8($res);
		return json_encode($res);
	}

	protected function toUft8($data)
	{
		if(is_string($data))
			return \qx\Tools::Utf8Encode($data);
		elseif(is_object($data))
			foreach ($data as $key => $value)
				$data->$key = $this->toUft8($value);
		elseif(is_array($data))
			foreach ($data as $key => $value)
				$data[$key] = $this->toUft8($value);
		return $data;
			
	}
}

class ViewXml extends View
{
	public function exists()
	{
		return true;
	}

	public function render(Data $datas, ViewController $ctrl = null)
	{
		$ctrl = $ctrl ? $ctrl : $this->getViewController();
		
		header('Content-Type: text/xml; charset=UTF-8');
		return xmlrpc_encode($datas->toObject());
	}
}


/////
