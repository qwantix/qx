<?php
namespace qx;

class DateTime extends \DateTime
{
	static public function FromInput($value)
	{
		return \DateTime::createFromFormat( __('@date_format') , $value);
	}
}