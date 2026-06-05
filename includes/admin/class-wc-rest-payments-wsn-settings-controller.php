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
 * Extends WC_Payments_REST_Controller to inherit `check_permission()` and the
 * shared `$namespace` default (plus any future cross-cutting behavior the base
 * accrues). The base class requires a WC_Payments_API_Client at construction;
 * this controller never invokes the client at runtime — its whole flow is local
 * wp_options reads/writes — but the dependency is injected to satisfy the base
 * contract. See `WSN_Hub::register_rest_controllers()` for the matching rationale.
 *
 * PUT semantics: accepts any subset of the settings keys. Validation runs in two tiers:
 *
 * 1. WP REST's `args` schema (enum/format/type) runs `rest_validate_request_arg` BEFORE
 *    the callback executes. Failures here return 400 and reject the entire request —
 *    sibling fields are NOT persisted. Fields validated at this tier: all type
 *    declarations.
 *
 * 2. Setter-level rejections inside the callback (`refund_page_id` not pointing to a
 *    published page, `hero_image_id` / `logo_override_id` not resolving to image
 *    attachments) collect errors per-field and return 422 — sibling fields that
 *    succeeded ARE persisted, and the response body's `errors` map carries per-field
 *    detail.
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
	 * Product visibility / catalog exposure is handled by WC's native
	 * product-catalog-visibility settings (not by this controller), so there are no
	 * visibility-related fields here.
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
	 * Throttle window for the Retry-button-backed force-resync endpoint.
	 *
	 * Action Scheduler already dedupes the resulting `wcpay_wsn_profile_push`
	 * action (rapid clicks collapse to one push), but the AS dedup happens
	 * at row-write time — each call still costs an unschedule + schedule
	 * round-trip. This site-wide throttle spares the DB that churn under
	 * button-mashing and matches the emitter's natural debounce window.
	 *
	 * @var int
	 */
	const RESYNC_THROTTLE_SECONDS = MINUTE_IN_SECONDS;

	/**
	 * Transient key for the Retry-button throttle. Site-scoped — multisite
	 * networks rate-limit per-site, not globally, which is correct for the
	 * abuse model (one merchant per site).
	 *
	 * @var string
	 */
	const RESYNC_THROTTLE_TRANSIENT = 'wsn_profile_resync_throttle';

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

		// Sibling route — manual "Retry sync" trigger backing the Profile-tab
		// sync-state badge. Fires `wcpay_wsn_profile_force_resync` which the
		// emitter listens for. Separate route (not a query param on the PUT)
		// because its semantics are "fire a push with no data write" — the
		// settings PUT only fires a push as a side-effect of changed Profile
		// fields. Conflating would require a synthetic-edit code path.
		register_rest_route(
			$this->namespace,
			'/payments/wsn/profile-resync',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'force_resync' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
	}

	/**
	 * GET handler — returns the full settings blob plus the feature flag state
	 * AND resolved derivations the Profile tab UI needs (logo URL, hero URL,
	 * synced shop name/tagline, shipping regions, free-shipping summary, refund
	 * page label). Bundling derivations into the same response avoids two
	 * round-trips at Profile tab mount time.
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
				'derivations'     => $this->compute_derivations(),
				'sync'            => $this->compute_sync_state(),
			]
		);
	}

	/**
	 * Compute the Profile-tab derivations.
	 *
	 * Thin delegator to `WSN_Derivations::compute()`. Lives separately so
	 * the upcoming Profile sync emitter (RSM-3945) can call the same
	 * derivation logic without instantiating this controller in a
	 * background-job context.
	 *
	 * @return array
	 */
	private function compute_derivations(): array {
		return WSN_Derivations::compute();
	}

	/**
	 * Compute the Profile-sync state block surfaced to the Hub UI.
	 *
	 * Thin read of the emitter's static accessors. Bundled into the same
	 * GET response as `settings` + `derivations` so the Profile tab can
	 * render the sync-state badge on initial paint without a second
	 * round-trip. All three accessors are safe to call regardless of the
	 * emitter sub-flag — they return null / empty-string defaults when
	 * the emitter has never run.
	 *
	 * `debounce_seconds` is included so the front-end Retry handler can
	 * time its optimistic refresh against the actual AS debounce window
	 * (currently 60s but the constant is the source of truth).
	 *
	 * @return array{last_synced: int|null, last_synced_version: string, last_error: array|null, debounce_seconds: int}
	 */
	private function compute_sync_state(): array {
		return [
			'last_synced'         => WSN_Profile_Emitter::get_last_synced_time(),
			'last_synced_version' => WSN_Profile_Emitter::get_last_synced_version(),
			'last_error'          => WSN_Profile_Emitter::get_last_error(),
			'debounce_seconds'    => WSN_Profile_Emitter::DEBOUNCE_SECONDS,
		];
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

		// Include derivations in the PUT response so the client can replace
		// its overlay state (pendingMediaUrls / pre-save settings) with the
		// server's authoritative resolved values. Without this, save-success
		// handlers that null-out their overlays would render NO image because
		// derivations.{logo,hero}_url would be undefined post-save until the
		// next GET — the merchant sees the preview vanish then reappear on
		// refresh. Shape must mirror get_settings().
		$response_body = [
			'settings'        => WSN_Settings::get_all(),
			'feature_enabled' => WC_Payments_Features::is_wsn_hub_enabled(),
			'derivations'     => $this->compute_derivations(),
		];

		if ( ! empty( $errors ) ) {
			$response_body['errors'] = $errors;
			// `params` is the standard WP-REST validation envelope location —
			// the client-side `formatApiError` reads `error.data.params` to
			// surface per-field detail. Without this key, field-level errors
			// are silently dropped at the boundary. `body` is kept too because
			// callers that read the partial-write echo (settings + derivations)
			// for state reconciliation still rely on that shape.
			return new WP_Error(
				'wcpay_wsn_validation_failed',
				__( 'Some fields could not be saved.', 'woocommerce-payments' ),
				[
					'status' => 422,
					'params' => $errors,
					'body'   => $response_body,
				]
			);
		}

		return rest_ensure_response( $response_body );
	}

	/**
	 * POST handler — fire an immediate Profile push, bypassing the 60s debounce.
	 *
	 * Backs the Profile-tab "Retry sync" button (RSM-3945, sync-state UI wireup).
	 * Returns 202 Accepted because the push itself runs asynchronously through
	 * Action Scheduler — by the time the response goes out, the AS row is
	 * scheduled but not yet executed. Clients refresh GET settings after the
	 * emitter's debounce window to see the new sync state.
	 *
	 * Failure modes:
	 *  - **503** when `_wcpay_feature_wsn_profile_emitter` is off. No emitter
	 *    listener exists to handle the action; firing it would be a silent no-op
	 *    and the merchant would see no state change. Better to surface the gate.
	 *  - **429** when called within RESYNC_THROTTLE_SECONDS of the previous call.
	 *    Includes a `Retry-After` header with the remaining seconds. The throttle
	 *    is site-wide (one transient key per WP_options table) — matches the
	 *    abuse model (one merchant per site).
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function force_resync( WP_REST_Request $request ) {
		unset( $request );

		if ( ! WC_Payments_Features::is_wsn_profile_emitter_enabled() ) {
			return new WP_Error(
				'wsn_profile_emitter_disabled',
				__( 'Profile sync is disabled by feature flag.', 'woocommerce-payments' ),
				[ 'status' => 503 ]
			);
		}

		$throttle_expires = (int) get_transient( self::RESYNC_THROTTLE_TRANSIENT );
		if ( $throttle_expires > time() ) {
			$remaining = max( 1, $throttle_expires - time() );
			// `WP_REST_Response` (not `WP_Error`) so the `Retry-After` header
			// propagates. WP's `WP_REST_Server::error_to_response` does not
			// forward custom headers from a WP_Error's additional_data. The
			// body mirrors WP's error envelope shape so apiFetch consumers can
			// treat it identically to a WP_Error.
			$response = new WP_REST_Response(
				[
					'code'    => 'wsn_profile_resync_throttled',
					'message' => __( 'Resync requested too recently. Try again in a moment.', 'woocommerce-payments' ),
					'data'    => [ 'status' => 429 ],
				],
				429
			);
			$response->header( 'Retry-After', (string) $remaining );
			return $response;
		}

		// Throttle: store the wall-clock instant the throttle EXPIRES (not just
		// a sentinel "1"), so the 429 branch can compute Retry-After remaining
		// without a second `get_option` call.
		set_transient(
			self::RESYNC_THROTTLE_TRANSIENT,
			time() + self::RESYNC_THROTTLE_SECONDS,
			self::RESYNC_THROTTLE_SECONDS
		);

		/**
		 * Fires when the Profile-tab Retry button (or any other manual trigger)
		 * requests an immediate Profile-sync push. The emitter is the canonical
		 * listener; it calls `force_immediate_push()` which schedules the AS
		 * action at time() (vs the default 60s debounce).
		 *
		 * @since 10.8.0
		 */
		do_action( 'wcpay_wsn_profile_force_resync' );

		$response = rest_ensure_response(
			[
				'status'         => 'scheduled',
				'rescheduled_at' => time(),
			]
		);
		$response->set_status( 202 );
		return $response;
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
			'enabled'          => [
				'description' => __( 'Whether the merchant has opted in to the Shopping Network.', 'woocommerce-payments' ),
				'type'        => 'boolean',
			],
			'hero_image_id'    => [
				'description' => __( 'Hero banner attachment ID, or null to clear.', 'woocommerce-payments' ),
				'type'        => [ 'integer', 'null' ],
			],
			'logo_override_id' => [
				'description' => __( 'Logo override attachment ID, or null to use the site logo.', 'woocommerce-payments' ),
				'type'        => [ 'integer', 'null' ],
			],
			'contact_email'    => [
				// Three-state, matching WSN_Settings::set_contact_email:
				// null = clear override, fall back to default_contact_email derivation
				// ""   = explicit "no contact email" (preserved as override)
				// email = explicit override, validated by sanitize_email in the setter
				// `format=email` would reject "" outright, so it's omitted here
				// and the setter does the final sanitize_email() validation.
				'description' => __( 'Merchant contact email override. Null = use WC-derived default, empty string = explicit "no contact", otherwise an email address.', 'woocommerce-payments' ),
				'type'        => [ 'string', 'null' ],
			],
			'refund_page_id'   => [
				'description' => __( 'Page ID of the published refund policy page.', 'woocommerce-payments' ),
				'type'        => [ 'integer', 'null' ],
			],
		];
	}
}
