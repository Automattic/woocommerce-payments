<?php
namespace WCPay\Core\State_Machine;

interface Entity {
	public function get_current_state(): ?State;
	public function get_revisions(): ?array;
	public function log( State $previous_state, State $current_state, Input $input, State_Machine_Abstract $state_machine, int $timestamp = null );
	public function set( string $key, $value );
	public function get( string $key );
	public function exist( string $key ): bool;
}
