<?php
/**
 * Non-persistent session for agent purchase quotes.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Service\AgentPurchases;

/** An in-memory session with no persistence hooks. */
class InMemorySession extends \WC_Session {}
