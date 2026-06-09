<?php
/**
 * Class WC_REST_Payments_WSN_Pages_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST controller backing the Profile-tab Refund-policy page picker dropdown.
 *
 * Route:
 *   GET /wp-json/wc/v3/payments/wsn/pages
 *
 * Returns published Pages suitable for selection as the merchant's refund
 * policy. The list is shaped specifically for a refund-policy picker (not a
 * general "list all pages" endpoint) — policy candidates are surfaced first,
 * obviously-functional WooCommerce pages (cart/checkout/shop/my-account) are
 * filtered out entirely.
 *
 * Response shape:
 *
 *     {
 *         "policy_pages": [
 *             { "id": 42, "title": "Refund & Returns Policy", "edit_url": "...", "category": "refund_returns" },
 *             { "id": 43, "title": "Terms & Conditions", "edit_url": "...", "category": "terms" }
 *         ],
 *         "other_pages": [
 *             { "id": 14, "title": "About Us", "edit_url": "..." },
 *             { "id": 19, "title": "Contact",   "edit_url": "..." }
 *         ]
 *     }
 *
 * The React picker renders policy_pages at the top (highlighted as
 * "Suggested") with a divider, then other_pages alphabetical. Either array
 * may be empty.
 */
class WC_REST_Payments_WSN_Pages_Controller extends WC_Payments_REST_Controller {

	/**
	 * Endpoint path under the namespace. ($namespace is inherited.)
	 *
	 * @var string
	 */
	protected $rest_base = 'payments/wsn/pages';

	/**
	 * Hard ceiling on returned pages. Stores with thousands of pages don't need
	 * to ship every one across the wire for a policy-picker dropdown.
	 *
	 * @var int
	 */
	const MAX_PAGES = 200;

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
					'callback'            => [ $this, 'get_pages' ],
					'permission_callback' => [ $this, 'check_permission' ],
				],
			]
		);
	}

	/**
	 * GET handler.
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @return WP_REST_Response
	 */
	public function get_pages( WP_REST_Request $request ) {
		unset( $request );

		$policy_ids    = $this->collect_policy_page_ids();
		$excluded_ids  = $this->collect_excluded_page_ids();
		$all_published = $this->fetch_published_pages();

		$policy_pages = [];
		$other_pages  = [];

		foreach ( $all_published as $page ) {
			$page_id = (int) $page->ID;

			if ( in_array( $page_id, $excluded_ids, true ) ) {
				continue;
			}

			$category = $this->categorize_policy_page( $page_id, $policy_ids );
			if ( null !== $category ) {
				$policy_pages[] = $this->format_page( $page, $category );
			} else {
				$other_pages[] = $this->format_page( $page, null );
			}
		}

		return rest_ensure_response(
			[
				'policy_pages' => $policy_pages,
				'other_pages'  => $other_pages,
			]
		);
	}

	/**
	 * Collect the IDs of WC + WP core "policy-y" pages worth surfacing first.
	 *
	 * Returns a map of `page_id => category-slug` so the response can label
	 * each suggested page with WHY it was suggested (e.g., "Refund & Returns
	 * Policy (your WC refund page)").
	 *
	 * @return array<int, string>
	 */
	private function collect_policy_page_ids(): array {
		$candidates = [
			'refund_returns' => (int) get_option( 'woocommerce_refund_returns_page_id', 0 ),
			'terms'          => (int) get_option( 'woocommerce_terms_page_id', 0 ),
			'privacy_policy' => (int) get_option( 'wp_page_for_privacy_policy', 0 ),
		];

		$resolved = [];
		foreach ( $candidates as $category => $page_id ) {
			if ( $page_id > 0 ) {
				$resolved[ $page_id ] = $category;
			}
		}
		return $resolved;
	}

	/**
	 * Collect IDs of pages that are obviously NOT policy candidates — WC
	 * functional pages (cart/checkout/shop/my-account) + the WP home page.
	 *
	 * @return int[]
	 */
	private function collect_excluded_page_ids(): array {
		$excluded = [
			(int) get_option( 'woocommerce_cart_page_id', 0 ),
			(int) get_option( 'woocommerce_checkout_page_id', 0 ),
			(int) get_option( 'woocommerce_shop_page_id', 0 ),
			(int) get_option( 'woocommerce_myaccount_page_id', 0 ),
			(int) get_option( 'page_on_front', 0 ),
			(int) get_option( 'page_for_posts', 0 ),
		];
		return array_values( array_filter( $excluded, static fn( $id ) => $id > 0 ) );
	}

	/**
	 * Fetch all published pages, alphabetical by title, capped at MAX_PAGES.
	 *
	 * @return WP_Post[]
	 */
	private function fetch_published_pages(): array {
		$pages = get_pages(
			[
				'post_type'   => 'page',
				'post_status' => 'publish',
				'sort_order'  => 'ASC',
				'sort_column' => 'post_title',
				'number'      => self::MAX_PAGES,
			]
		);
		return is_array( $pages ) ? $pages : [];
	}

	/**
	 * Determine whether a page is in the policy-candidates set, and if so
	 * which slot it belongs to.
	 *
	 * Title-based fallback: even if `$policy_ids` doesn't include this page
	 * (e.g., merchant created their own policy page outside the WC onboarding
	 * flow), pages whose title contains "refund", "return", "policy", or
	 * "terms" are surfaced as suggestions too. This catches the merchant who
	 * named their page "Our Pledge" — no, but it catches "Returns Guide" or
	 * "Refund Information" which are common.
	 *
	 * @param int                $page_id    Page ID under inspection.
	 * @param array<int, string> $policy_ids ID => category-slug map from collect_policy_page_ids().
	 * @return string|null Category slug, or null when not a policy page.
	 */
	private function categorize_policy_page( int $page_id, array $policy_ids ): ?string {
		if ( isset( $policy_ids[ $page_id ] ) ) {
			return $policy_ids[ $page_id ];
		}

		$title = html_entity_decode( (string) get_the_title( $page_id ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$lower = strtolower( $title );

		// Word-bounded matches so e.g. "Returnees Profile" doesn't trigger
		// on `return`, "Diplomacy" doesn't trigger on `policy`, etc. Each
		// keyword gets `\b<word>\b` regex anchors — the prior strpos()
		// implementation was a substring match that didn't match the
		// comment's stated intent (would have surfaced "Returnees",
		// "Determinism" → "terms", etc.). Singular + plural forms are
		// listed explicitly because `\b` won't bridge `refund` → "Refunds".
		$keywords = [ 'refund', 'refunds', 'return', 'returns', 'return policy', 'policy', 'terms', 'privacy' ];
		$pattern  = '/\b(?:' . implode( '|', array_map( 'preg_quote', $keywords ) ) . ')\b/u';
		if ( 1 === preg_match( $pattern, $lower ) ) {
			return 'matched_by_title';
		}

		return null;
	}

	/**
	 * Project a WP_Post into the shape the picker dropdown consumes.
	 *
	 * @param WP_Post     $page     Page to project.
	 * @param string|null $category Policy category slug, or null when "other".
	 * @return array
	 */
	private function format_page( WP_Post $page, ?string $category ): array {
		$row = [
			'id'       => (int) $page->ID,
			'title'    => html_entity_decode( (string) get_the_title( $page->ID ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
			'edit_url' => (string) get_edit_post_link( $page->ID, 'raw' ),
		];
		if ( null !== $category ) {
			$row['category'] = $category;
		}
		return $row;
	}
}
