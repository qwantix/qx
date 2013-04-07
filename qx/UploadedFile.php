<?php
namespace qx;
/**
 * Provide simple access to uploaded files
 * 
 * @author Brice Dauzats
 */
class UploadedFile extends Observable
{
	/**
	 * Get uploaded file by name
	 */
	static public function Get($name)
	{
		if (!empty($_FILES) 
			&& isset($_FILES[$name])
			&& is_uploaded_file($_FILES[$name]['tmp_name']))
		{
			$uf = new UploadedFile($name);
			return $uf;
		}
		return null;
	}

	//////////////////////

	private $_name;
	/**
	 * Get file name of uploaded file
	 */
	public function name()
	{
		return $this->_name;
	}

	/**
	 * Get safe file name of uploaded file
	 */
	public function safeName()
	{
		return preg_replace('`[\\/]+`', '_',$this->_name); //clean name to prevent attack by "/" or "\"
	}

	private $_tmpName;
	public function tmpName()
	{
		return $this->_tmpName;
	}

	private $_size;
	public function size()
	{
		return $this->_size;
	}

	private function __construct($name)
	{
		$o = $_FILES[$name];
		$this->_name = $o['name'];
		$this->_tmpName = $o['tmp_name'];
		$this->_size = $o['size'];
		
	}

	/**
	 * Get mime type of uploaded file
	 */
	public function mime()
	{
		if(function_exists('mime_content_type'))
			return mime_content_type($this->_tmpName);
		else if (function_exists('finfo_file'))
		{
			$fi = new \finfo(FILEINFO_MIME_TYPE);
			$mime = $fi->file($this->_tmpName);
			$mime = array_shift(explode(';',$mime));
			$fi->close();
		}
		return $mime;
	}
	/**
	 * Get safe extension using mime type
	 */
	public function ext()
	{
		return Tools::MimeToExt($this->mime(), $this->name());
	}

	/**
	 * Remove uploaded file
	 */
	public function remove()
	{
		unlink($this->_tmpName);
		$session = new core\Session($this);
		$session->{$this->_id} = null;
		
	}

	/**
	 * Safe uploaded file to a file
	 */
	public function saveAs($target)
	{
		return move_uploaded_file($this->_tmpName,$target);
	}

	/**
	 * Check if uploaded file type
	 * @param $types array of mimes type
	 */
	public function isTypeOf($types)
	{
		if(is_string($types))
			$types = array($types);
		return in_array($this->mime(), $types);
	}
	
}