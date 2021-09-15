<?php
/**
 * WCS_Progress_Aware_Updater
 *
 * Provide APIs to get the progress of updater that runs in background process.
 *
 * @author   WooCommerce
 * @category Admin
 * @package  WooCommerce Subscriptions/Admin/Upgrades
 * @since    3.1.4
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class WCS_Progress_Aware_Updater extends WCS_Background_Repairer {

	/**
	 * Attaches callback to count updated item.
	 *
	 * @since 3.1.4
	 * @see WCS_Background_Repairer::init() for additional hooks and callbacks.
	 */
	public function init() {
		parent::init();
		add_action( $this->repair_hook, array( $this, 'increase_updated_count' ) );
	}

	private function get_updated_count() {
		return absint( get_option( "{$this->repair_hook}_updated_count", 0 ) );
	}

	/**
	 * Increase a counter of updated item.
	 */
	protected function increase_updated_count() {
		set_option( "{$this->repair_hook}_updated_count", $this->get_updated_count() + 1 );
		set_option( "{$this->repair_hook}_last_updated", time() );
	}

	/**
	 * Returns total items to update, number of updated items, is done boolean, last updated and next update timestamp.
	 */
	public function get_progress() {
		$total       = $this->get_items_to_update_count();
		$updated     = $this->get_updated_count();
		$last_update = get_option( "{$this->repair_hook}_last_updated" );;

		$next_update        = null;
		$next_repair_hook   = as_next_scheduled_action( $this->repair_hook );
		$next_schedule_hook = as_next_scheduled_action( $this->scheduled_hook );
		if ( is_numeric( $next_repair_hook ) ) {
			$next_update = $next_repair_hook;
		}
		if ( is_null( $next_update ) || is_numeric( $next_schedule_hook ) ) {
			$next_update = min( $next_update, $next_schedule_hook );
		}

		return [
			'total'       => $total,
			'updated'     => $updated,
			'is_done'     => $updated >= $total,
			'last_update' => $last_update,
			'next_update' => $next_update,
		];
	}

	/**
	 * Total count of items to be updated before this process is considered finished.
	 */
	abstract protected function get_items_to_update_count( );
}
