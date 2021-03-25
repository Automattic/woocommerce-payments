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
import Currency from "@woocommerce/currency";
// TODO: Use the proper WCPay currency.
const currency = new Currency();

const InstantDepositButton = ( {
	// eslint-disable-next-line camelcase
	balance: { amount, fee, net, transaction_ids },
} ) => {
	const [ isModalOpen, setModalOpen ] = useState( false );
	const { deposit, inProgress, submit } = useInstantDeposit( transaction_ids );

	const onClose = () => {
		setModalOpen( false );
	};

	return (
		<>
			<Button isDefault onClick={ () => setModalOpen( true ) }>
				{ __( 'Instant deposit', 'woocommerce-payments' ) }
			</Button>
			{ isModalOpen && ! deposit && (
				<InstantDepositModal
					amount={ amount }
					fee={ fee }
					net={ net }
					inProgress={ inProgress }
					onSubmit={ submit }
					onClose={ onClose }
				/>
			) }
		</>
	);
};

export default InstantDepositButton;
