<?php
/**
 * WooPay Status Overlay Provider Interface
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\WooPay\Tracking_Providers;

defined( 'ABSPATH' ) || exit;

/**
 * Contract for providers that enrich shipments with carrier-status data.
 *
 * Separate from `WooPay_Tracking_Provider` because the two responsibilities
 * are categorically different:
 *
 * - `WooPay_Tracking_Provider` produces shipments — it knows that a tracking
 *   number exists for an order and what the carrier/URL/ship-date are.
 * - `WooPay_Status_Overlay_Provider` enriches existing shipments with live
 *   carrier status — it knows whether the package is in transit, out for
 *   delivery, etc.
 *
 * TrackShip is the canonical overlay provider: it never produces primary
 * shipments (it requires WC Shipment Tracking or AST to supply them), but it
 * polls carriers and pushes status updates back through its REST endpoint,
 * which our persistence listener captures.
 *
 * The sync orchestrator runs primary providers first (first-non-empty wins),
 * then runs all overlay providers in priority order over the resulting
 * shipments. Each overlay may match by `tracking_number` and enrich the
 * shipment's `status` and `status_updated_at` fields.
 */
interface WooPay_Status_Overlay_Provider {
	/**
	 * Enrich the shipments array with carrier-status data.
	 *
	 * Called after the primary provider chain has produced its result. Should
	 * match shipments by `tracking_number` and overlay enriched fields
	 * (`status`, `status_updated_at`) when applicable. Return the shipments
	 * array unchanged if no enrichment applies to this order.
	 *
	 * Implementations MUST be idempotent — the same shipments array passed
	 * twice in succession should produce the same enriched result both times.
	 *
	 * Implementations MUST NOT add or remove shipments — only enrich existing
	 * entries. The primary chain owns shipment cardinality.
	 *
	 * @param \WC_Order $order     Order being assembled.
	 * @param array     $shipments Shipments produced by the primary chain.
	 * @return array Possibly-enriched shipments, same cardinality as input.
	 */
	public function overlay( \WC_Order $order, array $shipments ): array;
}
