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

const getModalVars = ( overview ) => {
	if ( overview && overview.instant_balance ) {
		const instant = overview.instant_balance;
		return {
			amount: instant.amount,
			fee: instant.fee,
			net: instant.net,
			transactionIds: instant.transaction_ids,
			buttonDisabled: false,
		};
	}

	return {
		amount: 0,
		fee: 0,
		net: 0,
		transactionIds: [],
		buttonDisabled: true,
	};
};

const InstantDepositButton = ( { overview } ) => {
	const [ isModalOpen, setModalOpen ] = useState( false );
	const { amount, fee, net, transactionIds, buttonDisabled } = getModalVars(
		overview
	);
	const { inProgress, submit } = useInstantDeposit( transactionIds );
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
				isSecondary
				disabled={ buttonDisabled }
				onClick={ () => setModalOpen( true ) }
			>
				{ __( 'Instant deposit', 'woocommerce-payments' ) }
			</Button>
			{ ( isModalOpen || inProgress ) && (
				<InstantDepositModal
					amount={ amount }
					fee={ fee }
					net={ net }
					inProgress={ inProgress }
					onSubmit={ onSubmit }
					onClose={ onClose }
				/>
			) }
		</>
	);
};

export default InstantDepositButton;
