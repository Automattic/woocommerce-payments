<?php
namespace WCPay\Core\State_Machine;

class Entity_Storage_Payment {
	const META_KEY = '_wcpay_payment_entity';
	public function save( Entity_Payment $entity ) {
		$order = wc_get_order( $entity->get_order_id() );
		$order->update_meta_data( self::META_KEY, serialize($entity) );
	}

	public function load( \WC_Order $order ): Entity_Payment {
		if ( $order->meta_exists( self::META_KEY) ) {
			return unserialize( $order->get_meta( self::META_KEY ) );
		}

		$entity = new Entity_Payment( $order->get_id() );
		$this->save( $entity );
		return $entity;
	}
}

