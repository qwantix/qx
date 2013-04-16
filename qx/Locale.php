<?php
namespace qx {
	/**
	 * @author Brice Dauzats
	 */
	class Locale 
	{
		
		static private $current;
		static public function Current()
		{
			if(!self::$current)
			{
				$langs = Request::GetHeader('Accept-Language',true);

				$setLang = function($lang){
					$ol = new Locale($lang,false);
					if(file_exists($ol->filename()))
					{
						$ol->load();
						return $ol;
					}
					return null;
				};

				foreach($langs as $l)
					if(self::$current = $setLang($l[0]))
						break;
				
				if(!self::$current)
				{
					//Safari use only long code with lang-country
					//so, strip country
					foreach($langs as $l)
					{
						list($l) = explode('-',$l[0]);
						if(self::$current = $setLang($l))
							break;
					}
				}

				if(!self::$current)
					self::$current = new self('');
			}
			return self::$current;
		}

		static public function SetLocale($lang)
		{
			self::$current = new self($lang);
		}
		
		private $lang;
		private $entries = array();
		public function __construct($lang, $autoLoad = true)
		{
			$this->lang = $lang;
			if($autoLoad)
				$this->load();
		}

		public function load()
		{
			$this->entries = array();
			if(file_exists($this->filename()))
			{
				$t = array();
				include $this->filename();
				$t2 = array();
				foreach($t as $k=>$v)
					$t2[stripcslashes ($k)] = $v;
				
				$this->entries = $t2;
				
			}
		}

		public function filename()
		{
			return "app/locales/$this->lang.php";
		}

		public function get($key)
		{
			return isset($this->entries[$key]) ? $this->entries[$key] : $key;
		}

		public function exists($key)
		{
			return isset($this->entries[$key]);
		}

		public function getLang()
		{
			return $this->lang;
		}
	}
}
namespace {

	function __($value, $args = null) 
	{
		$s = \qx\Locale::Current()->get($value);
		if(func_num_args()>1)
		{
			$args = func_get_args();
			$args[0] = $s;
			$s = call_user_func_array ('sprintf', $args);
		}		
		return $s;
		
	}

}