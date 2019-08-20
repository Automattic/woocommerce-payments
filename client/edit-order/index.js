/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Button, Modal } from '@wordpress/components';
import { render, useState, Fragment } from '@wordpress/element';
import $ from 'jquery';

/**
 * Internal dependencies
 */
import './style.scss';

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
				<Modal title="Capture Charge" onRequestClose={ () => setOpen( false ) } className="authorized-charge-modal">
					<div className="authorized-charge-modal-buttons">
						<Button isDefault onClick={ () => setOpen( false ) }>Close</Button>
						<Button isPrimary onClick={ doAction } isBusy={ isBusy }>Capture Charge</Button>
					</div>
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
				<Modal title="Void Authorization" onRequestClose={ () => setOpen( false ) } className="authorized-charge-modal">
					<div className="authorized-charge-modal-buttons">
						<p>{ __( 'Voiding this authorization will cancel the order, and restock all items.' ) }</p>
						<Button isDefault onClick={ () => setOpen( false ) }>Close</Button>
						<Button isPrimary onClick={ doAction } isBusy={ isBusy }>Void Authorization</Button>
					</div>
				</Modal>
			) }
		</Fragment>
	);
};

const AuthorizedChargeActions = () => (
	<Fragment>
		<VoidAction />
		<CaptureAction />
	</Fragment>
);

const container = $( '<div>' )
	.attr( 'class', 'authorized-charge-action-container' )
	.prependTo( '.woocommerce-order-data__meta.order_number' )
	.get( 0 );

render( <AuthorizedChargeActions />, container );
