/**
 * External dependencies
 */
import { Button, Modal } from '@wordpress/components';
import { render, useState, Fragment } from '@wordpress/element';
import $ from 'jquery';

const doOrderAction = ( actionId ) => {
	$( '#actions select' ).val( actionId ).closest( 'form' ).submit();
};

const CaptureAction = () => {
	const [ isOpen, setOpen ] = useState( false );
	const [ isBusy, setBusy ] = useState( false );

	const doAction = () => {
		setBusy( true );
		doOrderAction( 'capture_charge' );
	}

	return (
		<Fragment>
			<Button isDefault onClick={ () => setOpen( true ) }>Capture Charge</Button>
			{ isOpen && (
				<Modal title="Capture Charge" onRequestClose={ () => setOpen( false ) }>
					<Button isDefault onClick={ () => setOpen( false ) }>Close</Button>
					<Button isPrimary onClick={ doAction } isBusy={ isBusy }>Capture Charge</Button>
				</Modal>
			) }
		</Fragment>
	);
};

const VoidAction = () => {
	const [ isOpen, setOpen ] = useState( false );
	const [ isBusy, setBusy ] = useState( false );

	const doAction = () => {
		setBusy( true );
		doOrderAction( 'void_authorization' );
	}

	return (
		<Fragment>
			<Button isDefault onClick={ () => setOpen( true ) }>Void Authorization</Button>
			{ isOpen && (
				<Modal title="Void Authorization" onRequestClose={ () => setOpen( false ) }>
					<Button isDefault onClick={ () => setOpen( false ) }>Close</Button>
					<Button isPrimary onClick={ doAction } isBusy={ isBusy }>Void Authorization</Button>
				</Modal>
			) }
		</Fragment>
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
