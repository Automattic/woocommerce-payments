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
import ManualDepositModal from './modal';

interface ManualDepositButtonProps {
	availableBalance: AccountOverview.Balance;
	depositDelayDays: number;
}

const ManualDepositButton = ( {
	availableBalance,
	depositDelayDays,
}: ManualDepositButtonProps ): JSX.Element => {
	const [ isModalOpen, setModalOpen ] = useState< boolean >( false );
	const buttonDisabled = availableBalance.amount > 0 ? false : true;

	// TODO: handle manual deposit request
	const inProgress = false;
	const onClose = () => {
		setModalOpen( false );
	};
	const onSubmit = () => {
		setModalOpen( false );
		// submit();
	};

	return (
		<>
			<Button
				isSecondary
				disabled={ buttonDisabled }
				onClick={ () => setModalOpen( true ) }
			>
				{ __( 'Deposit now', 'woocommerce-payments' ) }
			</Button>
			{ ( isModalOpen || inProgress ) && (
				<ManualDepositModal
					availableBalance={ availableBalance }
					depositDelayDays={ depositDelayDays }
					onSubmit={ onSubmit }
					onClose={ onClose }
					inProgress={ inProgress }
				/>
			) }
		</>
	);
};

export default ManualDepositButton;
