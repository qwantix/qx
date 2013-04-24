<?php
namespace qx\db\pdo;

/**
 * @author Brice Dauzats
 */
class MysqlObjectModel extends ObjectModel {
	public function __construct($datas = null)
	{
		parent::__construct($datas);
	}
	protected function init($datas = null)
	{
		if(empty($this->_fields))
			$this->autoInit();	
		parent::init($datas);
	}
	protected function autoInit()
	{
		if(!($definition = \qx\Cache::Of('db/describe')->get($this->tableName())) || !is_array($definition))
		{
			$mysqlTypeMap = array(
				'tinyint' => 'int',
				'smallint' =>'int',
				'mediumint' => 'int',
				'int' => 'int',
				'integer' => 'int',
				'bigint' => 'int',
				'number' => 'int',
				'float' => 'float',
				'double' => 'float',
				'real' => 'float',
				'decimal' => 'float',

				'datetime' => 'datetime',
				'date' => 'date',
				'timestamp' => 'int',
				'time' => 'time',
				'year' => 'int',

				'char' => 'string',
				'binary' => 'string',
				'varchar' => 'string',
				'tinyblob' => 'string',
				'tinytext' => 'string',
				'blob' => 'string',
				'text' => 'string',
				'mediumblob' => 'string',
				'mediumtext' => 'string',
				'longblob' => 'string',
				'longtext' => 'string',

				'enum' => 'string'
			);

			$st = $this->connection->exec("DESCRIBE `".$this->tableName()."`");
			$columns = array();
			$primary = array();
			while ($o = $st->fetchObject())
			{
				$type = trim($o->Type);
				preg_match('`^([a-zA-Z_-]+)(\((\d+(,\d+)?|((\'[^\']+\',?))+)\))?(\s+(?:un)?signed)?$`xi',$type,$m);
				$type = strtolower(@$m[1]);
				
				$param = array_key_exists(3,$m)?$m[3]:null;
				$signed =isset($m[6]) && stristr($m[6],'unsigned') === false?true:false;
				if($o->Key == 'PRI')
					$primary[] = $o->Field;
				@$columns[$o->Field] =
					array (
						'type' => $mysqlTypeMap[$type],
						'primary' => $o->Key == 'PRI',
						'signed' => $signed,
						'ai' => $o->Extra == 'auto_increment',
						'fk' => array()
					);
			}
			
			$st = $this->connection->exec("
				SELECT kcu.referenced_table_schema, kcu.referenced_table_name, kcu.referenced_column_name, kcu.column_name
				FROM information_schema.table_constraints AS tc
				INNER JOIN information_schema.key_column_usage AS kcu USING( constraint_schema, constraint_name )
				WHERE tc.constraint_type = 'FOREIGN KEY' AND tc.table_schema= ? AND tc.table_name= ? "
			, array(
					$this->connection->db(),
					$this->tableName()
				)
			);

			while ($o = $st->fetchObject())
				$columns[$o->column_name]['fk'] = array (
					'table' => $o->referenced_table_name,
					'column' => $o->referenced_column_name,
					'alias' => substr($o->column_name,0,strlen($o->column_name)-3)
				);

			if(empty($columns))
				throw new \Exception("Table ".$this->tableName()." not found or empty");
			else
			{
				$definition = array(
					'columns'=>$columns,
					'primary'=>$primary
				);
				\qx\Cache::Of('db/describe')->set($this->tableName(),$definition);
			}
		}
		
		$columns = $definition['columns'];
		$primary = $definition['primary'];

		$this->_fieldsDefinitions = $columns;
		$this->_fields = array_keys($columns);
		$this->_foreignsTables = array();

		$this->_primaryKey = count($primary)>1?$primary:@$primary[0];
		
		foreach($columns as $key=>$def)
			if(!empty($def['fk']))
				$this->_foreignsTables[$def['fk']['alias']] = array($key,$def['fk']['table'],$def['fk']['column']);

	}
}