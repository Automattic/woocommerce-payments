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
import { formatCurrency } from 'utils/currency';
import { useStandardDeposit } from 'wcpay/data';
import StandardDepositModal from './modal';
import Tooltip from 'wcpay/components/tooltip';

type StandardDepositButtonProps = {
	availableBalance: AccountOverview.Overview[ 'available' ];
};

type ButtonTooltipProps = {
	content: React.ReactNode;
	noArrow: boolean;
	position: 'top' | 'bottom';
};

const ButtonTooltip: React.FC< ButtonTooltipProps > = ( {
	children,
	...props
} ) => {
	if ( ! props.content ) {
		return <>{ children }</>;
	}

	return <Tooltip { ...props }>{ children }</Tooltip>;
};

const StandardDepositButton: React.FC< StandardDepositButtonProps > = ( {
	availableBalance,
} ) => {
	const {
		amount,
		currency,
		transaction_ids: transactionIds,
	} = availableBalance;

	const minimumDepositAmounts =
		wcpaySettings?.accountStatus?.deposits?.minimum_deposit_amounts ?? {};

	const minimumDepositAmount = minimumDepositAmounts?.[ currency ] || 500;

	const [ isModalOpen, setModalOpen ] = useState< boolean >( false );
	const buttonDisabled = amount < minimumDepositAmount;

	const { inProgress, submit } = useStandardDeposit( transactionIds );
	const onClose = () => {
		setModalOpen( false );
	};
	const onSubmit = () => {
		setModalOpen( false );
		submit();
	};

	let tooltipText = '';

	if ( amount < minimumDepositAmount ) {
		tooltipText = sprintf(
			__(
				'Deposits require a minimum available balance of %s.',
				'woocommerce-payments'
			),
			formatCurrency( minimumDepositAmount, currency )
		);
	}

	return (
		<>
			<ButtonTooltip content={ tooltipText } position="bottom" noArrow>
				<Button
					disabled={ buttonDisabled }
					isPrimary
					onClick={ () => setModalOpen( true ) }
				>
					{ __( 'Deposit funds', 'woocommerce-payments' ) }
				</Button>
			</ButtonTooltip>
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
