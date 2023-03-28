/**
 * External dependencies
 */
import React from 'react';

/**
 * Internal dependencies
 */
import PaymentCardReaderChargeDetails from './readers';
import { PaymentDetailsProps } from './types';
import PaymentOrderDetails from './order-details';
import PaymentChargeDetails from './charge-details';

const PaymentDetails: React.FC< PaymentDetailsProps > = ( { query } ) => {
	const {
		id,
		transaction_id: transactionId,
		transaction_type: transactionType,
	} = query || {};

	if ( 'card_reader_fee' === transactionType ) {
		return (
			<PaymentCardReaderChargeDetails
				chargeId={ id }
				transactionId={ transactionId }
			/>
		);
	}

	if ( /^\d+$/.test( id ) ) {
		return <PaymentOrderDetails id={ id } />;
	}

	return <PaymentChargeDetails id={ id } />;
};

export default PaymentDetails;
