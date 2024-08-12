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
import { formatCurrency } from 'wcpay/utils/currency';
import InstantDepositModal from './modal';
import { useInstantDeposit } from 'wcpay/data';
import type * as AccountOverview from 'wcpay/types/account-overview';

const isButtonDisabled = ( instantBalance: AccountOverview.InstantBalance ) => {
	let buttonDisabled = false;
	if ( 0 === instantBalance.amount ) {
		buttonDisabled = true;
	}

	return buttonDisabled;
};

interface InstantDepositButtonProps {
	instantBalance: AccountOverview.InstantBalance;
}
const InstantDepositButton: React.FC< InstantDepositButtonProps > = ( {
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
						/* translators: %s: Available instant deposit amount */
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
