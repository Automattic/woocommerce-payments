/** @format **/

/**
 * External dependencies
 */
import { Button, Modal } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import Currency from '@woocommerce/currency';
import apiFetch from '@wordpress/api-fetch';

const currency = new Currency();

const InstantDepositModal = ( { amount, fee, net, onClose, onSubmit, inProgress } ) => {
	return (
		<Modal
			title={ __( 'Instant deposit', 'woocommerce-payments' ) }
			onRequestClose={ onClose }
		>
			<ul>
				<li>Total amount: { currency.formatCurrency( amount / 100 ) }</li>
				<li>Fee: { currency.formatCurrency( fee / 100 ) }</li>
				<li>Net: { currency.formatCurrency( net / 100 ) }</li>
			</ul>
			<Button isPrimary onClick={ onSubmit } isBusy={ inProgress }>
				{ sprintf(
					/* translators: %s: Monetary amount to deposit */
					__( 'Deposit %s now', 'woocommerce-payments' ),
					currency.formatCurrency( net / 100 )
				) }
			</Button>
		</Modal>
	)
}

// TODO: Properly style :allthethings:
const InstantDepositButton = ( { balance: { amount, fee, net, transaction_ids } } ) => {
	const [ isModalOpen, setModalOpen ] = useState( false );
	const [ inProgress, setInProgress ] = useState( false );

	// TODO: Use wp.data
	const submit = async () => {
		try {
			setInProgress( true );
			await apiFetch( {
				path: '/wc/v3/payments/deposits',
				method: 'POST',
				data: {
					type: 'instant',
					transaction_ids,
				},
			} );
			// TODO: Success notice? Full-reload the page so the new deposit appears?
		} catch ( err ) {
			// TODO: Real error management
			alert( 'An error has occurred: ' + err );
		} finally {
			setInProgress( false );
			setModalOpen( false );
		}
	};

	return (
		<>
			<Button isDefault onClick={ () => setModalOpen( true ) }>
				{ __( 'Instant deposit', 'woocommerce-payments' ) }
			</Button>
			{ isModalOpen && <InstantDepositModal
				amount={ amount }
				fee={ fee }
				net={ net }
				inProgress={ inProgress }
				onSubmit={ submit }
				onClose={ () => setModalOpen( false ) } /> }
		</>
	);
};

export default InstantDepositButton;
