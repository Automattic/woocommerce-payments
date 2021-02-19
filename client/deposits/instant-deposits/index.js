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
	const [ hasError, setHasError ] = useState( false );

	// TODO: Use wp.data
	const submit = async () => {
		try {
			setInProgress( true );
			setHasError( false );
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
		} catch ( err ) {
			setHasError( err );
		} finally {
			setInProgress( false );
		}
	};

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
					onClose={ () => {
						setModalOpen( false );
						setHasError( false );
					} }
					hasError={ hasError }
				/>
			) }
		</>
	);
};

export default InstantDepositButton;
