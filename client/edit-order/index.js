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

const ConfirmAction = ( { action, title, children } ) => {
	const [ isOpen, setOpen ] = useState( false );
	const [ isBusy, setBusy ] = useState( false );

	const doAction = () => {
		setBusy( true );
		doOrderAction( action );
	};

	return (
		<Fragment>
			<Button isDefault onClick={ () => setOpen( true ) }>{ title }</Button>
			{ isOpen && (
				<Modal title={ title } onRequestClose={ () => setOpen( false ) } className="authorized-charge-modal">
					{ children }
					<div className="authorized-charge-modal-buttons">
						<Button isDefault onClick={ () => setOpen( false ) }>{ __( 'Close' ) }</Button>
						<Button isPrimary onClick={ doAction } isBusy={ isBusy }>{ title }</Button>
					</div>
				</Modal>
			) }
		</Fragment>
	);
};

const AuthorizedChargeActions = () => (
	<Fragment>
		<ConfirmAction action="void_authorization" title={ __( 'Void Authorization' ) }>
			<p>{ __( 'Voiding this authorization will cancel the order.' ) }</p>
		</ConfirmAction>
		<ConfirmAction action="capture_charge" title={ __( 'Capture Charge' ) } />
	</Fragment>
);

const container = $( '<div>' )
	.attr( 'class', 'authorized-charge-action-container' )
	.prependTo( '.woocommerce-order-data__meta.order_number' )
	.get( 0 );

render( <AuthorizedChargeActions />, container );
