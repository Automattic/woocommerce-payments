<?php
/**
 * Class WC_REST_WooPay_Dev_Marketplace_Bridge_Controller
 *
 * Dev-mode-only bridge that emits this merchant's store + products in
 * the projected shape WooPay's `/wsn/v1/stores/<host>` endpoint
 * returns — so WooPay-dev can fetch a real merchant's catalog and
 * inject it ahead of the live ES results when testing surfaces against
 * a not-yet-indexed merchant.
 *
 * The response mirrors WooPay's slim projection (the default, no
 * `?fields=all`): `MarketplaceStoreFull` + an inline list of
 * `MarketplaceProduct`s. WooPay can either render this directly or
 * prepend it to a live ES response before passing through its own
 * shape adapters.
 *
 * Permission: dev-mode only. The endpoint 404s in test / live so a
 * production merchant can't leak their full catalog to anyone who
 * stumbles onto the URL.
 *
 * @package WooCommerce\Payments\Admin
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST controller — dev-mode marketplace bridge for WooPay testing.
 */
class WC_REST_WooPay_Dev_Marketplace_Bridge_Controller extends WP_REST_Controller {

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'wcpay/v1';

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'woopay/dev-marketplace-bridge';

	/**
	 * Per-page caps. Aligned with WooPay's `/wsn/v1/stores/<host>` so
	 * the dev surface mirrors the live one without changing call sites
	 * when swapping the fetch target.
	 */
	const DEFAULT_PER_PAGE = 100;
	const MAX_PER_PAGE     = 200;

	/**
	 * Configure REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_store_data' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [
					'page'     => [
						'type'              => 'integer',
						'required'          => false,
						'default'           => 1,
						'minimum'           => 1,
						'sanitize_callback' => 'absint',
					],
					'per_page' => [
						'type'              => 'integer',
						'required'          => false,
						'default'           => self::DEFAULT_PER_PAGE,
						'minimum'           => 1,
						'maximum'           => self::MAX_PER_PAGE,
						'sanitize_callback' => 'absint',
					],
				],
			]
		);
	}

	/**
	 * Permission check — endpoint only available in dev mode. Avoids
	 * accidental catalog leak from production sites that get hit by a
	 * speculative `GET`. WooPay-dev's caller is responsible for any
	 * additional auth on its end (e.g. allowlisted dev-merchant hosts).
	 *
	 * @return bool
	 */
	public function check_permission(): bool {
		return WC_Payments::mode()->is_dev();
	}

	/**
	 * Bridge handler. Returns the merchant's store metadata + a
	 * paginated slice of products in WooPay's slim projection shape.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response
	 */
	public function get_store_data( WP_REST_Request $request ): WP_REST_Response {
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = (int) $request->get_param( 'per_page' );
		if ( $per_page <= 0 ) {
			$per_page = self::DEFAULT_PER_PAGE;
		}
		$per_page = min( $per_page, self::MAX_PER_PAGE );

		$host = $this->normalized_host();

		// Total product count — drives `product_count` + pagination
		// math without hydrating every WC_Product upfront.
		$count_query = new WC_Product_Query(
			[
				'status'     => 'publish',
				'limit'      => -1,
				'return'     => 'ids',
				'orderby'    => 'date',
				'order'      => 'DESC',
				'paginate'   => false,
				'visibility' => 'visible',
			]
		);
		$all_ids     = (array) $count_query->get_products();
		$total       = count( $all_ids );
		$total_pages = $per_page > 0 ? (int) ceil( $total / $per_page ) : 0;

		// Fetch the paginated slice and project each through the
		// WooPay-slim shape.
		$paginated_ids = array_slice( $all_ids, ( $page - 1 ) * $per_page, $per_page );
		$products      = [];
		foreach ( $paginated_ids as $product_id ) {
			$wc_product = wc_get_product( $product_id );
			if ( ! $wc_product instanceof WC_Product ) {
				continue;
			}
			$projected = $this->project_product( $wc_product, $host );
			if ( null !== $projected ) {
				$products[] = $projected;
			}
		}

		$store = $this->project_store( $host, $products, $total );

		$response = [
			'url'              => $host,
			'product_count'    => $total,
			'products_in_page' => count( $products ),
			'page'             => $page,
			'per_page'         => $per_page,
			'total_pages'      => $total_pages,
			'has_more'         => $page < $total_pages,
			'store'            => $store,
			'products'         => $products,
		];

		return rest_ensure_response( $response );
	}

	/**
	 * Project the merchant's store-level metadata into the WooPay slim
	 * `MarketplaceStoreFull` shape. Mirrors `EsSource::project_store_slim`
	 * + `project_store_full` field-by-field so WooPay's adapters can
	 * consume the response without a custom code path.
	 *
	 * @param string $host           Normalized site host (no scheme).
	 * @param array  $products       Already-projected products (passed
	 *                               through into `store.products`).
	 * @param int    $product_count  Total product count across the
	 *                               whole catalog (not just this page).
	 * @return array
	 */
	private function project_store( string $host, array $products, int $product_count ): array {
		$icon_url    = $this->site_icon_url();
		$favicon_url = '' !== $host
			? 'https://www.google.com/s2/favicons?domain=' . rawurlencode( $host ) . '&sz=128'
			: null;

		$blog_url      = esc_url_raw( home_url() );
		$description   = (string) get_bloginfo( 'description' );
		$blog_lang_raw = (string) get_bloginfo( 'language' );
		// Normalize WP's `en-US` to ES's `en` (only the primary subtag).
		$blog_lang     = '' !== $blog_lang_raw ? strtolower( strtok( $blog_lang_raw, '-' ) ) : null;
		$blog_name     = (string) get_bloginfo( 'name' );
		$site_currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '';

		return [
			// Slim fields.
			'normalized_url'    => sanitize_text_field( $host ),
			'name'              => '' !== $blog_name ? sanitize_text_field( $blog_name ) : null,
			'logo_url'          => null,
			'icon_url'          => $icon_url,
			'favicon_url'       => $favicon_url,
			'description'       => '' !== $description ? sanitize_text_field( $description ) : null,
			'blog_url'          => '' !== $blog_url ? $blog_url : null,
			'lang'              => '' !== $blog_lang ? sanitize_text_field( $blog_lang ) : null,
			'display_category'  => null,

			// Full additions (mirrors `EsSource::project_store_full`).
			'rank'              => null,
			'country'           => $this->merchant_country(),
			'dominant_currency' => '' !== $site_currency ? sanitize_text_field( $site_currency ) : null,
			'product_count'     => $product_count,
			'products'          => $products,
		];
	}

	/**
	 * Project a `WC_Product` into WooPay's slim `MarketplaceProduct`
	 * shape. Mirrors `EsSource::project_product` field-by-field; fields
	 * the ES indexer fills via AI classification or curator-set data
	 * (`global_category`, `display_category`, `featured_rank`, etc.)
	 * are emitted null/empty here since this merchant isn't ES-indexed.
	 *
	 * @param WC_Product $product Product to project.
	 * @param string     $host    Merchant's normalized host (for the
	 *                            permalink fallback when WC's stored
	 *                            permalink isn't fully qualified).
	 * @return array|null         Projected product or null when the
	 *                            product can't be hydrated.
	 */
	private function project_product( WC_Product $product, string $host ) {
		$variations_raw = $this->collect_variations( $product );
		$any_on_sale    = false;
		$sale_regular   = null;
		foreach ( $variations_raw as $v ) {
			if ( ! empty( $v['on_sale'] ) ) {
				$any_on_sale = true;
				if ( null === $sale_regular && isset( $v['regular_price'] ) && is_numeric( $v['regular_price'] ) ) {
					$sale_regular = (float) $v['regular_price'];
				}
				if ( null !== $sale_regular ) {
					break;
				}
			}
		}
		// Top-level on_sale falls back to the product's own getter when
		// the product isn't variable (so `on_sale` still tracks a
		// simple-product sale price set via the regular WC admin).
		if ( ! $any_on_sale && $product->is_on_sale() ) {
			$any_on_sale = true;
		}

		$permalink = $product->get_permalink();
		if ( '' === (string) $permalink && '' !== $host ) {
			$permalink = 'https://' . $host . '/?p=' . $product->get_id();
		}
		$permalink = esc_url_raw( (string) $permalink );

		$images_all = $this->collect_images( $product );
		$image_url  = $images_all[0]['url'] ?? null;

		$currency      = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '';
		$price         = $this->price_or_null( $product->get_price() );
		$regular_price = $this->price_or_null( $product->get_regular_price() );
		$sale_price    = $this->price_or_null( $product->get_sale_price() );
		// `wc_price()` returns escaped HTML; the slim projection wants
		// plain text. Strip tags + decode entities so callers can
		// render the formatted price as-is.
		$formatted = null;
		if ( null !== $price && function_exists( 'wc_price' ) ) {
			$formatted = trim( html_entity_decode( wp_strip_all_tags( wc_price( $price ) ), ENT_QUOTES, 'UTF-8' ) );
		}

		$stock_quantity   = $product->get_stock_quantity();
		$low_stock_amount = $product->get_low_stock_amount();
		$backorders       = (string) $product->get_backorders();
		$avg_rating_raw   = $product->get_average_rating();
		$avg_rating       = is_numeric( $avg_rating_raw ) ? (float) $avg_rating_raw : null;
		$review_count     = (int) $product->get_review_count();

		// Reviews — kept under the slim projection because WooPay's
		// PDP adapter reads from this field. Capped at a reasonable
		// number to keep the bridge response bounded.
		$reviews = $this->collect_reviews( $product, 20 );

		// Categories + tags in WooPay's slim shape: array of
		// { name, slug } objects (matches ES's taxonomy.product_cat /
		// taxonomy.product_tag projection).
		$categories = $this->collect_terms( $product->get_category_ids(), 'product_cat' );
		$tags       = $this->collect_terms( $product->get_tag_ids(), 'product_tag' );

		$brand = $this->resolve_brand( $product );

		$global_unique_id = null;
		if ( is_callable( [ $product, 'get_global_unique_id' ] ) ) {
			$gid              = (string) $product->get_global_unique_id();
			$global_unique_id = '' !== $gid ? sanitize_text_field( $gid ) : null;
		}

		$date_obj = $product->get_date_created();
		$date     = $date_obj instanceof WC_DateTime ? $date_obj->date( 'c' ) : null;

		$sku               = sanitize_text_field( (string) $product->get_sku() );
		$short_description = wp_kses_post( (string) $product->get_short_description() );
		$full_description  = wp_kses_post( (string) $product->get_description() );

		return [
			'post_id'            => (int) $product->get_id(),
			'blog_id'            => (int) get_current_blog_id(),
			'name'               => sanitize_text_field( (string) $product->get_name() ),
			'sku'                => '' !== $sku ? $sku : null,
			'global_unique_id'   => $global_unique_id,
			'brand'              => $brand,
			'excerpt'            => '' !== $short_description ? $short_description : null,
			'description'        => '' !== $full_description ? $full_description : null,
			'date'               => $date,
			'permalink'          => '' !== $permalink ? $permalink : null,
			'price'              => $price,
			// Mirrors `project_product`'s fallback: when the product
			// itself doesn't carry a regular_price (variable products
			// store it on the variation), surface the on-sale variant's
			// regular price so the SPA can render the strikethrough.
			'regular_price'      => $regular_price ?? $sale_regular,
			'sale_price'         => $sale_price,
			'formatted_price'    => $formatted,
			'currency'           => '' !== $currency ? sanitize_text_field( $currency ) : null,
			'on_sale'            => $any_on_sale,
			'stock_status'       => sanitize_text_field( (string) $product->get_stock_status() ),
			'stock_quantity'     => null !== $stock_quantity ? (int) $stock_quantity : null,
			'low_stock_amount'   => null !== $low_stock_amount ? (int) $low_stock_amount : null,
			'backorders'         => '' !== $backorders ? sanitize_text_field( $backorders ) : null,
			'virtual'            => $product->is_virtual(),
			'downloadable'       => $product->is_downloadable(),
			'average_rating'     => $avg_rating,
			'review_count'       => $review_count,
			'reviews'            => $reviews,
			'image_url'          => $image_url,
			'image_thumbnail'    => $image_url,
			'images'             => $images_all,
			'categories'         => $categories,
			'tags'               => $tags,
			'variations'         => $variations_raw,

			// Fields the ES indexer populates via LLM classification or
			// curator-set metadata. Emitted null/empty here so WooPay
			// adapters don't NPE; surfacing this merchant in the
			// AI-category nav would need the indexer to ingest them
			// for real.
			'global_category'    => null,
			'global_subcategory' => null,
			'global_attributes'  => [],
		];
	}

	/**
	 * Resolve a value to a price (float) or null. WC stores prices as
	 * strings, sometimes empty; the slim projection wants null when
	 * unset rather than the float 0.0.
	 *
	 * @param mixed $value Raw price value from WC.
	 * @return float|null
	 */
	private function price_or_null( $value ) {
		if ( null === $value || '' === $value ) {
			return null;
		}
		if ( is_numeric( $value ) ) {
			return (float) $value;
		}
		return null;
	}

	/**
	 * Collect product images in the slim shape: `[ { url, alt_text } ]`
	 * with the featured image first, then gallery images in order.
	 *
	 * @param WC_Product $product Product to extract images from.
	 * @return array<int, array{url: string, alt_text: ?string}>
	 */
	private function collect_images( WC_Product $product ): array {
		$image_ids = [];
		$featured  = (int) $product->get_image_id();
		if ( $featured > 0 ) {
			$image_ids[] = $featured;
		}
		foreach ( (array) $product->get_gallery_image_ids() as $gid ) {
			$gid = (int) $gid;
			if ( $gid > 0 && ! in_array( $gid, $image_ids, true ) ) {
				$image_ids[] = $gid;
			}
		}
		$out = [];
		foreach ( $image_ids as $id ) {
			$url     = wp_get_attachment_image_url( $id, 'full' );
			$escaped = esc_url_raw( (string) $url );
			if ( '' === $escaped ) {
				continue;
			}
			$alt   = (string) get_post_meta( $id, '_wp_attachment_image_alt', true );
			$out[] = [
				'url'      => $escaped,
				'alt_text' => '' !== $alt ? sanitize_text_field( $alt ) : null,
			];
		}
		return $out;
	}

	/**
	 * Build the slim-shape variation list. For variable products this
	 * walks each child variation; for simple products it returns an
	 * empty array (WooPay's adapter handles the simple case via the
	 * top-level price fields).
	 *
	 * Fields match `EsSource::project_product`'s variation read shape
	 * (`price`, `regular_price`, `sale_price`, `on_sale`, `in_stock`,
	 * `sku`, `attributes`).
	 *
	 * @param WC_Product $product Product whose variations to collect.
	 * @return array
	 */
	private function collect_variations( WC_Product $product ): array {
		if ( ! $product instanceof WC_Product_Variable ) {
			return [];
		}
		$out = [];
		foreach ( $product->get_children() as $child_id ) {
			$variation = wc_get_product( (int) $child_id );
			if ( ! $variation instanceof WC_Product_Variation ) {
				continue;
			}
			$out[] = [
				// WC variation post id — surfaced so the WSN→WooPay
				// handoff can call `WC()->cart->add_to_cart` with the
				// exact variation rather than guessing from attributes.
				'variation_id'  => (int) $variation->get_id(),
				'sku'           => sanitize_text_field( (string) $variation->get_sku() ),
				'price'         => $this->price_or_null( $variation->get_price() ),
				'regular_price' => $this->price_or_null( $variation->get_regular_price() ),
				'sale_price'    => $this->price_or_null( $variation->get_sale_price() ),
				'on_sale'       => $variation->is_on_sale(),
				'in_stock'      => 'instock' === $variation->get_stock_status(),
				'attributes'    => array_map(
					'sanitize_text_field',
					(array) $variation->get_attributes()
				),
			];
		}
		return $out;
	}

	/**
	 * Collect a product's reviews in the shape WooPay's PDP adapter
	 * reads (`{ author, date, content, rating }`). Capped to bound
	 * the response size; recent first.
	 *
	 * @param WC_Product $product Product whose reviews to collect.
	 * @param int        $limit   Max reviews to return.
	 * @return array
	 */
	private function collect_reviews( WC_Product $product, int $limit ): array {
		$comments = get_comments(
			[
				'post_id' => $product->get_id(),
				'type'    => 'review',
				'status'  => 'approve',
				'orderby' => 'comment_date',
				'order'   => 'DESC',
				'number'  => $limit,
			]
		);
		$out      = [];
		foreach ( (array) $comments as $comment ) {
			if ( ! $comment instanceof WP_Comment ) {
				continue;
			}
			$rating = (int) get_comment_meta( $comment->comment_ID, 'rating', true );
			$out[]  = [
				'id'      => (int) $comment->comment_ID,
				'author'  => sanitize_text_field( (string) $comment->comment_author ),
				'date'    => mysql2date( 'c', $comment->comment_date_gmt, false ),
				'content' => wp_kses_post( (string) $comment->comment_content ),
				'rating'  => $rating > 0 ? $rating : null,
			];
		}
		return $out;
	}

	/**
	 * Resolve term IDs to `[{name, slug}]` pairs. Mirrors the ES
	 * projection's `taxonomy.product_cat` / `taxonomy.product_tag`
	 * shape that `EsSource::project_product` consumes.
	 *
	 * @param int[]  $term_ids Term IDs to resolve.
	 * @param string $taxonomy Taxonomy name (`product_cat`, `product_tag`).
	 * @return array<int, array{name: string, slug: string}>
	 */
	private function collect_terms( array $term_ids, string $taxonomy ): array {
		$out = [];
		foreach ( $term_ids as $term_id ) {
			$term = get_term( (int) $term_id, $taxonomy );
			if ( ! $term instanceof WP_Term ) {
				continue;
			}
			$out[] = [
				'name' => sanitize_text_field( (string) $term->name ),
				'slug' => sanitize_title( (string) $term->slug ),
			];
		}
		return $out;
	}

	/**
	 * First-hit brand resolver across WC's brand taxonomies. WooPay's
	 * ES projection's `brand` field is sourced from
	 * `taxonomy.product_brand` / `taxonomy.pa_brand`; mirror that
	 * priority chain here.
	 *
	 * @param WC_Product $product Product whose brand to resolve.
	 * @return string|null
	 */
	private function resolve_brand( WC_Product $product ): ?string {
		foreach ( [ 'product_brand', 'pa_brand' ] as $taxonomy ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}
			$terms = wp_get_post_terms( $product->get_id(), $taxonomy, [ 'fields' => 'names' ] );
			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				continue;
			}
			$first = reset( $terms );
			return '' !== (string) $first ? sanitize_text_field( (string) $first ) : null;
		}
		return null;
	}

	/**
	 * Site icon URL (the WP-admin "Site Identity" icon). WooPay's ES
	 * `blog_icon_url` is populated when Jetpack indexes a site icon;
	 * mirror that here so the merchant's own admin upload surfaces on
	 * the bridge response.
	 *
	 * @return string|null
	 */
	private function site_icon_url(): ?string {
		if ( ! function_exists( 'get_site_icon_url' ) ) {
			return null;
		}
		$url = get_site_icon_url();
		if ( ! is_string( $url ) || '' === $url ) {
			return null;
		}
		$escaped = esc_url_raw( $url );
		return '' !== $escaped ? $escaped : null;
	}

	/**
	 * Normalized site host (no scheme, no trailing slash). Matches what
	 * WooPay's `/wsn/v1/stores/<host>` route uses as its merchant
	 * identifier so the bridge response can be slotted directly into
	 * the same SPA call sites.
	 *
	 * @return string
	 */
	private function normalized_host(): string {
		$home = home_url();
		$host = (string) wp_parse_url( $home, PHP_URL_HOST );
		if ( '' === $host ) {
			return '';
		}
		return strtolower( $host );
	}

	/**
	 * Merchant country code, best-effort. Falls back to the WC base
	 * country setting when the WCPay-account country isn't available
	 * (e.g. a merchant who hasn't connected WooPayments yet).
	 *
	 * @return string|null ISO 3166-1 alpha-2 code, or null.
	 */
	private function merchant_country(): ?string {
		if ( function_exists( 'WC' ) ) {
			$base = WC()->countries->get_base_country();
			if ( is_string( $base ) && '' !== $base ) {
				return strtoupper( $base );
			}
		}
		return null;
	}
}
