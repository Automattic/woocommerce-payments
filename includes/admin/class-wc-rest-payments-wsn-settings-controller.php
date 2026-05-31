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
			]
		);
	}

	/**
	 * Compute the values the Profile tab needs that aren't stored as WSN
	 * options — they're derived from WC core options, the WP site identity,
	 * the active theme, the shipping zone configuration, or the resolved
	 * attachment URLs.
	 *
	 * Returns null for any field that doesn't resolve so the React UI can
	 * render a placeholder. Never throws — degrades silently when WC isn't
	 * fully loaded.
	 *
	 * @return array
	 */
	private function compute_derivations(): array {
		// Image URLs resolve through wp_get_attachment_url which returns false
		// when the attachment is missing — coerce to null for a cleaner JSON
		// contract on the client.
		$resolve_attachment_url = static function ( ?int $attachment_id ): ?string {
			if ( null === $attachment_id || $attachment_id <= 0 ) {
				return null;
			}
			$url = wp_get_attachment_url( $attachment_id );
			return is_string( $url ) ? $url : null;
		};

		$logo_override_id = WSN_Settings::get_logo_override_id();

		// Build the fallback chain that determines what shows when the
		// merchant hasn't set a WSN override:
		//
		// 1. Site logo — `theme_mod custom_logo` (classic themes) OR
		// `option site_logo` (block / FSE themes set via the Site
		// Editor). Block themes never populate `custom_logo`, so
		// checking only one path silently misses half of installs.
		// Prefer custom_logo when both are set.
		// 2. Site icon (favicon) — many small-store merchants have only
		// a favicon and no proper site logo; using it as a brand
		// mark in WSN is better than rendering "No logo".
		//
		// `default_logo_*` exposes both the URL and the source-of-truth
		// so the editor UI can tell the merchant which fallback is in
		// use ('Using your site logo' vs. 'Using your site icon — set a
		// proper site logo in the Site Editor').
		$site_logo_id = (int) get_theme_mod( 'custom_logo' );
		if ( $site_logo_id <= 0 ) {
			$site_logo_id = (int) get_option( 'site_logo', 0 );
		}

		$default_logo_url    = null;
		$default_logo_source = 'none';
		if ( $site_logo_id > 0 ) {
			$default_logo_url    = $resolve_attachment_url( $site_logo_id );
			$default_logo_source = null === $default_logo_url ? 'none' : 'site_logo';
		}
		if ( null === $default_logo_url ) {
			$site_icon_id = (int) get_option( 'site_icon', 0 );
			if ( $site_icon_id > 0 ) {
				$default_logo_url    = $resolve_attachment_url( $site_icon_id );
				$default_logo_source = null === $default_logo_url ? 'none' : 'site_icon';
			}
		}

		$override_logo_url = $resolve_attachment_url( $logo_override_id );
		$logo_url          = $override_logo_url ?? $default_logo_url;

		$refund_page_id    = WSN_Settings::get_refund_page_id();
		$refund_page_label = null;
		$refund_page_url   = null;
		if ( null !== $refund_page_id && $refund_page_id > 0 ) {
			$post = get_post( $refund_page_id );
			if ( $post instanceof WP_Post && 'publish' === $post->post_status ) {
				$refund_page_label = get_the_title( $post );
				$refund_page_url   = (string) get_permalink( $post );
			}
		}

		// Synced-from-source fields render readonly in the Profile UI — the
		// merchant edits them at their source (WP Settings > General for the
		// site title + tagline via blogname/blogdescription, WC > Shipping for
		// zones). Sending them in this payload prevents the Profile tab from
		// needing additional REST calls.
		$shop_name = (string) get_bloginfo( 'name' );
		$tagline   = (string) get_bloginfo( 'description' );

		$shipping_regions = $this->collect_shipping_region_names();

		return [
			'logo_url'              => $logo_url,
			// `default_logo_url` is what shows when the merchant clears
			// the override — could be the site logo OR the site icon.
			// `default_logo_source` tells the editor which one so the
			// "Synced from …" copy can be accurate ('site_logo' vs
			// 'site_icon' vs 'none').
			'default_logo_url'      => $default_logo_url,
			'default_logo_source'   => $default_logo_source,
			'logo_source'           => null !== $logo_override_id && null !== $override_logo_url
				? 'override'
				: $default_logo_source,
			'hero_image_url'        => $resolve_attachment_url( WSN_Settings::get_hero_image_id() ),
			'shop_name'             => $shop_name,
			'tagline'               => $tagline,
			'default_contact_email' => WSN_Settings::resolve_default_contact_email(),
			'shipping_regions'      => $shipping_regions,
			'free_shipping'         => $this->compute_free_shipping(),
			'refund_page_label'     => $refund_page_label,
			'refund_page_url'       => $refund_page_url,
			'theme_type'            => function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ? 'block' : 'classic',
		];
	}

	/**
	 * Collect human-readable zone names — what the Profile tab shows in the
	 * readonly "Shipping regions" field.
	 *
	 * @return string[]
	 */
	private function collect_shipping_region_names(): array {
		// Use the data-store class directly: `WC()->shipping()` returns
		// WC_Shipping (the singleton), which does NOT have a get_shipping_zones
		// method — that lives on WC_Shipping_Zones (the data class).
		if ( ! class_exists( 'WC_Shipping_Zones' ) ) {
			return [];
		}

		$names = [];
		foreach ( WC_Shipping_Zones::get_zones() as $zone_data ) {
			if ( isset( $zone_data['zone_name'] ) && '' !== $zone_data['zone_name'] ) {
				$names[] = (string) $zone_data['zone_name'];
			}
		}
		return $names;
	}

	/**
	 * Compute the free-shipping summary structure. Returns null when the
	 * summarizer class isn't loaded — defensive against future autoload
	 * changes.
	 *
	 * @return array|null
	 */
	private function compute_free_shipping(): ?array {
		if ( ! class_exists( 'WSN_Free_Shipping_Summarizer' ) ) {
			return null;
		}
		return WSN_Free_Shipping_Summarizer::summarize();
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
				// Three-state, matching WSN_Settings::set_contact_email:
				// null = clear override, fall back to default_contact_email derivation
				// ""   = explicit "no contact email" (preserved as override)
				// email = explicit override, validated by sanitize_email in the setter
				// `format=email` would reject "" outright, so it's omitted here
				// and the setter does the final sanitize_email() validation.
				'description' => __( 'Merchant contact email override. Null = use WC-derived default, empty string = explicit "no contact", otherwise an email address.', 'woocommerce-payments' ),
				'type'        => [ 'string', 'null' ],
			],
			'refund_page_id'         => [
				'description' => __( 'Page ID of the published refund policy page.', 'woocommerce-payments' ),
				'type'        => [ 'integer', 'null' ],
			],
		];
	}
}
