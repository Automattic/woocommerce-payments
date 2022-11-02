<?php

namespace WCPay\Core\DataTransferObjects\API\Request;

use WCPay\Core\Contracts\API\Request\Base_Request;
use WCPay\Core\DataTransferObjects\Data_Transfer_Object;

final class Request extends Data_Transfer_Object implements Base_Request
{
	/**
	 * @var array $parameters Request params.
	 */
	private $parameters;

	/**
	 * @var string $method HTTP method.
	 */
	private $method;

	/**
	 * @var string $route API route.
	 */
	private $route;

	/**
	 * @var array Request headers.
	 */
	private $headers;

	/**
	 * @var bool $is_site_specific Is site specific call.
	 */
	private $is_site_specific;

	/**
	 * @var string $use_user_token Use user token.
	 */
	private $use_user_token;

	/**
	 * @param array $parameters Request params.
	 * @param string $method HTTP method.
	 * @param string $route Request API route.
	 * @param array $headers Request headers.
	 * @param bool $is_site_specific Is site specific call.
	 * @param bool $use_user_token Use user token.
	 */
	public function __construct($parameters, $method, $route, $headers = [], $is_site_specific = true, $use_user_token = false)
	{
		$this->parameters = $parameters;
		$this->method = $method;
		$this->route = $route;
		$this->headers = $headers;
		$this->is_site_specific = $is_site_specific;
		$this->use_user_token = $use_user_token;
	}


	/**
	 * @return array
	 */
	public function get_parameters()
	{
		return $this->parameters;
	}

	/**
	 * @return string
	 */
	public function get_method()
	{
		return $this->method;
	}

	/**
	 * @return string
	 */
	public function get_route()
	{
		return $this->route;
	}

	/**
	 * @return array
	 */
	public function get_headers()
	{
		return $this->headers;
	}



	/**
	 * @return bool
	 */
	public function is_site_specific()
	{
		return $this->is_site_specific;
	}

	/**
	 * @return bool
	 */
	public function use_user_token()
	{
		return $this->use_user_token;
	}
}
