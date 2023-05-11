<?php
namespace WCPay\Core\State_Machine;
// TODO this would be a service
class Entity_Storage_Payment {
	const META_KEY = '_wcpay_payment_entity';
	public function save( \WC_Order $order, Entity_Payment $entity ): bool {
		$order->update_meta_data( self::META_KEY, serialize($entity), true );
	}


	public function get( \WC_Order $order): ?Entity_Payment {
		return $order->meta_exists( self::META_KEY)
			?  unserialize( $order->get_meta( self::META_KEY ) )
			: null;
	}

	public function get_or_create( \WC_Order $order ): Entity_Payment {

		if ( $order->meta_exists( self::META_KEY) ) {
			return unserialize( $order->get_meta( self::META_KEY ) );
		}

		$entity = new Entity_Payment( $order->get_id() );
		$this->save( $order, $entity );
		return $entity;
	}
	public function delete( \WC_Order $order ): bool {
		$order->delete_meta_data( self::META_KEY );
	}
}

