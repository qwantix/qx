<?php
namespace qx;
/**
 * @author Brice Dauzats
 */
class Form extends Observable {
	
	/**
	 *
	 * @param array $fields
	 * @return Form 
	 */
	static public function Create(array $fields)
	{
		return new self($fields);
	}

	static private $_Types = array();
	static public function RegisterFormType($name, $type)
	{
		self::$_Types[$name] = $type;
	}

	/**
	 * 
	 * @return IFormValidator
	 */
	static protected function CreateFormField($type)
	{

		if(!isset(self::$_Types[$type]))
			throw new Exception("Unknow type $type");
		$typecls = self::$_Types[$type];
		$inst = new $typecls();
		if(!($inst instanceof IFormType))
			throw new Exception("Type must be implement IFormType");
		return $inst;
	}

	private $_errors = array();
	private $_fields = array();
	private $_datas = array();
	private $_validated = false;
	public function __construct(array $fields)
	{
		$this->_fields = $fields;
	}

	public function validate($datas = null)
	{
		$this->_validated = true;
		$this->_errors = array();
		$this->_datas = $datas?(array)$datas:$_POST;
		foreach($this->_fields as $field=>$v)
		{
			if(	isset($this->_fields[$field]) 
				&& array_key_exists('autoValidate', $this->_fields[$field])
				&& $this->_fields[$field]['autoValidate'] == false )
				continue;
			
			$this->validateField ($field);
		}
		return empty($this->_errors);
	}

	public function validateField($field)
	{
		try {
			$this->field($field)->validate();
		} catch (FormException $e) {
			$this->addError($field, $e->getMessage());
			return false;
		}
		return true;
	}

	public function values()
	{
		$o = new \stdClass();
		foreach($this->_fields as $field=>$def)
		{
			$def = (object) $def;
			$v = $this->field($field)->getValue();
			$o->$field = $v;
		}
		return $o;
	}

	private $_fieldsInst = array();
	public function field($name)
	{
		if(isset($this->_fieldsInst[$name]))
			return $this->_fieldsInst[$name];
		if (!isset($this->_fields[$name]))
			throw new Exception('Unknow field '.$name);
		$opt = (object)$this->_fields[$name];
		$inst = self::CreateFormField(isset($opt->type)?$opt->type:'default') ;
		$inst->setOptions($opt);
		$inst->setValue(isset($this->_datas[$name])?$this->_datas[$name]:null);
		$this->_fieldsInst[$name] = $inst;
		return $inst;
	}

	/**
	 * Get if form is valid
	 * 
	 * @return bool
	 */
	public function isValid()
	{
		if(!$this->_validated)
			$this->validate();
		return empty($this->_errors);
	}
	/**
	 * Get errors
	 * 
	 * Return an associative array of errors
	 * @return array
	 */
	public function errors()
	{
		return $this->_errors;
	}
	/**
	 * Add error in form for specific field
	 */
	public function addError($field,$message)
	{
		$this->_errors[$field] = $message;
	}
	
	/**
	 * Opposite to isValid
	 * 
	 * @return bool
	 */
	public function hasError()
	{
		return !$this->isValid();
	}
}


interface IFormType 
{
	public function setOptions($opts);
	public function setValue($value);
	public function getValue();
	public function validate();
	public function isEmpty();
}

class FormTypeDefault implements IFormType
{
	protected $opts;
	protected $value;
	public function setOptions($opts)
	{
		$this->opts = (object)$opts;
	}
	public function setValue($value)
	{
		$this->value = trim($value);
	}
	public function getValue()
	{
		return $this->value;
	}
	public function validate()
	{
		if(!empty($this->opts->required) && $this->isEmpty())
			throw new FormException(__('This field is required'));
		return true;
	}
	public function isEmpty()
	{
		return empty($this->value);
	}
}
Form::RegisterFormType('default', '\\qx\\FormTypeDefault');

class FormTypeInt extends FormTypeDefault {
	public function getValue()
	{
		return (int)$this->value;
	}
	public function validate()
	{
		parent::validate();
		if (!$this->isEmpty() && !is_numeric($this->value))
			throw new FormException(\__("This field isn't a valid number"));
		return true;
	}
}
Form::RegisterFormType('int', '\\qx\\FormTypeInt');

class FormTypeFloat extends FormTypeDefault {
	public function getValue()
	{
		return (float)$this->value;
	}
	public function validate()
	{
		parent::validate();
		if (!$this->isEmpty() && !is_numeric($this->value))
			throw new FormException(\__("This field isn't a valid number"));
		return true;
	}
}
Form::RegisterFormType('float', '\\qx\\FormTypeFloat');

class FormTypeEmail extends FormTypeDefault {
	public function getValue()
	{
		return $this->value;
	}
	public function validate()
	{
		parent::validate();
		if (!$this->isEmpty() && !preg_match('`^[A-Z0-9._%+-]+@(?:[A-Z0-9-]+\.)+[A-Z]{2,4}$`i', $this->value))
			throw new FormException(__("This field isn't a valid email"));
		return true;
	}
}
Form::RegisterFormType('email', '\\qx\\FormTypeEmail');

class FormTypeDate extends FormTypeDefault {
	private $date;
	public function setValue($value)
	{
		parent::setValue($value);
		$this->date = \DateTime::createFromFormat( __('@date_format') , $this->value);
	}
	public function getValue()
	{
		return $this->date;
	}
	public function validate()
	{
		parent::validate();
		if (!$this->isEmpty() && !($this->date instanceof \DateTime))
			throw new FormException(\__("This field isn't a valid date"));
		return true;
	}
}
Form::RegisterFormType('date', '\\qx\\FormTypeDate');

class FormTypeTime extends FormTypeDefault {
	private $date;
	public function setValue($value)
	{
		parent::setValue($value);
		$this->time = \DateTime::createFromFormat( __('@time_format') , $this->value);
	}
	public function getValue()
	{
		return $this->time;
	}
	public function validate()
	{
		parent::validate();
		if (!$this->isEmpty() && !($this->time instanceof \DateTime))
			throw new FormException(\__("This field isn't a valid date"));
		return true;
	}
}
Form::RegisterFormType('time', '\\qx\\FormTypeTime');

class FormTypeUrl extends FormTypeDefault {
	public function getValue()
	{
		return $this->value;
	}
	public function validate()
	{
		parent::validate();
		if (!$this->isEmpty() && !preg_match('`^http(s)?://[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(/.*)?$`i', $this->value))
			throw new FormException(__("This field isn't a valid url format"));
		/*else if(get_headers($this->value) === false)
			throw new FormException(__("This url is invalid"));*/
		return true;
	}
}
Form::RegisterFormType('url', '\\qx\\FormTypeUrl');

class FormTypeArray extends FormTypeDefault {
	public function setValue($value)
	{
		var_dump($value);
		$this->value = is_array($value) ? $value : null;
	}
	public function getValue()
	{
		return $this->value;
	}
	public function validate()
	{
		parent::validate();
		return true;
	}
}
Form::RegisterFormType('array', '\\qx\\FormTypeArray');

class FormTypeObject extends FormTypeDefault {
	public function setValue($value)
	{
		$this->value = is_object($value) ? $value : null;
	}
	public function getValue()
	{
		return $this->value;
	}
	public function validate()
	{
		parent::validate();
		return true;
	}
}
Form::RegisterFormType('object', '\\qx\\FormTypeObject');