<?php
namespace qx;

/**
 * @author Brice Dauzats
 */
class Tools
{
	/// Array
	
	static public function IsAssoc(array $a)
	{
		return array_keys($a) !== range(0, count($a) - 1);
	}
	
	/**
	 * Merge recursivly  
	 */
	static public function Merge(array $a1, array $a2)
	{
		foreach ($a2 as $key => $Value)
		{
			if (array_key_exists($key, $a1) && is_array($Value))
				$a1[$key] = self::merge($a1[$key], $a2[$key]);
			else
				$a1[$key] = $Value;
		}
		return $a1;
	}

	/// Class
	
	/**
	 * Get class name without namespace
	 */
	static public function ClassName($o)
	{
		$toks = explode('\\', is_object($o) ? get_class($o) : $o);
		return $toks[sizeof($toks) - 1];
	}
	
	/**
	 * Remove part of namespace
	 * 
	 */
	static public function ShiftNamespace($ns,$class)
	{
		if(is_object($class))
			$class = get_class($class);
		return preg_replace ('`^'.preg_quote ($ns).'[\\\\]?`i', '', $class);
	}

	/// Url
	
	static public function Redirect($location)
	{
		header('Location:'.$location);
		exit;
	}
	
	//// String

	static public function StripAccent($s)
	{
		return preg_replace(
				array(
					'/[\xC0-\xC6]/',
					'/[\xE0-\xE6]/',
					'/[\xC8-\xCB]/',
					'/[\xE8-\xEB]/',
					'/[\xCC-\xCF]/',
					'/[\xEC-\xEF]/',
					'/[\xD2-\xD6]/',
					'/[\xF2-\xF6]/',
					'/[\xD9-\xDC]/',
					'/[\xF9-\xFC]/',
					'/[\xD1]/',
					'/[\xF1]/'
				),
				array(
					'A',
					'a',
					'E',
					'e',
					'I',
					'i',
					'O',
					'o',
					'U',
					'u',
					'N',
					'n'
				),
				utf8_decode($s));
	}
	
	/// Files
	
	/*static public function CleanFilename($filename)
	{
		return preg_replace('`[^\w0-9_()[]-]+`', '_', $filename);
	}*/
	static public function SanitizeFilename($url, $excludeDir = false)
	{
		$s = trim($url);
		$s = Tools::StripAccent($s);
		$s = preg_replace(
				array(
					'/\s+/',
					'/[^\w-_.]+/'
				),
				array(
					'-',
					''
				),
				$s);
		if($excludeDir)
			$s = str_replace('/', '-',$s);
		return $s;
	}
	
	static public function FormatBytes($size)
	{
		$units = array(' B', ' KB', ' MB', ' GB', ' TB');
		for ($i = 0; $size >= 1024 && $i < 4; $i++)
			$size /= 1024;
		return round($size, 2) . $units[$i];
	}
	
	static public function CreateUID($entropy_size = 8)
	{
		$str = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
		$strlen = strlen($str);
		$out = base_convert(microtime(true),10,36);
		for($i=0;$i<$entropy_size;$i++)
			$out .= $str{mt_rand (0, $strlen-1)};
		return $out;
	}

	/**
	 * Get ext by mime
	 * @param type $mime
	 * @param type $filename
	 * @return string 
	 */
	static public function MimeToExt($mime,$filename = null)
	{
		$icon = array(
			//Documents
			'application/pdf'=>'pdf',
			'text/csv'=>'csv',
			'application/ogg'=>'ogg',
			'application/zip'=>'zip',
			'application/vnd.oasis.opendocument.text'=>'odt',
			'application/vnd.oasis.opendocument.spreadsheet'=>'ods',
			'application/vnd.oasis.opendocument.presentation'=>'odp',
			'application/vnd.oasis.opendocument.graphics'=>'odg',
			'application/vnd.ms-excel'=>'xls',
			'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'=>'xls',
			'application/vnd.ms-powerpoint'=>'ppt',
			'application/msword'=>'doc',
			'application/vnd.openxmlformats-officedocument.wordprocessingml.document'=>'doc',

			//Audio
			'audio/mpeg'=>'mp3',
			'audio/x-ms-wma'=>'wma',
			'audio/x-wav'=>'wav',

			//Video
			'video/mpeg'=>'mpeg',
			'video/mp4'=>'mp4',
			'video/x-ms-wmv'=>'wmv',
			'video/x-msvideo'=>'avi',
			'video/x-flv'=>'flv',

			//Images
			'image/gif'=>'gif',
			'image/jpeg'=>'jpg',
			'image/png'=>'png',
			'image/tiff'=>'tiff',
			'image/vnd.microsoft.icon'=>'ico',
			'image/svg+xml'=>'svg',
			//Text
			'text/plain'=>'txt',
			'text/html'=>'html'
		);
		if(isset($icon[$mime]))
			return $icon[$mime];
		else
			return pathinfo ($filename,PATHINFO_EXTENSION);
	}
}

