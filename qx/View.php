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
	protected function __construct($name)
	{
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
	

	protected function filename($ext = '.html')
	{
		return Config::Of('app')->get('root').
			DIRECTORY_SEPARATOR.
			Config::Of('app')->get('views').
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

		$tpl = new PhpTemplate($this->filename(),$datas);
		$this->tpl->setHost($ctrl);
		$fn = $this->filename();
		if(file_exists($fn))
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
	public function render(Data $datas, ViewController $ctrl = null)
	{
		$ctrl = $ctrl ? $ctrl : $this->getViewController();
		$r = $ctrl->response();
		$r->header('scripts',$r->__scripts);
		$r->header('styles',$r->__styles);
		$r->header('location', $_SERVER['REQUEST_URI']);
		//header('Content-Type: application/json; charset=UTF-8');
		$res = array(
			'header'=>$r->header(),
			'body'=>$datas->toObject()
		);
		return json_encode($res);
	}
}

class ViewXml extends View
{
	public function render(Data $datas, ViewController $ctrl = null)
	{
		$ctrl = $ctrl ? $ctrl : $this->getViewController();
		
		header('Content-Type: text/xml; charset=UTF-8');
		return xmlrpc_encode($datas->toObject());
	}
}


/////
