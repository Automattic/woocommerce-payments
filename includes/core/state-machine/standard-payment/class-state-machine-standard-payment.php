<?php

namespace WCPay\Core\State_Machine;
class State_Machine_Standard_Payment extends State_Machine_Abstract {
	 public function get_id(): string {
		 return 'standard_payment';
	 }

	 protected $config = [
		 Start_Standard_Payment_State::class => [ Prepare_Data_State::class ],
		 Prepare_Data_State::class => [
			 General_Failed_State::class,
			 Validate_Data_State::class
		 ],

		 Validate_Data_State::class => [
			 General_Failed_State::class,
			 Avoid_Duplicate_State::class
		 ],

		 Avoid_Duplicate_State::class => [
			 General_Failed_State::class,
			 Detect_Duplicate_State::class,
		 ],

		 Detect_Duplicate_State::class => [
			 Completed_Duplicate_State::class,
		 ],

		 Start_Processing_State::class =>  [
			 General_Failed_State::class,
		     Process_Standard_Payment_State::class
		 ],

		 Process_Standard_Payment_State::class => [
			 General_Failed_State::class,
			 Confirming_State::class,
			 Need_3ds_State::class],

		 Need_3ds_State::class => [
			 Confirming_State::class,
			 General_Failed_State::class,
		 ],

		 Confirming_State::class => [
			 Completed_State::class
		 ],
	 ];



}
