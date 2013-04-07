<?php
namespace qx\pdo;

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
		{
			$conf = \qx\Config::Of('app');
			self::$_Current = new self($conf->get('db.dsn'),$conf->get('db.username'),$conf->get('db.password')); 
		}
		return self::$_Current;
	}

	private $pdo;
	private $dsn;
	public function __construct($dsn, $username, $password)
	{
		$this->dsn = $dsn;
		$this->pdo = new \PDO($dsn,$username,$password);
		$this->pdo->setAttribute(\PDO::ATTR_ERRMODE,\PDO::ERRMODE_WARNING);
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

	public function exec($sqlOrClause, array $args = null)
	{
		return $this->_exec($sqlOrClause,$args); //Fix pass $args by reference problem
	}
	protected function _exec($sqlOrClause, array &$args = null) 
	{
		$sql = is_string($sqlOrClause) ? $sqlOrClause : $this->build($sqlOrClause, $args);
		//var_dump($sql);
		$sth = $this->pdo->prepare($sql);
		$sth->execute($args);
		//var_dump($args);
		return $sth;
	}
	public function select($sqlOrClause, array $args = null, $class = null) 
	{
		$sth = $this->_exec($sqlOrClause, $args);
		if($class)
		{
			if(is_object($class))
				$class = get_class($class);
			return $sth->fetchAll(\PDO::FETCH_CLASS|\PDO::FETCH_PROPS_LATE, $class);
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
		{
			$clause->where = $this->buildWhereClose($clause->where, $args);
		}
		//$w = !empty($clause->where) ? is_array($clause->where) ? '('.implode(') AND (',$clause->where).')' : $clause->where : '';
		if(!empty($clause->where))
			$sql .= ' WHERE '.$clause->where;
		if(!empty($clause->groupBy))
			$sql .= ' GROUP BY '.$clause->groupBy;
		if(!empty($clause->having))
			$sql .= ' HAVING '.$clause->having;
		if(!empty($clause->orderBy))
			$sql .= ' ORDER BY '.(is_array($clause->orderBy) ? implode(', ',$clause->orderBy) : $clause->orderBy);
		
		if(!empty($clause->limit))
			$sql .= ' LIMIT '.(is_array($clause->limit) ? implode(', ',$clause->limit) : $clause->limit);
		//echo ($sql."\n");
		
		return $sql;
	}

	private function buildWhereClose($w, &$args, &$n = 0, $depth = 0)
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
						$a[] = $key . ' IN('.implode(',',array_map('intval',$value)).')';
					}
					else
					{
						$argName = ':where_args_'.$n++;
						$a[] = "`$key` = ".$argName;
						$args[$argName] = $value;
					}
				}
				$w = $a;
			}
			else
			{
				$a = array();
				foreach($w as $c)
					$a[] = $this->buildWhereClose($c, $args, $n, $depth+1);
				$w = $a;
			}
			//var_dump($clause->where);
			$w = '('.implode(') AND (',$w).')';
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
			'limit'=>array()
		);
	}

	public function mergeClauses($c1,$c2, array $overrides = array())
	{
		$c1 = (object)$c1;
		$c2 = (object)$c2;

		$c = $this->createClause($c1->from);
		
		foreach(array('select','join','where','groupBy','orderBy') as $part)
		{
			if(in_array($part,$overrides) && isset($c2->$part))
				$c->$part = $c2->$part;
			else if(isset($c1->$part) && isset($c2->$part))
			{
				if(!is_array($c1->$part))
					$c1->$part = array($c1->$part);
				if(!is_array($c2->$part))
					$c2->$part = array($c2->$part);
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

	public function rebuildTree($table, $field = 'parent_id')
	{
		$this->_rebuildTree($table, $field);
	}

	private function _rebuildTree($table, $field, $id = null, $ft = 0)
	{
		$gt = $ft + 1;
		$sth = $this->_exec("SELECT id FROM `$table` WHERE $field ".($id?'= '.$id:'IS NULL'));
		$ids = $sth->fetchAll(\PDO::FETCH_OBJ);
		foreach ($ids as $r)
			$gt = $this->_rebuildTree($table, $field, $r->id, $gt);
		
		
		$this->exec("UPDATE `$table` SET lft = ?, rgt = ? WHERE id = ?", array($ft,$gt,$id));
		return $gt + 1;
	}
}

