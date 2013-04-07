<?php
namespace qx\tools;
use \qx;
/**
 * @author Brice Dauzats
 */
class LocaleCompiler 
{
	
	private static $patterns = array();

	public static function RegisterPattern($fileMask, $mask, $index)
	{
		self::$patterns[] = (object)array('fileMask'=>$fileMask,'mask'=>$mask,'index'=>$index);
	}
	
	public function process(array $locales)
	{
		$this->messages = array();
		$this->filenames = array();

		$this->processDir('app');
		$this->processDir('lib');
		$this->processDir(__DIR__.'/..');

		$this->checkSimilarity();
		
		foreach($locales as $l)
			$this->writeFile ($l);
	}
	
	private function processDir($dir)
	{
		foreach(scandir($dir) as $e)
		{
			if($e{0} == '.')
				continue;
			if(is_dir("$dir/$e"))
				$this->processDir("$dir/$e");
			else
				$this->processFile("$dir/$e");
		}
	}

	private $filenames = array();
	private function processFile($filename)
	{
		if(in_array(realpath($filename), $this->filenames))
			return;
		$this->filenames[] = $filename;

		if(!is_readable($filename))
			return;
		
		$content = null;
		
		foreach(self::$patterns as $pattern)
			if(fnmatch($pattern->fileMask,$filename))
			{
				if(!$content)
					$content = file($filename); //Lazy loading content
				foreach($content as $i=>$line)
					if(preg_match_all($pattern->mask, $line, $matches,PREG_SET_ORDER))
						foreach($matches as $m)
							$this->setKey ($m[$pattern->index], $filename,$i+1);
				
			}
		
	}

	private $entries = array();
	public function setKey($key,$file,$line)
	{
		if(!isset($this->entries[$key]))
			$this->entries[$key] = array();
		if(!isset($this->entries[$key][$file]))
			$this->entries[$key][$file] = array();
		$this->entries[$key][$file][] = $line;
	}
	
	private function writeFile($lang)
	{
		$locale = new qx\Locale($lang);
		
		$s = array('<?php // Translation <'.$lang.'>','','');
		$missings = array();
		$writer = function($list) use ($locale, &$missings, &$s)
		{
			foreach($list as $key=>$files)
			{
				$s[] = '/***';
				//$s[] = $key;
				//$s[] = '';
				foreach($files as $f=>$lines)
					$s[] = '@'.$f.'('.implode(', ',$lines).')';
				$s[] = '*/';
				
				$exists = $locale->exists($key);
				$s[] = ($exists?'':'#'). '$t[\''.addcslashes ($key,"'").'\'] = '.
						($exists && strlen($key.$locale->get($key))>70?"\n\t\t":'').'"'.($exists?addcslashes ($locale->get($key),'"'):'').'";';
				$s[] = '';
				if(!$exists)
					$missings[] = "\t - ".$key;
			}
		};

		$vars = array();
		//Extract vars
		foreach ($this->entries as $key => $value) {
			if($key{0} === '@')
			{
				unset($this->entries[$key]);
				$vars[$key] = $value;
			}
			elseif(preg_match('`[\w]_[\w]`', $key))
			{
				$this->messages[]="If '$key' is a var use @ to declare it as var";
			}
		}
		
		$s[] = '/// Variables';
		$s[] = '';
		$writer($vars);
		$s[] = '';
		$s[] = '/// Contents';
		$s[] = '';
		$writer($this->entries);



		if(!empty($missings))
			$this->messages[] = "Missing translations for $lang : \n ".implode ("\n", $missings);
		file_put_contents($locale->filename(), implode("\n",$s));
		
	}
	
	private $messages = array();
	public function getMessages()
	{
		return $this->messages;
	}

	private function checkSimilarity()
	{
		$entries = array();
		$m = array();
		
		foreach(array_keys($this->entries) as $e)
		{
			$l = trim(strtolower($e));
			$entries[$e] = preg_replace(
				array(
					'`\s+`',
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
					'/[\xF1]/',
				),
				array(
					' ',
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
				utf8_decode($l));
		}
		
		foreach($entries as $k1=>$v1)
			foreach($entries as $k2=>$v2)
			{
				
				if($k1 === $k2)
					continue; //is excatly the same
				if($v1 == $v2)
				{
					
					if(!isset($m[$v1]))
						$m[$v1] = array();
					$m[$v1][] = $k1;
				}
			}
		
		
		foreach($m as $keys)
		{
			$s = "WARNING : This contents appears similar :\n";
			foreach($keys as $k)
			{
				$s .= " - < $k > found in \n";
				foreach($this->entries[$k] as $f=>$lines)
					$s .= "\t\t $f(".implode(', ',$lines).')'."\n";
			}
			$this->messages[]=$s;
		}
		
	}

}

LocaleCompiler::RegisterPattern('*.php', '`\b__\(\s*"([^"]+)"\s*[,\)]`',1);
LocaleCompiler::RegisterPattern('*.php', '`\b__\(\s*\'([^\']+)\'\s*[,\)]`',1);