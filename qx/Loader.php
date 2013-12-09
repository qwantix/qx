<?php
namespace qx;

/**
 * @author Brice Dauzats
 */
class Loader extends Observable
{
	static private 
			$_paths = array(),
			$_loaders = array()
	;
	static public function AddPath($path)
	{
		if($path{strlen($path-1)} != '/')
			$path .= '/';
		if(!in_array($path, self::$_paths))
			self::$_paths[] = $path;
	}
	static public function AddLoader($handler)
	{
		self::$_loaders[] = $handler;
	}
	static public function Load($class)
	{
		$fn = implode(DIRECTORY_SEPARATOR, preg_split('`[\\\\_]`',$class)) . '.php';
		foreach (self::$_paths as $p) {
			if(file_exists($p.$fn))
			{
				require_once $p.$fn;
				return;
			}
		}
		foreach (self::$_loaders as $ldr)
			if(call_user_func($ldr, $class))
				return true;

		//Otherwise...
		require_once $fn;
	}
	
}

spl_autoload_register('\qx\Loader::Load');


