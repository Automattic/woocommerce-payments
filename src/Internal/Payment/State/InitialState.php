<?php
/**
 * Class InitialState
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Payment\State;

use WC_Payments_Order_Service;
use WCPay\Internal\Payment\SideEffects;
use WCPay\Internal\Payment\StateFactory;

/**
 * Initial payment state.
 */
class InitialState extends State {
	/**
	 * Order service.
	 *
	 * @var WC_Payments_Order_Service
	 */
	private $order_service;

	/**
	 * Side effects controller.
	 *
	 * @var SideEffects
	 */
	private $side_effects;

	/**
	 * Class constructor, only meant for storing dependencies.
	 *
	 * @param StateFactory              $state_factory State factory.
	 * @param WC_Payments_Order_Service $order_service Order service.
	 * @param SideEffects               $side_effects  Service for side effects.
	 */
	public function __construct(
		StateFactory $state_factory,
		WC_Payments_Order_Service $order_service,
		SideEffects $side_effects
	) {
		parent::__construct( $state_factory );

		$this->order_service = $order_service;
		$this->side_effects  = $side_effects;
	}

	/**
	 * Processes a new payment.
	 *
	 * @return State Returns the next payment state.
	 */
	public function process() {
		$context = $this->get_context();
		$order   = $this->order_service->get_order( $context->get_order_id() );

		// Perform actions.
		// Potential circular dependency: If we actually did anything with the order,
		// the order service would get in the way. We might end up creating too many services.
		return $this->side_effects->trigger( 'process', $this );
	}
}
