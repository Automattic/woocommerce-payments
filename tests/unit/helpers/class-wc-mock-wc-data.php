<?php
/**
 * Helper class to provide mock functionality to abstract methods of
 * WC_Data
 *
 * @package WooCommerce\Tests
 */

// Need to use WC_Mock_Data_Store.
require_once dirname( __FILE__ ) . '/class-wc-mock-wc-data-store.php';

/**
 * Used for exposing and testing the various Abstract WC_Data methods.
 */
class WC_Mock_WC_Data extends WC_Data {

	/**
	 * Data array
	 *
	 * @var array
	 */
	protected $data = [
		'content'    => '',
		'bool_value' => false,
	];

	/**
	 * Cache group
	 *
	 * @var string
	 */
	protected $cache_group = '';

	/**
	 * Data Store
	 *
	 * @var WC_Data_Store
	 */
	public $data_store;

	/*
	|--------------------------------------------------------------------------
	| Abstract methods.
	|--------------------------------------------------------------------------
	| Define the abstract methods WC_Data classes expect, so we can go on to
	| testing the good bits.
	*/

	/**
	 * Simple read.
	 */
	public function __construct( $id = '' ) {
		parent::__construct();
		if ( ! empty( $id ) ) {
			$this->set_id( $id );
		} else {
			$this->set_object_read( true );
		}

		$this->data_store = new WC_Mock_WC_Data_Store();

		if ( $this->get_id() > 0 ) {
			$this->data_store->read( $this );
		}
	}

	/**
	 * Simple get content.
	 *
	 * @param  string $context
	 * @return string
	 */
	public function get_content( $context = 'view' ) {
		return $this->get_prop( 'content', $context );
	}

	/**
	 * Simple set content.
	 *
	 * @param string $content
	 */
	public function set_content( $content ) {
		$this->set_prop( 'content', $content );
	}

	/**
	 * Simple get bool value.
	 *
	 * @param  string $context
	 * @return bool
	 */
	public function get_bool_value( $context = 'view' ) {
		return $this->get_prop( 'bool_value', $context );
	}

	/**
	 * Simple set bool value.
	 *
	 * @return bool
	 */
	public function set_bool_value( $value ) {
		if ( ! is_bool( $value ) ) {
			$this->error( 'invalid_bool_value', 'O noes' );
		}
		$this->set_prop( 'bool_value', $value );
	}

	/**
	 * Simple get data as array.
	 * @return array
	 */
	public function get_data() {
		return array_merge(
			$this->data,
			[ 'meta_data' => $this->get_meta_data() ]
		);
	}

	/**
	 * Set the data to any arbitrary data.
	 * @param array $data
	 */
	public function set_data( $data ) {
		$this->data = $data;
	}

	/**
	 * Set the changes to any arbitrary changes.
	 * @param array $changes
	 */
	public function set_changes( $changes ) {
		$this->changes = $changes;
	}

	/**
	 * Simple save.
	 */
	public function save() {
		if ( $this->data_store ) {
			if ( $this->get_id() ) {
				$this->data_store->update( $this );
			} else {
				$this->data_store->create( $this );
			}
		}
		$this->save_meta_data();
		return $this->get_id();
	}
}
