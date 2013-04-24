<?php
namespace qx\db\pdo;
/**
 * @author Brice Dauzats
 * TODO...
 */
class HierarchicalHelper extends \qx\Observable 
{
	private $_model;
	private $_table;
	private $_connection;
	private $_cond;
	private $_lftField = 'lft';
	private $_rgtField = 'rgt';
	private $_sort = 'pos';
	private $_parentField = 'parent_id';

	public function __construct(ObjectModel $model,array $args = null)
	{
		$this->_model = $model;
		$this->_table = $model->tableName();
		$this->_connection = $model->connection();

		if(!empty($args))
		{
			if(isset($args['cond']))
				$this->_cond = $args['cond'];
			if(isset($args['lftField']))
				$this->_lftField = $args['lftField'];
			if(isset($args['rgtField']))
				$this->_rgtField = $args['rgtField'];
			if(isset($args['sort']))
				$this->_sort = $args['sort'];
			if(isset($args['parentField']))
				$this->_parentField = $args['parentField'];
		}
		
	}

	public function setCond($cond)
	{
		$this->_cond = $cond;
		return $this;
	}

	public function delete()
	{
		$this->_connection->startTransaction();
		$lftValue = $this->_model->{$this->_lftField};
		$rgtValue = $this->_model->{$this->_rgtField};
		$gap = $rgtValue-$lftValue;
		//Remove leafs and sub leafs
		$this->_connection
			->delete($this->_table,array("$this->_lftField BETWEEN $lftValue AND $rgtValue", $this->_cond) );
		//Update next leafs
		$q =  "UPDATE `$this->_table` SET ";
		$q .= "$this->_lftField = $this->_lftField - $gap,";
		$q .= "$this->_rgtField = $this->_rgtField - $gap ";
		$q .= "WHERE ";
		$c = array("$this->_lftField > $lftValue");
		if($this->_cond)
			$c[] = $this->_cond;

		$q .= $this->_connection->buildWhereClose($c, $args);
		$this->_model->connection()->exec($q, $args);
		$this->_connection->commit();
	}
	public function insertAt()
	{
		//TODO
	}

	public function rebuild()
	{
		$this->_connection->startTransaction();
		$this->_rebuildTree($this->_model->id, $this->_model->{$this->_lftField});
		$this->_connection->commit();
	}
	public function rebuildAll()
	{
		$this->_connection->startTransaction();
		$this->_rebuildTree();
		$this->_connection->commit();
	}
	private function _rebuildTree( $id = null, $ft = 0)
	{
		$gt = $ft + 1;
		$args = array();
		$q = "SELECT id FROM `$this->_table` WHERE ";

		$c = array($id?array($this->_parentField=>$id):"$this->_parentField IS NULL");
		if($this->_cond)
			$c[] = $this->_cond;

		$q .= $this->_connection->buildWhereClose($c, $args);
		if($this->_sort)
			$q .= ' ORDER BY '.$this->_sort;

		$ids = $this->_connection->select($q,$args);
		foreach ($ids as $r)
			$gt = $this->_rebuildTree( $r->id, $gt);
		
		if($id)
			$this->_connection->exec("UPDATE `$this->_table` SET $this->_lftField = ?, $this->_rgtField = ? WHERE id = ?", array($ft,$gt,$id));
		return $gt + 1;
	}
}