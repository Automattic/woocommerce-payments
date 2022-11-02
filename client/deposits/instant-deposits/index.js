/** @format **/

/**
 * External dependencies
 */
import { Button } from '@wordpress/components';
import { __, _x, sprintf } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import moment from 'moment';

/**
 * Internal dependencies
 */
import './style.scss';
import InstantDepositModal from './modal';
import { useInstantDeposit } from 'wcpay/data';
import { formatCurrency } from 'utils/currency';
import Tooltip from 'wcpay/components/tooltip';
import { formatDate } from 'utils';

const isButtonDisabled = ( instantBalance, lastDayManualDeposit ) => {
	let buttonDisabled = false;
	if ( 0 === instantBalance.amount ) {
		buttonDisabled = true;
	}

	if ( lastDayManualDeposit ) {
		buttonDisabled = true;
	}

	return buttonDisabled;
};

const InstantDepositButton = ( { instantBalance, lastDayManualDeposit } ) => {
	const [ isModalOpen, setModalOpen ] = useState( false );
	const buttonDisabled = isButtonDisabled(
		instantBalance,
		lastDayManualDeposit
	);
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

	let tooltipText = '';

	if ( lastDayManualDeposit ) {
		const nextAvailableDepositDate = formatDate(
			_x(
				'F j, Y \\a\\t g:iA',
				'Date format, e.g. November 16, 1989 at 11:00AM',
				'woocommerce-payments'
			),
			moment.utc( lastDayManualDeposit.date ).add( 24, 'hours' ),
			false
		);

		tooltipText = sprintf(
			__(
				'Deposits are available once per day. Your next deposit can be created on %s.',
				'woocommerce-payments'
			),
			nextAvailableDepositDate
		);
	} else if ( 0 === instantBalance.amount ) {
		tooltipText = sprintf(
			__(
				'Instant deposits require a minimum available balance of %s.',
				'woocommerce-payments'
			),
			formatCurrency( 5000, 'usd' ) // TODO: update these hardcoded values when instant deposits are available for other currencies.
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
					{ __( 'Instant deposit', 'woocommerce-payments' ) }
				</Button>
			</Tooltip>
			{ ( isModalOpen || inProgress ) && (
				<InstantDepositModal
					inProgress={ inProgress }
					instantBalance={ instantBalance }
					onClose={ onClose }
					onSubmit={ onSubmit }
				/>
			) }
		</>
	);
};

export default InstantDepositButton;
