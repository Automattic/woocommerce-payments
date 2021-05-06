/** @format **/

/**
 * External dependencies
 */
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import './style.scss';
import InstantDepositModal from './modal';
import { useInstantDeposit } from 'data';

const isButtonDisabled = ( instantBalance ) => {
	let buttonDisabled = false;
	if ( 0 === instantBalance.amount ) {
		buttonDisabled = true;
	}

	return buttonDisabled;
};

const InstantDepositButton = ( { instantBalance } ) => {
	const [ isModalOpen, setModalOpen ] = useState( false );
	const buttonDisabled = isButtonDisabled( instantBalance );
	const { inProgress, submit } = useInstantDeposit(
		instantBalance.transaction_ids
	);
	const onClose = () => {
		setModalOpen( false );
	};
	const onSubmit = () => {
		setModalOpen( false );
		submit();
	};
	// TODO: Need to update isDefault to isSecondary once @wordpress/components is updated
	// https://github.com/Automattic/woocommerce-payments/pull/1536
	return (
		<>
			<Button
				isDefault
				className="is-secondary"
				disabled={ buttonDisabled }
				onClick={ () => setModalOpen( true ) }
			>
				{ __( 'Instant deposit', 'woocommerce-payments' ) }
			</Button>
			{ ( isModalOpen || inProgress ) && (
				<InstantDepositModal
					instantBalance={ instantBalance }
					inProgress={ inProgress }
					onSubmit={ onSubmit }
					onClose={ onClose }
				/>
			) }
		</>
	);
};

export default InstantDepositButton;
