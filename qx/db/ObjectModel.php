<?php
namespace qx\db;
/**
 * @author Brice Dauzats
 */
class ObjectModel extends \qx\Observable 
{

	protected $_fields = array();
	protected $_fieldsDefinitions = array();
	protected $_foreignsTables = array();
	protected $_datas = array();
	protected $_extraDatas = array();

	protected $_primaryKey = 'id';

	protected $_get_prefix = 'get_';
	protected $_set_prefix = 'set_';
	protected $_name;

	protected $_modifiedFields = array();

	private $_autoFetch = true;

	public function __invoke($datas = null)
	{
		return new self($datas);
	}

	public function __construct($datas = null, $autoFetch = true)
	{
		$this->_name = $this->tableName();
		$this->_autoFetch = $autoFetch;
		$this->init($datas);
	}

	public function __isset($name)
	{
		return isset($this->_datas[$name]) || isset($this->_foreignsTables[$name]);
	}

	public function __get($name)
	{
		if(method_exists($this, $this->_get_prefix.$name))
			return $this->{$this->_get_prefix.$name}();
		else if(in_array($name, $this->_fields))
			return $this->get_field($name);
		else if(isset($this->_foreignsTables[$name]))
			return $this->getForeignObject($name);
		return  null;
	}

	public function __set($name, $value)
	{
		if(in_array($name, $this->_fields))
		{
			$lastValue = isset($this->_datas[$name]) ? $this->_datas[$name] : null ;
			if(method_exists($this, $this->_set_prefix.$name))
				$this->_datas[$name] = $this->{$this->_set_prefix.$name}($value);
			else
				$this->set_field($name, $value);
			if(isset($this->_datas[$name]) && $lastValue !== $this->_datas[$name])
				$this->setModified($name);
		}
		else if(method_exists($this, $this->_set_prefix.$name))
		{
			$this->{$this->_set_prefix.$name}($value);
		}
		else
			$this->_extraDatas[$name] = $value;
	}

	public function __unset($name)
	{
		unset ($this->_datas[$name]);
	}

	protected function init($datas = null)
	{
		$this->import($datas);
		if($this->primaryKeyFilled() && $this->_autoFetch)
			$this->fetch();
	}

	public function tableName()
	{
		if(empty($this->_name))
		{
			$cls = explode('\\', get_class($this));
			$this->_name = end($cls);
		}
		return $this->_name;
	}

	public function import($datas, $isInit = false)
	{
		$this->set_primaryKey($datas);
		$datas = (object)$datas;
		if($isInit)
			$this->_datas = array();
		
		foreach ($this->_fields as $f)
			if(isset($datas->$f) && $this->$f !== $datas->$f)
				$this->$f = $datas->$f;
			
		if($isInit)
			$this->clearModifications();
		return $this;
	}

	public function reset()
	{
		foreach ($this->_fields as $f)
			$this->$f = null;
		$this->_modifiedFields = array();
		$this->_extraDatas = array();
	}

	protected $primaryKeyFilled = false;
	public function get_primaryKey($forceAssoc = false) {
		if(is_array($this->_primaryKey))
		{
			$a = array();
			foreach ($this->_primaryKey as $f)
				$a[$f] = $this->$f;
			return $a;
		}
		else
			return $forceAssoc ? array($this->_primaryKey=>@$this->_datas[$this->_primaryKey]) : @$this->_datas[$this->_primaryKey];
	}

	public function set_primaryKey($datas)
	{
		$this->primaryKeyFilled = false; //unused

		if(!is_array($this->_primaryKey))
		{
			if(!is_object($datas) && !is_array($datas))
				$this->_datas[$this->_primaryKey] = $datas;
			else {
				$datas = (array)$datas;
				if(isset($datas[$this->_primaryKey]))
					$this->_datas[$this->_primaryKey] = $datas[$this->_primaryKey];
			} 
			$this->primaryKeyFilled = !empty($this->_datas[$this->_primaryKey]);
		}
		else if( is_array($this->_primaryKey) && (is_object($datas) || is_array($datas)) )
		{
			$datas = (array)$datas;
			$int = array_intersect($this->_primaryKey, array_keys($datas));
			if(count($int) == count($this->_primaryKey)) 
			{
				$filled = true;
				foreach ($this->_primaryKey as $f) {
					$this->$f = $datas[$f];
					$filled = $filled && !empty($datas[$f]);
				}
				$this->primaryKeyFilled = $filled;
			}
		}

	}
	public function primaryKeyFilled()
	{
		$a = $this->get_primaryKey();
		if(is_array($a))
		{
			foreach ($a as $v)
				if(!empty($v))
					return true;
		}
		else
		{
			return !empty($a);
		}
		return false;
		
	}
	protected function set_field($name, $value)
	{
		if(in_array($name, $this->_fields))
		{
			if($value !== null) //Keep null values
				switch ($this->_fieldsDefinitions[$name]['type'])
				{
					case 'int':
						$value = (int)$value;
						break;
					case 'float':
						$value = (float)$value;
						break;
					case 'date':
						if($value instanceof \DateTime)
							$value = $value->format('Y-m-d');
						elseif($value !== null && !preg_match('`^\d{4}-\d{2}-\d{2}$`', $value))
						{
							if($dt = \DateTime::createFromFormat( __('@date_format') , $value))
								$value = $dt->format('Y-m-d');
							else
								$value = '0000-00-00';
						}
						break;
					case 'time':
						if($value instanceof \DateTime)
							$value = $value->format('H:i:s');
						elseif($value !== null && !preg_match('`^\d{2}:\d{2}:\d{2}$`', $value))
						{
							if($dt = \DateTime::createFromFormat( __('@time_format') , $value))
								$value = $dt->format('H:i:s');
							else
								$value = '00:00:00';
						}
						break;
					case 'datetime':
						if($value instanceof \DateTime)
							$value = $value->format('Y-m-d H:i:s');
						elseif($value !== null && !preg_match('`^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$`', $value))
						{
							if($dt = \DateTime::createFromFormat( __('@datetime_format') , $value))
								$value = $dt->format('Y-m-d H:i:s');
							else
								$value = '0000-00-00';
						}
						break;
				}
		}
		
		$this->_datas[$name] = $value;
	}
	protected function get_field($name)
	{
		return isset($this->_datas[$name])?$this->_datas[$name]:null;
	}
	public function fetch()
	{
		throw new Exception("Override fetch method!");
	}

	public function save($force = false)
	{
		if($force)
			$this->_modifiedFields = (array)$this->_fields;
		if(!$this->exists())
			$this->insert();
		else
			$this->update();
	}

	public function update()
	{
		throw new Exception("Override update method!");
	}

	public function insert()
	{
		throw new Exception("Override insert method!");
	}

	public function delete()
	{
		throw new Exception("Override delete method!");
	}

	public function exists()
	{
		return in_array('id', $this->_fields) ? $this->id>0 : false; //XXX
	}

	public function modifiedDatas()
	{
		$a = array();
		foreach ($this->_modifiedFields as $f)
			$a[$f] = isset($this->_datas[$f]) ? $this->_datas[$f] : null;
		return $a;
	}

	public function clearModifications()
	{
		$this->_modifiedFields = array();
	}

	public function setModified($name)
	{
		if(!in_array($name, $this->_modifiedFields))
			$this->_modifiedFields[] = $name;
		if(isset($this->_foreignsCache[$name]))
			unset($this->_foreignsCache[$name]);
	}

	protected $_foreignsCache = array();
	public function getForeignObject($name)
	{
		if(!isset($this->_foreignsCache[$name]))
		{
			list($field, $table, $tableField) = $this->_foreignsTables[$name];
			$cls = '\\app\\models\\'.str_replace(' ', '',ucwords(str_replace('_', ' ', $table)));
			/*$o = new $cls();
			$o->fetch(array($tableField=>$this->$field));*/
			$this->_foreignsCache[$name] = $this->createForeignObject($cls, $field, $tableField);
		}
		return $this->_foreignsCache[$name];
	}
	protected function createForeignObject($cls, $localField, $foreignField)
	{
		$o = new $cls();
		$o->fetch(array($foreignField=>$this->$localField));
		return $o;
	}
	public function extra()
	{
		return (object)$this->_extraDatas;
	}
	public function toArray()
	{
		$a = array();
		foreach ($this->_fields as $f)
			$a[$f] = $this->$f;
		return $a;
	}
	
	public function toObject()
	{
		return (object)$this->toArray();
	}

	public function __toString()
	{
		$pk = @$this->get_primaryKey();
		return get_class($this).'('.(is_array($pk)?implode(',',$pk):$pk).')';
	}
	
	public function __sleep()
	{
		return array(
			"_fields",
			"_fieldsDefinitions",
			"_foreignsTables",
			"_datas",
			"_extraDatas",
			"_primaryKey",
			"_get_prefix",
			"_set_prefix",
			"_name",
			"_modifiedFields",
			"\0qx\\db\\ObjectModel\0_autoFetch",
			"primaryKeyFilled"
		);
	}

	/**
	 * Session object for this object
	 */
	public function session()
	{
		return \qx\Session::Of($this, $this->id);
	}
}
