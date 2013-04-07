<?php
namespace qx;
/**
 * @author Brice Dauzats
 */
class Storage extends Observable 
{
	static public function GetDir($path, $createIfNotExists = false, $relativeTo = '')
	{
		$path = $relativeTo.'datas/'.$path;
		if($createIfNotExists && !file_exists($path))
		{
			$p = str_replace('//','/',$path);
			mkdir($p, 0755, true);
			chmod($p,0777);
		}
		if($path[strlen($path)-1] !== '/')
			$path .= '/';
		return $path;
	}
	
	static public function PublicDir($path, $createIfNotExists = false)
	{
		return self::GetDir($path, $createIfNotExists, 'www/');
	}
}