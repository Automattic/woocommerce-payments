<?php
namespace WCPay\Core\State_Machine;

interface Entity_Storage_Interface {
	public function save( Entity $entity );
	public function load( $reference ): Entity;
}
