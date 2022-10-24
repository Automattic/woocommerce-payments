<?php
namespace WCPay\API;

/**
 * Holds all static instances of the API's classes.
 *
 * @property-read Mode $mode The working mode of the gateway.
 */
class API {
	protected $mode;

	public function __construct() {
		$this->mode = new Mode();
	}

	public function __get( $prop ) {
		return $this->$prop; // A read-only accessor.
	}
}
