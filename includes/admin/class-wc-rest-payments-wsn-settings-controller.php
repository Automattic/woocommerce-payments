<?php
/**
 * Class WC_REST_Payments_WSN_Settings_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for the Woo Shopping Network Hub settings.
 *
 * Routes:
 *   GET  /wp-json/wc/v3/payments/wsn/settings  — full settings blob
 *   PUT  /wp-json/wc/v3/payments/wsn/settings  — partial updates accepted
 *
 * Extends WP_REST_Controller directly (not WC_Payments_REST_Controller) because all
 * operations are local wp_options reads/writes — there's no WC_Payments_API_Client
 * dependency to inject.
 *
 * PUT semantics: accepts any subset of the settings keys. Validation runs in two tiers:
 *
 * 1. WP REST's `args` schema (enum/format/type) runs `rest_validate_request_arg` BEFORE
 *    the callback executes. Failures here return 400 and reject the entire request —
 *    sibling fields are NOT persisted. Fields validated at this tier: `visibility_mode`
 *    (enum), `contact_email` (format=email), and all type declarations.
 *
 * 2. Setter-level rejections inside the callback (e.g., `visibility_product_ids` over
 *    cap, `refund_page_id` not pointing to a published page, `hero_image_id` /
 *    `logo_override_id` not resolving to image attachments) collect errors per-field
 *    and return 422 — sibling fields that succeeded ARE persisted, and the response
 *    body's `errors` map carries per-field detail.
 *
 * After persisting, if any Profile-tab field changed, fires the `wcpay_wsn_profile_changed`
 * action so the outbound emitter (RSM-3945) can react.
 */
class WC_REST_Payments_WSN_Settings_Controller extends WC_Payments_REST_Controller {

	/**
	 * Endpoint path under the namespace. ($namespace is inherited from the base class.)
	 *
	 * @var string
	 */
	protected $rest_base = 'payments/wsn/settings';

	/**
	 * The Profile-tab field keys that, when changed by a PUT, fire `wcpay_wsn_profile_changed`.
	 *
	 * Visibility-tab keys (`visibility_*`) are NOT in this list — they flow through the
	 * Jetpack Sync option whitelist (RSM-3946), not the Profile emitter.
	 *
	 * @var string[]
	 */
	const PROFILE_FIELDS = [
		'hero_image_id',
		'logo_override_id',
		'contact_email',
		'refund_page_id',
	];

	/**
	 * Registers REST routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_settings' ],
					'permission_callback' => [ $this, 'check_permission' ],
				],
				[
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'update_settings' ],
					'permission_callback' => [ $this, 'check_permission' ],
					'args'                => $this->get_update_args(),
				],
				'schema' => [ $this, 'get_public_item_schema' ],
			]
		);
	}

	/**
	 * GET handler — returns the full settings blob plus the feature flag state.
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @return WP_REST_Response
	 */
	public function get_settings( WP_REST_Request $request ) {
		unset( $request );

		return rest_ensure_response(
			[
				'settings'        => WSN_Settings::get_all(),
				'feature_enabled' => WC_Payments_Features::is_wsn_hub_enabled(),
			]
		);
	}

	/**
	 * PUT handler — partial update. Only fields present in the request are touched.
	 *
	 * Each setter validates independently. Validation failures are collected and returned
	 * as a 422 alongside the (partially-applied) state, so the UI can surface field-level
	 * errors without losing the writes that did succeed.
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_settings( WP_REST_Request $request ) {
		$before_profile = $this->snapshot_profile_fields();
		$errors         = [];

		if ( $request->has_param( 'enabled' ) ) {
			WSN_Settings::set_enabled( (bool) $request->get_param( 'enabled' ) );
		}

		if ( $request->has_param( 'visibility_mode' ) ) {
			if ( ! WSN_Settings::set_visibility_mode( (string) $request->get_param( 'visibility_mode' ) ) ) {
				$errors['visibility_mode'] = __( 'Invalid visibility mode.', 'woocommerce-payments' );
			}
		}

		if ( $request->has_param( 'visibility_terms' ) ) {
			$terms = $request->get_param( 'visibility_terms' );
			if ( is_array( $terms ) ) {
				WSN_Settings::set_visibility_terms( $terms );
			} else {
				$errors['visibility_terms'] = __( 'Visibility terms must be an object.', 'woocommerce-payments' );
			}
		}

		if ( $request->has_param( 'visibility_product_ids' ) ) {
			$ids = $request->get_param( 'visibility_product_ids' );
			if ( ! is_array( $ids ) || ! WSN_Settings::set_visibility_product_ids( $ids ) ) {
				$errors['visibility_product_ids'] = sprintf(
					/* translators: %d: maximum number of product IDs allowed. */
					__( 'Visibility product IDs must be an array of at most %d positive integers.', 'woocommerce-payments' ),
					WSN_Settings::MAX_SPECIFIC_PRODUCT_IDS
				);
			}
		}

		if ( $request->has_param( 'hero_image_id' ) ) {
			$hero_id = $request->get_param( 'hero_image_id' );
			if ( ! WSN_Settings::set_hero_image_id( null === $hero_id ? null : (int) $hero_id ) ) {
				$errors['hero_image_id'] = __( 'Hero image must reference an image attachment.', 'woocommerce-payments' );
			}
		}

		if ( $request->has_param( 'logo_override_id' ) ) {
			$logo_id = $request->get_param( 'logo_override_id' );
			if ( ! WSN_Settings::set_logo_override_id( null === $logo_id ? null : (int) $logo_id ) ) {
				$errors['logo_override_id'] = __( 'Logo override must reference an image attachment.', 'woocommerce-payments' );
			}
		}

		if ( $request->has_param( 'contact_email' ) ) {
			$email = $request->get_param( 'contact_email' );
			if ( ! WSN_Settings::set_contact_email( null === $email ? null : (string) $email ) ) {
				$errors['contact_email'] = __( 'Invalid email address.', 'woocommerce-payments' );
			}
		}

		if ( $request->has_param( 'refund_page_id' ) ) {
			$page_id = $request->get_param( 'refund_page_id' );
			if ( ! WSN_Settings::set_refund_page_id( null === $page_id ? null : (int) $page_id ) ) {
				$errors['refund_page_id'] = __( 'Refund page must reference a published page.', 'woocommerce-payments' );
			}
		}

		$this->maybe_fire_profile_changed( $before_profile );

		$response_body = [
			'settings'        => WSN_Settings::get_all(),
			'feature_enabled' => WC_Payments_Features::is_wsn_hub_enabled(),
		];

		if ( ! empty( $errors ) ) {
			$response_body['errors'] = $errors;
			return new WP_Error(
				'wcpay_wsn_validation_failed',
				__( 'Some fields could not be saved.', 'woocommerce-payments' ),
				[
					'status' => 422,
					'body'   => $response_body,
				]
			);
		}

		return rest_ensure_response( $response_body );
	}

	/**
	 * Snapshot of the Profile-relevant fields before mutation. Used to detect
	 * post-mutation changes so we only fire the action when something actually changed.
	 *
	 * @return array
	 */
	private function snapshot_profile_fields(): array {
		return [
			'hero_image_id'    => WSN_Settings::get_hero_image_id(),
			'logo_override_id' => WSN_Settings::get_logo_override_id(),
			'contact_email'    => WSN_Settings::get_contact_email(),
			'refund_page_id'   => WSN_Settings::get_refund_page_id(),
		];
	}

	/**
	 * Fires the `wcpay_wsn_profile_changed` action when any Profile field's value
	 * differs from the pre-mutation snapshot.
	 *
	 * The action signature passes the new + old values so listeners (e.g., the emitter
	 * in RSM-3945) can decide whether to re-fire the outbound push.
	 *
	 * @param array $before Pre-mutation values keyed by Profile field name.
	 */
	private function maybe_fire_profile_changed( array $before ): void {
		$after = $this->snapshot_profile_fields();

		foreach ( self::PROFILE_FIELDS as $field ) {
			if ( ( $before[ $field ] ?? null ) !== ( $after[ $field ] ?? null ) ) {
				/**
				 * Fires when any Profile-tab field changes via the settings REST endpoint.
				 *
				 * Consumers: WSN Profile Emitter (RSM-3945) for the outbound push to WooPay.
				 *
				 * @param array $after  Post-mutation values keyed by Profile field name.
				 * @param array $before Pre-mutation values for the same keys.
				 */
				do_action( 'wcpay_wsn_profile_changed', $after, $before );
				return;
			}
		}
	}

	/**
	 * Validation schema for PUT params. Every field is optional — partial updates accepted.
	 *
	 * @return array
	 */
	private function get_update_args(): array {
		return [
			'enabled'                => [
				'description' => __( 'Whether the merchant has opted in to the Shopping Network.', 'woocommerce-payments' ),
				'type'        => 'boolean',
			],
			'visibility_mode'        => [
				'description' => __( 'Product visibility mode.', 'woocommerce-payments' ),
				'type'        => 'string',
				'enum'        => WSN_Settings::valid_visibility_modes(),
			],
			'visibility_terms'       => [
				'description' => __( 'Selected taxonomy term IDs.', 'woocommerce-payments' ),
				'type'        => 'object',
			],
			'visibility_product_ids' => [
				'description' => __( 'Explicit product ID whitelist for the specific-products mode.', 'woocommerce-payments' ),
				'type'        => 'array',
				'items'       => [ 'type' => 'integer' ],
			],
			'hero_image_id'          => [
				'description' => __( 'Hero banner attachment ID, or null to clear.', 'woocommerce-payments' ),
				'type'        => [ 'integer', 'null' ],
			],
			'logo_override_id'       => [
				'description' => __( 'Logo override attachment ID, or null to use the site logo.', 'woocommerce-payments' ),
				'type'        => [ 'integer', 'null' ],
			],
			'contact_email'          => [
				'description' => __( 'Merchant-curated contact email.', 'woocommerce-payments' ),
				'type'        => [ 'string', 'null' ],
				'format'      => 'email',
			],
			'refund_page_id'         => [
				'description' => __( 'Page ID of the published refund policy page.', 'woocommerce-payments' ),
				'type'        => [ 'integer', 'null' ],
			],
		];
	}
}
