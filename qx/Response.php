<?php
namespace qx;
/**
 * @author Brice Dauzats
 */
class Response extends Observable
{
	private $_parts = array();
	
	private $_app;
	public function __construct(App $app)
	{
		$this->_app = $app;
	}
	
	private $_data;
	public function data()
	{
		if(!$this->_data)
			$this->_data = new Data();
		return $this->_data;
	}

	public function generate()
	{
		$data = $this->data();

		$conf = Config::Of('app');

		$data->ConfigApp = $conf;
		$data->root = $conf->get('wwwroot');
		$data->session_token = Session::Token();
		$data->lang =  Locale::Current()->getLang();

		$defaultDataNs = 'innerContent';

		//$appNs = $conf->get('namespace').'\\'.$conf->get('controller.namespace');
		//$viewNs = $conf->get('view.namespace');
		$viewMainName = $conf->get('view.mainName');

		$out = '';

		foreach($this->_parts as $depth => $part)
		{
			$ns = $part->ns();
			$ctrl = $part->controller();
			if(empty ($ns))
				$ns = $defaultDataNs;
			
			$standalone = $part->standalone();

			if($standalone && !$part->standaloneUseParentsDatas())
				$data = $part->datas();
			else
				$data->merge($part->datas());

			try
			{
				$data->$ns = $out = $part->createView()
										->render($data,$ctrl);
				//Remerge for template modifiction
				$this->mergeInclusions($part->datas(), $data);
			}
			catch(\qx\ViewNotFoundException $e) 
			{

			}

			
			if($part->wrapInMain())
			{
				try
				{
					$out = View::Create($part->viewDir().$viewMainName, $part->type())->render($data,$ctrl);
					$data->$ns = $out;
					$this->mergeInclusions($part->datas(), $data);
				}
				catch(\qx\ViewNotFoundException $e) {}
			}

			if($standalone)
				break;
		}

		if($enc = $part->encapsulation())
			$out = $part->type($enc)->createView()->render(new Data($out),$part->controller());
		return $out;
	}

	private function mergeInclusions($source, $dest)
	{
		if(is_array($source->__scripts))
		{
			if(!is_array($dest->__scripts))
				$dest->__scripts = array();

			$a = $dest->__scripts;
			foreach ($source->__scripts as $v)
				if(!in_array($v, $a))
					$a[] = $v;
			$dest->__scripts = $source->__scripts = $a;
		}
		else
			$source->__scripts = $dest->__scripts;
		if(is_array($source->__styles))
		{
			if(!is_array($dest->__styles))
				$dest->__styles = array();
			$a = $dest->__styles;
			foreach ($source->__styles as $v)
				if(!in_array($v, $a))
					$a[] = $v;
			$dest->__styles = $source->__styles = $a;
		}
		else
			$source->__styles = $dest->__styles;
		return $dest;
	}
	
	public function append(ResponsePart $part)
	{
		$this->_parts[] = $part;
	}
	
	
}
