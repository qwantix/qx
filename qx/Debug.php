<?php

namespace qx {
	/**
	 * Description of Config
	 *
	 * @author Brice Dauzats
	 */
	class Debug
	{
		static private $_Instance;
		static public function Instance()
		{
			if(!self::$_Instance)
				self::$_Instance = new self;
			return self::$_Instance;
		}

		private $_queries = array();

		public function logQuery($sql, $args)
		{
			$this->_queries[] = array($sql, $args);
			/*
			echo "<pre>$sql</pre>";
			var_dump($args);
			/**/
		}

		public function getQueries($offset = null, $length = null)
		{
			if($offset !== null)
				return array_slice($this->_queries, $offset, $length);
			return $this->_queries;
		}
		public function getSql($offset = null, $length = null)
		{
			$a = array();
			foreach ($this->getQueries($offset,$length) as $q) {
				list($sql, $args) = $q;
				if($args)
					$sql = str_replace(array_keys($args), array_map(function($v){
						return is_numeric($v) ? $v : '"'.$v.'"';
					},array_values($args)), $sql);
				$a[] = preg_replace_callback('`\b(FROM|INNER JOIN|LEFT JOIN|RIGHT JOIN|WHERE|GROUP BY|ORDER BY|UNION)\b`', function($m){
					return "\n".$m[1];
				}, $sql);
			}
			return count($a) == 1 ? $a[0] : $a;
		}

		public function trace(){
			if(!App::Instance()->isDebug())
				return;
			$args = func_get_args();
			$backtrace = debug_backtrace();
			foreach (debug_backtrace() as $tr) {
				if(isset($tr['file']) && $tr['file'] != __FILE__)
				{
					$prev = $tr;
					break;
				}
			}
			
			$class = basename($prev["file"]);
			$class = str_replace(".class.php","",$class);
			$html = !!ini_get('html_errors');
			if($html)
				echo "<div>$class ( ".$prev["line"]." )</div>";
			else
				echo "\n", $class,"[",$prev["line"],"]: ";
			
			if($html)
				echo "<blockquote>";
			foreach($args as $o)
			{
				if(is_string($o) && strlen($o) > ini_get('xdebug.var_display_max_data'))
					echo $html ? "<pre>$o</pre>" : $o;
				else
					var_dump($o);
			}
			if($html)
				echo "</blockquote>";
		}
	}

	function Dbg()
	{
		return Debug::Instance();
	}
}
namespace {
	//Alias of qx_dbg_trace
	function qx_dump()
	{
		call_user_func_array(array(\qx\Debug::Instance(),'trace'), func_get_args());
	}
	function qx_dbg()
	{
		return \qx\Debug::Instance();
	}
	function qx_dbg_sql()
	{
		return call_user_func_array(array(\qx\Debug::Instance(),'getSql'), func_get_args());
	}
}
