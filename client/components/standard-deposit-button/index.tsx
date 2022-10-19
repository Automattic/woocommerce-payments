/**
 * External dependencies
 */
import React from 'react';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { useStandardDeposit } from 'wcpay/data';
import StandardDepositModal from './modal';

type StandardDepositButtonProps = {
	availableBalance: AccountOverview.Overview[ 'available' ];
};

const StandardDepositButton: React.FC< StandardDepositButtonProps > = ( {
	availableBalance,
} ) => {
	const { amount, transaction_ids: transactionIds } = availableBalance;

	const [ isModalOpen, setModalOpen ] = useState< boolean >( false );
	const buttonDisabled = amount > 0 ? false : true;

	const { inProgress, submit } = useStandardDeposit( transactionIds );
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
				disabled={ buttonDisabled }
				isPrimary
				onClick={ () => setModalOpen( true ) }
			>
				{ __( 'Deposit funds', 'woocommerce-payments' ) }
			</Button>
			{ ( isModalOpen || inProgress ) && (
				<StandardDepositModal
					availableBalance={ availableBalance }
					inProgress={ inProgress }
					onClose={ onClose }
					onSubmit={ onSubmit }
				/>
			) }
		</>
	);
};

export default StandardDepositButton;
