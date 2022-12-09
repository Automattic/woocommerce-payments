<?php
/**
 * Currency changes information
 *
 * @package WooCommerce\Payments\i18n
 * @version 5.2.0
 */

defined( 'ABSPATH' ) || exit;

return [
	'HR' => [
		'effective' => 1672531200, // Jan 1, 2023.
		'update'    => [
			'currency_code' => 'EUR',
			'name'          => 'Euro',
			'singular'      => 'euro',
			'plural'        => 'euros',
			'short_symbol'  => '€',
			'locales'       => $locales['EUR'],
		],
	],
];
