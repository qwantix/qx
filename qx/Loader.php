<?php
namespace qx;

/**
 * @author Brice Dauzats
 */
class Loader extends Observable
{
	static private $_paths = array();
	static public function AddPath($path)
	{
		//set_include_path($path.PATH_SEPARATOR.get_include_path());
		if($path{strlen($path-1)} != '/')
			$path .= '/';
		if(!in_array($path, self::$_paths))
			self::$_paths[] = $path;
	}
	
	static public function Load($class)
	{
		$fn = implode(DIRECTORY_SEPARATOR, explode('\\',$class)) . '.php';
		foreach (self::$_paths as $p) {
			if(file_exists($p.$fn))
			{
				require_once $p.$fn;
				return;
			}
		}
		//Otherwise...
		require_once $fn;
	}
	
}

spl_autoload_register('\qx\Loader::Load');


