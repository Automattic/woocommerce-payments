/** @format **/

/**
 * External dependencies
 */
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import './style.scss';
import InstantDepositModal from './modal';

const InstantDepositButton = ( {
	// eslint-disable-next-line camelcase
	balance: { amount, fee, net, transaction_ids },
} ) => {
	const [ isModalOpen, setModalOpen ] = useState( false );
	const [ inProgress, setInProgress ] = useState( false );
	const [ hasNotice, setHasNotice ] = useState( false );

	// TODO: Use wp.data
	const submit = async () => {
		try {
			setInProgress( true );
			setHasNotice( false );
			await apiFetch( {
				path: '/wc/v3/payments/deposits',
				method: 'POST',
				data: {
					type: 'instant',
					// eslint-disable-next-line camelcase
					transaction_ids,
				},
			} );
			// TODO: Success notice? Full-reload the page so the new deposit appears?
			setHasNotice( 'success' );
		} catch ( err ) {
			setHasNotice( err );
		} finally {
			setInProgress( false );
		}
	};

	const onClose = () => {
		// If it has a notice, we want to refresh the page.
		if ( hasNotice ) {
			document.location.reload();
		} else {
			setModalOpen( false );
			setHasNotice( false );
		}
	}

	return (
		<>
			<Button isDefault onClick={ () => setModalOpen( true ) }>
				{ __( 'Instant deposit', 'woocommerce-payments' ) }
			</Button>
			{ isModalOpen && (
				<InstantDepositModal
					amount={ amount }
					fee={ fee }
					net={ net }
					inProgress={ inProgress }
					onSubmit={ submit }
					onClose={ onClose }
					hasNotice={ hasNotice }
				/>
			) }
		</>
	);
};

export default InstantDepositButton;
