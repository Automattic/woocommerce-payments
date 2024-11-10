/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import './style.scss';
import { formatCurrency } from 'multi-currency/interface/functions';
import InstantPayoutModal from './modal';
import { useInstantDeposit } from 'wcpay/data';
import type * as AccountOverview from 'wcpay/types/account-overview';

const isButtonDisabled = ( instantBalance: AccountOverview.InstantBalance ) => {
	let buttonDisabled = false;
	if ( 0 === instantBalance.amount ) {
		buttonDisabled = true;
	}

	return buttonDisabled;
};

interface InstantPayoutButtonProps {
	instantBalance: AccountOverview.InstantBalance;
}
const InstantPayoutButton: React.FC< InstantPayoutButtonProps > = ( {
	instantBalance,
} ) => {
	const [ isModalOpen, setModalOpen ] = useState( false );
	const buttonDisabled = isButtonDisabled( instantBalance );
	const { inProgress, submit } = useInstantDeposit( instantBalance.currency );
	const onClose = () => {
		setModalOpen( false );
	};
	const onSubmit = () => {
		setModalOpen( false );
		submit();
	};

	return (
		<>
			<Button
				isPrimary
				disabled={ buttonDisabled }
				onClick={ () => setModalOpen( true ) }
			>
				{ sprintf(
					__(
						/* translators: %s: Available instant payout amount */
						'Get %s now',
						'woocommerce-payments'
					),
					formatCurrency(
						instantBalance.amount,
						instantBalance.currency
					)
				) }
			</Button>
			{ ( isModalOpen || inProgress ) && (
				<InstantPayoutModal
					instantBalance={ instantBalance }
					inProgress={ inProgress }
					onSubmit={ onSubmit }
					onClose={ onClose }
				/>
			) }
		</>
	);
};

export default InstantPayoutButton;
