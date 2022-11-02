<?php

namespace WCPay\Core\Contracts\API\Request;

interface Base_Request {

	public function get_parameters();

	public function get_method();

	public function get_route();

	public function is_site_specific();

	public function use_user_token();

	public function get_headers();
}
