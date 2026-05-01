<?php
/**
 * Class Dispute_Readiness_Service
 *
 * @package WooCommerce\Payments
 */

declare( strict_types=1 );

namespace WCPay\Disputes;

/**
 * Builds the dispute readiness overview payload.
 */
class Dispute_Readiness_Service {
	public const DISMISSAL_OPTION = 'wcpay_dispute_readiness_card_dismissed';

	private const SIGNAL_REFUND_POLICY        = 'refund_policy';
	private const SIGNAL_TERMS_AND_CONDITIONS = 'terms_and_conditions';
	private const SIGNAL_STATEMENT_DESCRIPTOR = 'statement_descriptor';
	private const SIGNAL_SUPPORT_CONTACT      = 'support_contact';

	private const STATUS_COMPLETE   = 'complete';
	private const STATUS_INCOMPLETE = 'incomplete';
	private const STATUS_UNKNOWN    = 'unknown';

	/**
	 * Returns the disabled response used while the feature flag is off.
	 *
	 * @return array
	 */
	public function get_disabled_overview_payload(): array {
		return [
			'overview' => [
				'enabled'             => false,
				'hidden'              => true,
				'score'               => 0,
				'total'               => 0,
				'state'               => self::STATUS_INCOMPLETE,
				'isDismissed'         => true,
				'completeSignalIds'   => [],
				'incompleteSignalIds' => [],
				'signals'             => [],
				'dismissal'           => [
					'isDismissed'       => true,
					'isStoredDismissal' => false,
					'reappearReason'    => 'feature_disabled',
				],
			],
		];
	}

	/**
	 * Builds the enabled overview payload.
	 *
	 * @return array
	 */
	public function get_overview_payload(): array {
		$signals               = $this->get_signals();
		$total                 = count( $signals );
		$complete_signal_ids   = [];
		$incomplete_signal_ids = [];

		foreach ( $signals as $signal ) {
			if ( self::STATUS_COMPLETE === $signal['status'] ) {
				$complete_signal_ids[] = $signal['id'];
			} else {
				$incomplete_signal_ids[] = $signal['id'];
			}
		}

		$score     = count( $complete_signal_ids );
		$dismissal = $this->get_dismissal_state( $score, $total );

		return [
			'overview' => [
				'enabled'             => true,
				'hidden'              => false,
				'score'               => $score,
				'total'               => $total,
				'state'               => $score === $total ? self::STATUS_COMPLETE : self::STATUS_INCOMPLETE,
				'isDismissed'         => $dismissal['isDismissed'],
				'completeSignalIds'   => $complete_signal_ids,
				'incompleteSignalIds' => $incomplete_signal_ids,
				'signals'             => $signals,
				'dismissal'           => $dismissal,
			],
		];
	}

	/**
	 * Stores dismissal metadata for the current overview state.
	 *
	 * @return array Updated overview payload.
	 */
	public function dismiss_overview_card(): array {
		$payload  = $this->get_overview_payload();
		$overview = $payload['overview'];

		update_option(
			self::DISMISSAL_OPTION,
			[
				'dismissed'             => true,
				'dismissed_at'          => gmdate( 'c' ),
				'score_at_dismissal'    => $overview['score'],
				'total_at_dismissal'    => $overview['total'],
				'incomplete_signal_ids' => $overview['incompleteSignalIds'],
			],
			false
		);

		return $this->get_overview_payload();
	}

	/**
	 * Builds all v1 overview signals.
	 *
	 * @return array[]
	 */
	private function get_signals(): array {
		$account_data = $this->get_cached_account_data();

		return [
			$this->get_refund_policy_signal(),
			$this->get_terms_and_conditions_signal(),
			$this->get_statement_descriptor_signal( $account_data ),
			$this->get_support_contact_signal( $account_data ),
		];
	}

	/**
	 * Returns the refund policy signal.
	 *
	 * @return array
	 */
	private function get_refund_policy_signal(): array {
		$page_id = (int) get_option( 'woocommerce_refund_returns_page_id', 0 );
		$status  = $this->is_complete_page( $page_id ) ? self::STATUS_COMPLETE : self::STATUS_INCOMPLETE;

		return [
			'id'          => self::SIGNAL_REFUND_POLICY,
			'status'      => $status,
			'label'       => __( 'Refund policy', 'woocommerce-payments' ),
			'description' => self::STATUS_COMPLETE === $status
				? __( 'A published refund and returns policy page is set.', 'woocommerce-payments' )
				: __( 'Add a clear refund and returns policy to help set customer expectations.', 'woocommerce-payments' ),
			'actionLabel' => __( 'Fix', 'woocommerce-payments' ),
			'actionUrl'   => $this->get_page_action_url( $page_id ),
			'reason'      => self::STATUS_COMPLETE === $status ? 'page_assigned_published_non_empty' : 'missing_or_incomplete_page',
		];
	}

	/**
	 * Returns the terms and conditions signal.
	 *
	 * @return array
	 */
	private function get_terms_and_conditions_signal(): array {
		$page_id = function_exists( 'wc_terms_and_conditions_page_id' ) ? (int) wc_terms_and_conditions_page_id() : (int) get_option( 'woocommerce_terms_page_id', 0 );
		$status  = $this->is_complete_page( $page_id ) ? self::STATUS_COMPLETE : self::STATUS_INCOMPLETE;

		return [
			'id'          => self::SIGNAL_TERMS_AND_CONDITIONS,
			'status'      => $status,
			'label'       => __( 'Terms and conditions', 'woocommerce-payments' ),
			'description' => self::STATUS_COMPLETE === $status
				? __( 'A published terms and conditions page is set.', 'woocommerce-payments' )
				: __( 'Add store terms so customers can review policies before buying.', 'woocommerce-payments' ),
			'actionLabel' => __( 'Fix', 'woocommerce-payments' ),
			'actionUrl'   => $this->get_page_action_url( $page_id ),
			'reason'      => self::STATUS_COMPLETE === $status ? 'page_assigned_published_non_empty' : 'missing_or_incomplete_page',
		];
	}

	/**
	 * Returns the statement descriptor signal.
	 *
	 * @param array $account_data Cached account data.
	 * @return array
	 */
	private function get_statement_descriptor_signal( array $account_data ): array {
		$descriptor = isset( $account_data['statement_descriptor'] ) ? (string) $account_data['statement_descriptor'] : '';
		$is_default = $this->is_default_like_descriptor( $descriptor, $account_data );
		$status     = '' !== trim( $descriptor ) && ! $is_default ? self::STATUS_COMPLETE : self::STATUS_INCOMPLETE;

		return [
			'id'          => self::SIGNAL_STATEMENT_DESCRIPTOR,
			'status'      => $status,
			'label'       => __( 'Statement descriptor', 'woocommerce-payments' ),
			'description' => self::STATUS_COMPLETE === $status
				? __( 'Your customer bank statement descriptor appears to be customized.', 'woocommerce-payments' )
				: __( 'Review the descriptor customers may see on their bank statements.', 'woocommerce-payments' ),
			'actionLabel' => __( 'Review', 'woocommerce-payments' ),
			'actionUrl'   => admin_url( 'admin.php?page=wc-settings&tab=checkout&section=woocommerce_payments' ),
			'reason'      => self::STATUS_COMPLETE === $status ? 'customized' : ( '' === trim( $descriptor ) ? 'empty' : 'default_like' ),
		];
	}

	/**
	 * Returns the support contact signal.
	 *
	 * @param array $account_data Cached account data.
	 * @return array
	 */
	private function get_support_contact_signal( array $account_data ): array {
		$business_profile = isset( $account_data['business_profile'] ) && is_array( $account_data['business_profile'] ) ? $account_data['business_profile'] : [];
		$support_email    = isset( $business_profile['support_email'] ) ? trim( (string) $business_profile['support_email'] ) : '';
		$support_phone    = isset( $business_profile['support_phone'] ) ? trim( (string) $business_profile['support_phone'] ) : '';
		$status           = '' !== $support_email || '' !== $support_phone ? self::STATUS_COMPLETE : self::STATUS_INCOMPLETE;

		return [
			'id'          => self::SIGNAL_SUPPORT_CONTACT,
			'status'      => $status,
			'label'       => __( 'Support contact', 'woocommerce-payments' ),
			'description' => self::STATUS_COMPLETE === $status
				? __( 'A customer support email or phone number is available.', 'woocommerce-payments' )
				: __( 'Add a customer support email or phone number for payment questions.', 'woocommerce-payments' ),
			'actionLabel' => __( 'Review', 'woocommerce-payments' ),
			'actionUrl'   => admin_url( 'admin.php?page=wc-settings&tab=checkout&section=woocommerce_payments' ),
			'reason'      => self::STATUS_COMPLETE === $status ? 'support_contact_present' : 'missing_support_contact',
		];
	}

	/**
	 * Returns cached account data safely.
	 *
	 * @return array
	 */
	private function get_cached_account_data(): array {
		if ( ! class_exists( '\WC_Payments' ) || ! \WC_Payments::get_account_service() ) {
			return [];
		}

		$account_data = \WC_Payments::get_account_service()->get_cached_account_data();

		return is_array( $account_data ) ? $account_data : [];
	}

	/**
	 * Determines whether a configured page exists, is published, and has meaningful content.
	 *
	 * @param int $page_id Page ID.
	 * @return bool
	 */
	private function is_complete_page( int $page_id ): bool {
		if ( $page_id <= 0 ) {
			return false;
		}

		$page = get_post( $page_id );
		if ( ! $page || 'publish' !== $page->post_status ) {
			return false;
		}

		$content = strip_shortcodes( (string) $page->post_content );
		$content = wp_strip_all_tags( $content );
		$content = preg_replace( '/\s+/', '', $content );

		return '' !== $content;
	}

	/**
	 * Returns the edit URL for an assigned page or the WooCommerce page setup settings URL.
	 *
	 * @param int $page_id Page ID.
	 * @return string
	 */
	private function get_page_action_url( int $page_id ): string {
		if ( $page_id > 0 && get_post( $page_id ) ) {
			$edit_post_link = get_edit_post_link( $page_id, '' );

			return $edit_post_link ? $edit_post_link : admin_url( 'post.php?post=' . $page_id . '&action=edit' );
		}

		return admin_url( 'admin.php?page=wc-settings&tab=advanced&section=page_setup' );
	}

	/**
	 * Returns the current dismissal state.
	 *
	 * @param int $score Current score.
	 * @param int $total Current total.
	 * @return array
	 */
	private function get_dismissal_state( int $score, int $total ): array {
		$stored = get_option( self::DISMISSAL_OPTION, [] );
		if ( ! is_array( $stored ) || empty( $stored['dismissed'] ) ) {
			return [
				'isDismissed'       => false,
				'isStoredDismissal' => false,
				'reappearReason'    => null,
			];
		}

		$score_at_dismissal = isset( $stored['score_at_dismissal'] ) ? (int) $stored['score_at_dismissal'] : 0;
		$total_at_dismissal = isset( $stored['total_at_dismissal'] ) ? (int) $stored['total_at_dismissal'] : 0;
		$reappear_reason    = null;

		if ( $total !== $total_at_dismissal ) {
			$reappear_reason = 'total_changed';
		} elseif ( $score < $score_at_dismissal ) {
			$reappear_reason = 'score_decreased';
		}

		return [
			'isDismissed'         => null === $reappear_reason,
			'isStoredDismissal'   => true,
			'reappearReason'      => $reappear_reason,
			'dismissedAt'         => isset( $stored['dismissed_at'] ) ? (string) $stored['dismissed_at'] : null,
			'scoreAtDismissal'    => $score_at_dismissal,
			'totalAtDismissal'    => $total_at_dismissal,
			'incompleteSignalIds' => isset( $stored['incomplete_signal_ids'] ) && is_array( $stored['incomplete_signal_ids'] ) ? array_values( $stored['incomplete_signal_ids'] ) : [],
		];
	}

	/**
	 * Detects default-like statement descriptors using normalized comparison values.
	 *
	 * @param string $descriptor   Statement descriptor.
	 * @param array  $account_data Cached account data.
	 * @return bool
	 */
	private function is_default_like_descriptor( string $descriptor, array $account_data ): bool {
		$normalized_descriptor = $this->normalize_descriptor_value( $descriptor );
		if ( '' === $normalized_descriptor ) {
			return true;
		}

		$candidates = array_merge(
			[
				get_bloginfo( 'name' ),
				get_home_url(),
				get_site_url(),
				'woocommerce',
				'woopayments',
				'woopaymentsstore',
				'mystore',
				'teststore',
			],
			$this->get_url_candidates( get_home_url() ),
			$this->get_url_candidates( get_site_url() )
		);

		$business_profile = isset( $account_data['business_profile'] ) && is_array( $account_data['business_profile'] ) ? $account_data['business_profile'] : [];
		if ( ! empty( $business_profile['url'] ) ) {
			$candidates = array_merge( $candidates, $this->get_url_candidates( (string) $business_profile['url'] ) );
		}

		foreach ( array_unique( array_filter( $candidates ) ) as $candidate ) {
			if ( $normalized_descriptor === $this->normalize_descriptor_value( (string) $candidate ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Returns host/path candidates for a URL.
	 *
	 * @param string $url URL.
	 * @return array
	 */
	private function get_url_candidates( string $url ): array {
		$host = (string) wp_parse_url( $url, PHP_URL_HOST );
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );

		return array_filter(
			[
				$url,
				$host,
				preg_replace( '/^www\./i', '', $host ),
				trim( $path, '/' ),
			]
		);
	}

	/**
	 * Normalizes values for statement descriptor comparison.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private function normalize_descriptor_value( string $value ): string {
		$value = strtolower( trim( $value ) );
		$value = preg_replace( '#^https?://#', '', $value );
		$value = preg_replace( '#^www\.#', '', $value );
		$value = preg_replace( '/[^a-z0-9]/', '', $value );

		return is_string( $value ) ? $value : '';
	}
}
