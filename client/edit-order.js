/**
 * External dependencies
 */
import { Button } from '@wordpress/components';
import { render, useState } from '@wordpress/element';
import $ from 'jquery';

const doOrderAction = ( actionId ) => {
	$( '#actions select' ).val( actionId ).closest( 'form' ).submit();
};

const CaptureAction = () => {
	const [ isBusy, setBusy ] = useState( false );

	const doAction = () => {
		setBusy( true );
		doOrderAction( 'capture_charge' );
	}

	return (
		<Button isDefault onClick={ doAction } isBusy={ isBusy }>Capture Charge</Button>
	);
};

const VoidAction = () => {
	const [ isBusy, setBusy ] = useState( false );

	const doAction = () => {
		setBusy( true );
		doOrderAction( 'void_authorization' );
	}

	return (
		<Button isDefault onClick={ doAction } isBusy={ isBusy }>Void Authorization</Button>
	);
};

const AuthorizedChargeActions = () => (
	<div style={ { float: 'right' } }>
		<VoidAction />
		<CaptureAction />
	</div>
);

const container = $( '<div>' )
	.attr( 'class', 'authorized-charge-action-container' )
	.prependTo( '.woocommerce-order-data__meta.order_number' )
	.get( 0 );

render( <AuthorizedChargeActions />, container );
