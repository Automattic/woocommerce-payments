/**
 * External dependencies
 */
import React from 'react';
import { Button } from '@wordpress/components';
import { __, _x, sprintf } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import moment from 'moment';

/**
 * Internal dependencies
 */
import { formatCurrency } from 'utils/currency';
import { useStandardDeposit } from 'wcpay/data';
import StandardDepositModal from './modal';
import Tooltip from 'wcpay/components/tooltip';
import { formatDate } from 'utils';

type StandardDepositButtonProps = {
	availableBalance: AccountOverview.Overview[ 'available' ];
	lastManualDeposit: AccountOverview.Overview[ 'lastManualDeposit' ];
};

const StandardDepositButton: React.FC< StandardDepositButtonProps > = ( {
	availableBalance,
	lastManualDeposit,
} ) => {
	const {
		amount,
		currency,
		transaction_ids: transactionIds,
	} = availableBalance;

	const [ isModalOpen, setModalOpen ] = useState< boolean >( false );

	const minimumDepositAmounts =
		wcpaySettings?.accountStatus?.deposits?.minimum_deposit_amounts ?? {};

	const minimumDepositAmount = minimumDepositAmounts?.[ currency ] || 500;

	const buttonDisabled =
		amount < minimumDepositAmount || !! lastManualDeposit;

	const { inProgress, submit } = useStandardDeposit( transactionIds );
	const onClose = () => {
		setModalOpen( false );
	};
	const onSubmit = () => {
		setModalOpen( false );
		submit();
	};

	let tooltipText = '';

	if ( lastManualDeposit ) {
		const nextAvailableDepositDate = formatDate(
			_x(
				'F j, Y \\a\\t g:iA',
				'Date format, e.g. November 16, 1989 at 11:00AM',
				'woocommerce-payments'
			),
			moment.utc( lastManualDeposit.date ).add( 24, 'hours' ),
			false
		);

		tooltipText = sprintf(
			__(
				'Deposits are available once per day. Your next deposit can be created on %s.',
				'woocommerce-payments'
			),
			nextAvailableDepositDate
		);
	} else if ( amount < minimumDepositAmount ) {
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
			<Tooltip content={ tooltipText } position="bottom" noArrow>
				<Button
					disabled={ buttonDisabled }
					isPrimary
					onClick={ () => setModalOpen( true ) }
				>
					{ __( 'Deposit funds', 'woocommerce-payments' ) }
				</Button>
			</Tooltip>
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
