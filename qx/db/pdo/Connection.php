<?php
namespace qx\db\pdo;

/**
 *
 * @author Brice Dauzats
 */
class Connection {
	/**
	 *
	 * @var PDO 
	 */
	static private $_Current;
	static public function Current()
	{
		if(!self::$_Current)
			self::$_Current = self::Get('');
		return self::$_Current;
	}

	static private $_Connections = array();
	static public function Get($name)
	{
		if(!isset(self::$_Connections[$name]))
		{
			$conf = \qx\Config::Of('app');
			$key = !empty($name) ? '.'.$name : '';
			self::$_Connections[$name] = new self($conf->get("db$key.dsn"),$conf->get("db$key.username"),$conf->get("db$key.password")); 
		}
		return self::$_Connections[$name];
	}


	private $pdo;
	private $dsn;
	private $debug;
	private $lastQuery;
	public function __construct($dsn, $username, $password)
	{
		$this->dsn = $dsn;
		$this->pdo = new \PDO($dsn,$username,$password, array(\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'UTF8\''));
		$this->pdo->setAttribute(\PDO::ATTR_ERRMODE,\PDO::ERRMODE_EXCEPTION);

		$this->debug = !!\qx\Config::Of('app')->get('debug');
	}

	public function pdo()
	{
		return $this->pdo;
	}
	public function db()
	{
		preg_match('`\bdbname=(\w+)\b`', $this->dsn, $m);
		return $m[1];
	}
	public function startTransaction()
	{
		$this->pdo->beginTransaction();
	}
	public function commit()
	{
		$this->pdo->commit();
	}
	public function rollback()
	{
		$this->pdo->rollback();
	}
	public function inTransaction()
	{
		return $this->pdo->inTransaction();
	}
	public function getLastQuery($compiled = false)
	{
		if($compiled)
		{
			list($s,$a) = $this->lastQuery;
			//Format
			$s = $this->formatSql($s);
			if(!empty($a))
				return str_replace(array_keys($a), array_map(function($v){
					return is_numeric($v) ? $v : '"'.$v.'"';
				},array_values($a)), $s);
			return $s;

		}
		return $this->lastQuery;
	}
	protected function formatSql($sql)
	{
		return preg_replace_callback('`\b(FROM|INNER JOIN|LEFT JOIN|RIGHT JOIN|WHERE|GROUP BY|ORDER BY|UNION)\b`', function($m){
			return "\n".$m[1];
		}, $sql);
	}
	public function exec($sqlOrClause, array $args = null)
	{
		return $this->_exec($sqlOrClause,$args); //Fix pass $args by reference problem
	}
	protected function _exec($sqlOrClause, array &$args = null) 
	{
		$sql = is_string($sqlOrClause) ? $sqlOrClause : $this->build($sqlOrClause, $args);
		if($args)
			$args = array_map(function($value){
				if($value instanceof \DateTime)
					$value = $value->format('Y-m-d H:i:s');
				return $value;
			}, $args);
		$this->lastQuery = array($sql,$args);
		if($this->debug)
			\qx\Debug::Instance()->logQuery($sql,$args);
		$sth = $this->pdo->prepare($sql);
		try {
			$sth->execute($args);
		} catch (\Exception $e) {
			throw new \qx\DbException($e, $this->formatSql($sql), $args);
		}
		return $sth;
	}
	public function select($sqlOrClause, array $args = null, $class = null) 
	{
		$sth = $this->_exec($sqlOrClause, $args);
		if($class)
		{
			if(is_object($class))
				$class = get_class($class);
			return $sth->fetchAll(\PDO::FETCH_CLASS|\PDO::FETCH_PROPS_LATE, $class, array());
		}
		else
			return $sth->fetchAll(\PDO::FETCH_OBJ);
	}
	public function selectOne($sqlOrClause, array $args = null, $class = null) 
	{
		$sth = $this->_exec($sqlOrClause, $args);
		if($class)
		{
			if(is_object($class))
				$class = get_class($class);
			return $sth->fetchObject($class);
		}
		else
			return $sth->fetchObject();
	}
	public function insert($table, array $fields, array $values = null, $updateOnDuplicate = false)
	{
		if($values === null)
		{
			$f = array(); $values = array();
			foreach($fields as $k=>$v)
			{
				$f[] = "`$k`";
				$values[] = $v;
			}
			$fields = $f;
		}
		$s = 'INSERT INTO '.$this->table($table).' ('.implode(',',$fields).') VALUES ';
		if(!is_array($values))
			$values = array($values);
		$v = array();
		$args = array();
		foreach($values as $set)
		{
			$args[] = $set;
			$v[] = '?';// "(" . implode("," , '?') . ")";
		}
		$s .= '('.implode(',',$v).')';
		if($updateOnDuplicate)
		{
			if($updateOnDuplicate === true)
				$updateOnDuplicate = $fields;
			$s .= ' ON DUPLICATE KEY UPDATE '; //XXX Mysql only
			$a = array();
			foreach ($updateOnDuplicate as $f)
				$a[] = "`$f`=VALUES($f)";
			$s .= implode(', ', $a);
		}
		return $this->_exec($s,$args);
	}
	
	public function update($table, $fields, $where = '', $args = array())
	{
		$s = 'UPDATE '.$this->table($table).' SET ';
		$set = array();
		$n = 0;
		foreach($fields as $k=>$v)
		{
			$argName = ':set_value_'.$n++;
			$args[$argName] = $v;
			$set[] = "`$k`  = ".$argName;
		}
		$s .= implode(', ',$set);
		if(!empty($where))
			$s .= ' WHERE '.$this->buildWhereClose($where, $args);
		
		return $this->_exec($s, $args);
	}
	public function delete($table,$where = '', $args = array())
	{
		$s = 'DELETE FROM '.$this->table($table);
		if(!empty($where))
			$s .= ' WHERE '.$this->buildWhereClose($where, $args);
		return $this->_exec($s, $args);
	}
	public function table($name, $alias = null)
	{
		return '`' . $name . '`'.($alias?' '.$alias:'');
	}

	public function removeTable($name) 
	{
		$this->_exec('DROP TABLE IF EXISTS ' . $this->table($name));
	}
	public function removeTables(array $names) 
	{
		foreach($names as $v)
			$this->removeTable ($v);
	}
	public function createTable($name, array $fields) 
	{
		$this->removeTable($name);
		$s = 'CREATE TABLE ' . $this->table($name) . ' (';
		$f = array();
		$p = array();
		$i = array();
		foreach ($fields as $k => $v) {
			if (is_string($v))
				$v = array($v);
			$f[] = "`$k` " . $v[0] . (stripos($v[0],'varchar') !== false ? ' CHARACTER SET utf8 COLLATE utf8_general_ci':'');
			if (count($v) > 1) {
				if ($v[1] & 1)
					$p[] = "`$k`";
				if ($v[1] & 2)
					$i[] = "`$k`";
			}
		}
		$s .= implode(',', $f);
		if (!empty($p))
			$s .= ', PRIMARY KEY (' . implode(',', $p) . ')';
		if (!empty($i)) {
				$ii = array();
				foreach($i as $v)
					$ii[] = "INDEX $v ($v)";
				$s .= ', '.implode(',',$ii);
			}
			$s .= ') ';
			
		$this->_exec($s);
		
	}

	public function build($clause, &$args = null)
	{
		$clause = (object)$clause;
		if(empty($clause->select))
			$clause->select = '*';
		$select = is_array($clause->select) ? implode(',',$clause->select) : $clause->select;

		$sql = 'SELECT ';
		if(isset($clause->distinct) && $clause->distinct == true)
			$sql .= 'DISTINCT ';
		$sql .= empty($select) ? '*' : $select;
		$sql .= ' FROM ';
		if(!empty($clause->union))
		{
			$union = array();
			foreach ($clause->union as $cl)
				$union[] = $this->build($cl, $args);

			$sql .= '((' . implode (') UNION (', $union) . ')) as tmp';
		}
		else if(is_array($clause->from) || is_object($clause->from))
		{
			//Sub query
			$sql .= '(' . $this->build($clause->from, $args) . ') as tmp';
		}
		else
			$sql .= $clause->from;
		if(!empty($clause->join)) {
			$joins = array();
			if(is_string($clause->join))
				$clause->join = array($clause->join);
			foreach($clause->join as $j)
				if(is_array($j))
				{
					if(count($j) == 2)
						$j = array('INNER',$j[0],$j[1] );
					$joins[] = $j[0] . (stripos($j[0],'JOIN') === false ? ' JOIN' : '').' '.$j[1].' ON ('.$j[2].')';
				}
				else if(!preg_match('`(INNER|LEFT|RIGHT)\s+JOIN`i', $j))
					$joins[] = 'JOIN '.$j;
				else
					$joins[] = $j;
				
			$sql .= !empty($joins) ? ' ' . implode(' ',$joins) : '';
		}
		$w = '';
		if(!empty($clause->where))
			$clause->where = $this->buildWhereClose($clause->where, $args, $n = 0);
		if(!empty($clause->having))
			$clause->having = $this->buildWhereClose($clause->having, $args, $n);

		//$w = !empty($clause->where) ? is_array($clause->where) ? '('.implode(') AND (',$clause->where).')' : $clause->where : '';
		if(!empty($clause->where))
			$sql .= ' WHERE '.$clause->where;
		if(!empty($clause->groupBy))
			$sql .= ' GROUP BY '.(is_array($clause->groupBy) ? implode(', ',$clause->groupBy) : $clause->groupBy);
		if(!empty($clause->having))
			$sql .= ' HAVING '.$clause->having;
		if(!empty($clause->orderBy))
			$sql .= ' ORDER BY '.(is_array($clause->orderBy) ? implode(', ',$clause->orderBy) : $clause->orderBy);
		
		if(!empty($clause->limit))
			$sql .= ' LIMIT '.(is_array($clause->limit) ? implode(', ',$clause->limit) : $clause->limit);
		//echo ($sql."\n");
		
		return $sql;
	}

	public function buildWhereClose($w, &$args, &$n = 0, $depth = 0)
	{
		if(is_array($w))
		{
			if(\qx\Tools::IsAssoc($w))
			{
				if(!$args)
					$args = array();
				$a = array();
				foreach ($w as $key => $value)
				{
					if(is_array($value))
					{
						$in_values = array();
						$argName = ':where_args_'.$n++.'_in_';
						$hasNullValue = false;
						foreach ($value as $i=>$v) {
							if($v === null)
							{
								$hasNullValue = true;
								continue;
							}
							$args[$argName.$i] = $v;
							$in_values[] = $argName.$i;
						}
						$or = array();
						if($hasNullValue)
							$or[] = "$key IS NULL";
						if(!empty($in_values))
							$or[] = $key . ' IN('.implode(',',$in_values).')';
						if(!empty($or))
							$a[] = '('.implode(' OR ', $or).')';
					}
					else if($value === null)
					{
						$a[] = $key . ' IS NULL';
					}
					else
					{
						$argName = ':where_args_'.$n++;
						$a[] = (strpos($key, '`')!==false || strpos($key, '.')!==false?$key:"`$key`")." = ".$argName;
						$args[$argName] = $value;
					}
				}
				$w = $a;
			}
			else
			{
				$a = array();
				foreach($w as $c)
				{
					$ww = $this->buildWhereClose($c, $args, $n, $depth+1);
					if($ww)
						$a[] = $ww;
				}
				$w = $a;
				
			}
			$w = !empty($w) ?  count($w)>0 ? '('.implode(') AND (',$w).')' : $w : '';
		}
		if($depth == 0)
			$w = !empty($w) ? is_array($w) ? '('.implode(') AND (',$w).')' : $w : '';
		return $w;
	}

	public function createClause($table = '')
	{
		return (object)array(
			'select'=>array(),
			'from'=>$table,
			'join'=>array(),
			'where'=>array(),
			'groupBy'=>array(),
			'orderBy'=>array(),
			'having'=>array(),
			'limit'=>array()
		);
	}

	
	public function mergeClauses($c1,$c2, array $overrides = array())
	{
		$c1 = (object)$c1;
		$c2 = (object)$c2;

		$c = $this->createClause(isset($c1->from)?$c1->from:'');
		
		foreach(array('select','join','where','groupBy','having','orderBy','limit','distinct') as $part)
		{
			if(in_array($part,$overrides) && isset($c2->$part))
				$c->$part = $c2->$part;
			else if(isset($c1->$part) && isset($c2->$part))
			{
				if(!is_array($c1->$part))
					$c1->$part = array($c1->$part);
				if(!is_array($c2->$part))
					$c2->$part = array($c2->$part);
				if($part == 'where' || $part == 'having')
					$c->$part = array($c1->$part, $c2->$part);
				else
					$c->$part = array_merge($c1->$part,$c2->$part);
			}
			else if(isset($c1->$part))
				$c->$part = $c1->$part;
			else if(isset($c2->$part))
				$c->$part = $c2->$part;
		}
		
		return $c;
	}

	public function encodeDateTime($date)
	{
		if(!is_numeric($date))
		{
			list($date,$time) = explode(' ', $date);
			list($d,$m,$y) = explode('/',$date);
			list($h,$i,$s) = explode(':',$time);
			$date = mktime($h,$i,$s,$m,$d,$y);
		}
		return date('Y-m-d H:i:s',$date);
	}
	
	public function encodeDate($date)
	{
		if(!is_numeric($date) && strpos($date,'-') === false)
		{
			list($d,$m,$y) = explode('/',$date);
			$date = mktime(0,0,0,$m,$d,$y);
		}
		return date('Y-m-d',$date);
	}

	public function decodeDate($date)
	{
		if(empty($date))
			return null;
		if(is_numeric($date))
			return $date;
		$date = explode(' ',$date);
		list($y,$m,$d) = explode('-',$date[0]);
		if(count($date)>1)
			list($h,$i,$s) = explode(':',$date[1]);
		else $h = $i = $s = 0;
		return mktime($h,$i,$s,$m,$d,$y);
	}

	public function rebuildTree($table, $field = 'parent_id', $sort = null, $groupBy = null)
	{
		if($groupBy)
		{
			if(!is_array($groupBy))
				$groupBy = array($groupBy);
			foreach($this->select("SELECT `".implode('`,`',$groupBy)."` FROM `$table` GROUP BY `".implode('`,`',$groupBy).'`') as $r)
			{
				$cond = array();
				foreach ($groupBy as $f)
					$cond[$f] = $r->$f;
				$this->_rebuildTree($table, $field, $sort, $cond);
			}
		}
		else
			$this->_rebuildTree($table, $field, $sort);
	}

	private function _rebuildTree($table, $field, $sort = null, $cond = null,  $id = null, $ft = 0)
	{
		$gt = $ft + 1;
		$args = array();
		$q = "SELECT id FROM `$table` WHERE $field ";
		if($id)
		{
			$q .= '= ?';
			$args[] = $id;
		}
		else
			$q .= 'IS NULL';

		if($cond)
			foreach ($cond as $key => $value)
			{
				$q .= " AND `$key` = ? ";
				$args[] = $value;
			}
		if($sort)
			$q .= ' ORDER BY '.$sort;
		$ids = $this->select($q,$args);
		foreach ($ids as $r)
			$gt = $this->_rebuildTree($table, $field, $sort, $cond, $r->id, $gt);
		
		
		$this->exec("UPDATE `$table` SET lft = ?, rgt = ? WHERE id = ?", array($ft,$gt,$id));
		return $gt + 1;
	}


	static public function IsClause($q)
	{
		return empty($q) 
			|| isset($q['where']) 
			|| isset($q['from']) 
			|| isset($q['select']) 
			|| isset($q['join'])
			|| isset($q['limit'])
			|| isset($q['groupBy'])
			|| isset($q['orderBy'])
			|| isset($q['having'])
			|| isset($q['union'])
		;
	}
}

