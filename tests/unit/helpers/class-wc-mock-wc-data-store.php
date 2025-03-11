<?php
/**
 * Helper classes to provide mock functionality to abstract methods of WC_Data
 *
 * @package WooCommerce\Tests
 */

/**
 * Data store class used as a helper within a mock WC_Data class
 */
class WC_Mock_WC_Data_Store extends WC_Data_Store_WP implements WC_Object_Data_Store_Interface {

	/**
	 * Meta type
	 *
	 * @var string
	 */
	protected $meta_type = 'post';

	/**
	 * Object Id used for meta data
	 *
	 * @var string
	 */
	protected $object_id_field_for_meta = '';

	/**
	 * Internal key storage
	 *
	 * @var array
	 */
	protected $internal_meta_keys = [];

	/*
	|--------------------------------------------------------------------------
	| Setters for internal properties.
	|--------------------------------------------------------------------------
	| Normally we wouldn't want to be able to change this once the class is defined,
	| but to make testing different types of meta/storage, we should be able to
	| switch out our class settings.
	| These functions just change the properties set above.
	*/

	/**
	 * Set meta type (user or post).
	 * @param string $meta_type
	 */
	public function set_meta_type( $meta_type ) {
		$this->meta_type = $meta_type;
	}

	/**
	 * Set object ID field dynamically for testing.
	 * @param string $object_id_field
	 */
	public function set_object_id_field( $object_id_field ) {
		$this->object_id_field_for_meta = $object_id_field;
	}

	public function create( &$entity ) {
		if ( 'user' === $this->meta_type ) {
			$content_id = wc_create_new_customer( $entity->get_content(), 'username-' . time(), 'hunter2' );
		} else {
			$content_id = wp_insert_post( [ 'post_title' => $entity->get_content() ] );
		}
		if ( $content_id ) {
			$entity->set_id( $content_id );
		}

		$entity->apply_changes();
	}

	/**
	 * Simple read.
	 */
	public function read( &$entity ) {
		$entity->set_defaults();
		$id = $entity->get_id();

		if ( empty( $id ) ) {
			return;
		}

		if ( 'user' === $this->meta_type ) {
			$user_object = get_userdata( $id );
			if ( ! $user_object ) {
				return;
			}
			$entity->set_content( $user_object->user_email );
		} else {
			$post_object = get_post( $id );
			if ( ! $post_object ) {
				return;
			}
			$entity->set_content( $post_object->post_title );
		}

		$entity->read_meta_data();
		$entity->set_object_read( true );
	}

	/**
	 * Simple update.
	 */
	public function update( &$entity ) {
		global $wpdb;
		$content_id = $entity->get_id();

		if ( 'user' === $this->meta_type ) {
			wp_update_user(
				[
					'ID'         => $content_id,
					'user_email' => $entity->get_content(),
				]
			);
		} else {
			wp_update_post(
				[
					'ID'         => $content_id,
					'post_title' => $entity->get_content(),
				]
			);
		}
	}

	/**
	 * Simple delete.
	 */
	public function delete( &$entity, $args = [] ) {
		if ( 'user' === $this->meta_type ) {
			wp_delete_user( $entity->get_id() );
		} else {
			wp_delete_post( $entity->get_id() );
		}

		$entity->set_id( 0 );
	}
}
