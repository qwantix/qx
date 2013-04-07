<?php
namespace qx\pdo;

/**
 * @author Brice Dauzats
 */
class ObjectModel extends \qx\ObjectModel
{
	protected $connection;
	public function __construct($datas = null)
	{
		$this->connection = Connection::Current();
		parent::__construct($datas);
	}
	public function q()
	{
		return $this->connection->createClause($this->connection->table($this->tableName()));
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
		$this->connection->update($this->tableName(),$this->modifiedDatas() ,$this->get_primaryKey(true));
		$this->clearModifications();
	}
	public function insert()
	{
		$this->connection->insert($this->tableName(),$this->modifiedDatas());
	}
	public function delete()
	{
		$this->connection->delete($this->tableName(),$this->get_primaryKey(true));
	}

	static public function Find(array $q = array(),$args = null)
	{
		$cls = get_called_class();
		if(!empty($q) && !isset($q['where']) 
			&& !isset($q['from']) 
			&& !isset($q['select']) 
			&& !isset($q['join']))
			$q['where'] = $q;
		$o = new $cls();
		if(!isset($q['from']))
			$q['from'] = $o->connection->table($o->tableName());
		$q = $o->connection->mergeClauses($o->defaultClause(), $q);
		return $o->connection->select($q,$args,get_called_class());
	}
	static public function FindOne(array $q = array(), $args = null)
	{
		$a = self::Find($q, $args);
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
		return array(
			$o->connection->select($q,$args,get_called_class()),
			$count[0]->c
		);
	}
}