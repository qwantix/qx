<?php
namespace qx;
/**
 * @author Brice Dauzats
 */
interface ICredentials 
{
	public function authenticate($datas);
	public function restore($id);
	public function getIdentity();
	public function getId();
	public function hasRight($right);
}