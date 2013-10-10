<?php
namespace qx;

class DateTime extends \DateTime
{
	static public function FromInput($value)
	{
		return \DateTime::createFromFormat( __('@date_format') , $value);
	}
	static public function IsEmpty($value)
	{
		if(is_string($value))
			return !preg_match('`[1-9]`', $value);
		else if($value instanceof \DateTime)
			return $value->getTimestamp() == 0;
		return empty($value);
	}
}