<?php
namespace qx;
/**
 * @author Brice Dauzats
 */
class Request
{

	static public function Get($name,$default = null, $setDefaultIfEmpty = false)
	{
		return array_key_exists($name, $_GET) ? 
			!$setDefaultIfEmpty || ($setDefaultIfEmpty && $_GET[$name] != '')  ? $_GET[$name] : $default : $default;
	}

	static public function Post($name,$default = null, $setDefaultIfEmpty = false)
	{
		return array_key_exists($name, $_POST) ? 
			!$setDefaultIfEmpty || ($setDefaultIfEmpty && $_POST[$name] != '')  ? $_POST[$name] : $default : $default;
	}

	static public function File($name, $setDefaultIfEmpty = false)
	{
		return array_key_exists($name, $_FILES) ? $_FILES[$name] : false;
	}

	static public function HasGet($key = null)
	{
		return $key !== null?array_key_exists($key,$_GET):!empty($_GET);
	}

	static public function HasPost($key = null)
	{
		return $key !== null?array_key_exists($key,$_POST):!empty($_POST);
	}

	static public function HasFile($key = null)
	{
		return $key !== null?array_key_exists($key,$_FILES):!empty($_FILES);
	}

	static private $_Raw;
	static public function Raw()
	{
		if(!self::$_Raw)
			self::$_Raw = file_get_contents("php://input");
		return self::$_Raw;
	}

	static public function PostJson()
	{
		return json_decode(self::Raw());
	}

	static public function PostXml()
	{
		return simplexml_load_string(self::Raw());
	}

	static public function IsValid($redirectOnError = false)
	{
		if(Session::Token() === @$_REQUEST['token'])
			return true;
		if($redirectOnError)
			Url::Redirect(Url::Build(Config::Of('app')->get('csrf_error_route','')));
		return false;
	}

	static public function GetHeader($name, $parse = false)
	{
		$name = 'HTTP_'.str_replace('-','_' , strtoupper($name));
		$value = isset($_SERVER[$name])?$_SERVER[$name]:'';
		if(!$parse)
			return $value;
		$out = array();
		foreach(explode(',',$value) as $tok)
		{
			$a = explode(';',$tok);
			$out[] = $a;
		}
		return $out;
	}
}
