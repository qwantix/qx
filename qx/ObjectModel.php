<?php
namespace qx;
/**
 * @author Brice Dauzats
 */
class ObjectModel extends Observable 
{

	protected $_fields = array();
	protected $_fieldsDefinitions = array();
	protected $_foreignsTables = array();
	protected $_datas = array();

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
		if(in_array($name, $this->_fields))
			return method_exists($this, $this->_get_prefix.$name) ?
				 $this->{$this->_get_prefix.$name}() : 
				 (isset($this->_datas[$name])?$this->_datas[$name]:null);
		else if(isset($this->_foreignsTables[$name]))
			return $this->getForeignObject($name);
		return  null;
	}

	public function __set($name, $value)
	{
		if(in_array($name, $this->_fields))
		{
			if(method_exists($this, $this->_set_prefix.$name))
				$this->_datas[$name] = $this->{$this->_set_prefix.$name}($value);
			else
				$this->set_field($name, $value);
			$this->setModified($name);
		}
	}

	public function __unset($name)
	{
		unset ($this->_datas[$name]);
	}

	protected function init($datas = null)
	{
		$this->import($datas);
		$pk = $this->get_primaryKey();
		if(!empty($pk) && $this->_autoFetch)
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
		$this->primaryKeyFilled = false;
		if(!is_array($this->_primaryKey) && 
			!is_object($datas) && !is_array($datas) &&
			in_array($this->_primaryKey, $this->_fields)
		)
		{
			$this->_datas[$this->_primaryKey] = $datas;
			$this->primaryKeyFilled = !empty($datas);
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

	protected function set_field($name, $value)
	{
		if(in_array($name, $this->_fields))
		{
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

	public function fetch()
	{
		throw new Exception("Override fetch method!");
	}

	public function save($force = false)
	{
		if($force)
			$this->_modifiedFields = (array)$this->_fields;
		if(empty($this->_modifiedFields)) 
			return;
		if($this->exists())
			$this->update();
		else
			$this->insert();
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
		return in_array('id', $this->_fields) ? !empty($this->id) : false;
	}

	public function modifiedDatas()
	{
		$a = array();
		foreach ($this->_modifiedFields as $f)
			$a[$f] = $this->_datas[$f];
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
	}

	private $_foreignsCache = array();
	public function getForeignObject($name)
	{
		if(!isset($this->_foreignsCache[$name]))
		{
			list($field, $table, $tableField) = $this->_foreignsTables[$name];
			$cls = '\\app\\models\\'.str_replace(' ', '',ucwords(str_replace('_', ' ', $table)));
			$o = new $cls();
			$o->fetch(array($tableField=>$this->$field));
			$this->_foreignsCache[$name] = $o;
		}
		return $this->_foreignsCache[$name];
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
}
