<?php
namespace qx\db\pdo;

/**
 * @author Brice Dauzats
 */
class ObjectModel extends \qx\db\ObjectModel
{
	protected $connection;
	protected $connectionName;
	public function __construct($datas = null)
	{
		$this->connection = $this->connectionName ? Connection::Get($this->connectionName) : Connection::Current();
		parent::__construct($datas);
	}
	public function createClause()
	{
		return $this->connection->createClause($this->connection->table($this->tableName()));
	}
	public function connection()
	{
		return $this->connection;
	}
	public function fetch($filters = null)
	{
		$filters = $filters ? $filters : $this->get_primaryKey(true);

		$dc = $this->defaultClause();
		if(!$dc)
			$dc = $this->connection->createClause($this->connection->table($this->tableName()));

		if($filters)
			$dc = $this->connection->mergeClauses($dc,array('where'=>$filters));
			//$filters = $this->connection->mergeClauses($dc, $filters);

		$r = $this->connection->select($dc);
		if(!empty($r))
		{
			$this->import($r[0], true);
			return $this;
		}
		return null;
	}
	protected function defaultClause()
	{
		return $this->connection->createClause($this->connection->table($this->tableName()));
	}
	public function update()
	{
		$data = $this->modifiedDatas();
		if(!empty($data))
		{
			$this->connection->update($this->tableName(),$data ,$this->get_primaryKey(true));
			$this->clearModifications();
		}
	}
	public function insert()
	{
		$this->connection->insert($this->tableName(),$this->modifiedDatas());
		if(is_string($this->_primaryKey) && $this->_fieldsDefinitions[$this->_primaryKey]['ai'])
		{
			$this->set_primaryKey($this->connection->pdo()->lastInsertId());
		}
	}
	public function delete()
	{
		$this->connection->delete($this->tableName(),$this->get_primaryKey(true));
	}

	static public function Find(array $q = array(),$args = null, $returnAsSelf = true)
	{
		$cls = get_called_class();
		if(!Connection::IsClause($q))
			$q['where'] = $q;
		$o = new $cls();
		if(!isset($q['from']))
			$q['from'] = $o->connection->table($o->tableName());
		$q = $o->connection->mergeClauses($o->defaultClause(), $q);
		$a = $o->connection->select($q, $args, $returnAsSelf ? get_called_class() : null);
		if($returnAsSelf)
			foreach ($a as $o)
				$o->clearModifications(); //XXX
		return $a;
	}
	static public function FindOne(array $q = array(), $args = null)
	{
		//	$q['limit'] = array('0','1');
		$cls = get_called_class();
		$a = $cls::Find($q, $args);
		return count($a)?$a[0]:null;
	}
	static public function Search(array $q = array(), $args = null, $from = 0, $pageSize = 10, $sort = null)
	{
		$cls = get_called_class();
		$o = new $cls();
		if(!isset($q['from']))
			$q['from'] = $o->connection->table($o->tableName());

		$q = $o->connection->mergeClauses($o->defaultClause(), $q);
		
		$count = $o->connection->select('SELECT COUNT(1) c FROM ('.$o->connection->build($q,$args).') t',$args);
		
		$q->limit = array($from, $pageSize);
		if($sort)
			$q->orderBy = $sort;
		$a = $o->connection->select($q,$args,get_called_class());
		foreach ($a as $o)
			$o->clearModifications(); //XXX
		return array(
			$a,
			$count[0]->c
		);
	}
	static public function Count(array $q = array(), $args = null)
	{
		$cls = get_called_class();
		$o = new $cls();
		if(!isset($q['from']))
			$q['from'] = $o->connection->table($o->tableName());
		$q = $o->connection->mergeClauses($o->defaultClause(), $q);
		$count = $o->connection->select('SELECT COUNT(1) c FROM ('.$o->connection->build($q,$args).') t',$args);
		return $count[0]->c;
	}
	static public function DeleteAll($filters = null)
	{
		$cls = get_called_class();
		$o = new $cls();
		$o->connection->delete($o->tableName(), $filters);
	}
}