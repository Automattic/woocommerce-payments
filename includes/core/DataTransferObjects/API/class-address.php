<?php

namespace WCPay\Core\DataTransferObjects\API;

use WCPay\Core\DataTransferObjects\Data_Transfer_Object;

final class Address extends Data_Transfer_Object
{
	/**
	 * @var string $city City.
	 */
	private $city;

	/**
	 * @var string $country Country.
	 */
	private $country;

	/**
	 * @var string $address_1 Line 1.
	 */
	private $address_1;

	/**
	 * @var string $address_2 Line 2.
	 */
	private $address_2;

	/**
	 * @var string $postcode Post code.
	 */
	private $postcode;

	/**
	 * @var string $state State.
	 */
	private $state;

	/**
	 * @param string $city City.
	 * @param string $country Country.
	 * @param string $address_1 Address line 1.
	 * @param string $address_2 Address line 2.
	 * @param string $postcode Post code.
	 * @param string $state State.
	 */
	public function __construct($city, $country, $address_1, $address_2, $postcode, $state)
	{
		$this->city = $city;
		$this->country = $country;
		$this->address_1 = $address_1;
		$this->address_2 = $address_2;
		$this->postcode = $postcode;
		$this->state = $state;
	}

	/**
	 * Get city.
	 *
	 * @return string
	 */
	public function get_city(): string
	{
		return $this->city;
	}

	/**
	 * Get country.
	 *
	 * @return string
	 */
	public function getCountry(): string
	{
		return $this->country;
	}

	/**
	 * Get address line 1.
	 *
	 * @return string
	 */
	public function get_address1()
	{
		return $this->address_1;
	}

	/**
	 * Get address line 2.
	 *
	 * @return string
	 */
	public function get_address2()
	{
		return $this->address_2;
	}

	/**
	 * Get postcode.
	 * @return string
	 */
	public function get_postcode()
	{
		return $this->postcode;
	}

	/**
	 * Get state.
	 * @return string
	 */
	public function get_state()
	{
		return $this->state;
	}

	public function get_address_as_array() {
		return [
			'city' => $this->city,
			'country' => $this->country,
			'address_1' => $this->address_1,
			'address_2' => $this->address_2,
			'postcode' => $this->postcode,
			'state' => $this->state,
		];
	}

	public function to_array()
	{
		$data = $this->get_address_as_array();
		$data['formatted_address'] = $this->get_formatted_address();
		return $data;
	}

	/**
	 * Get formatted address.
	 *
	 * @return string
	 */
	public function get_formatted_address() {
		return WC()->countries->get_formatted_address( $this->get_address_as_array() );
	}

}
