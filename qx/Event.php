<?php
namespace qx;
/**
 * @author Brice Dauzats
 */
class Event
{
	public $name;
	public $datas;
	public $target;
	public function __construct($name, $datas = null)
	{
		$this->name = $name;
		$this->datas = $datas;
	}
}