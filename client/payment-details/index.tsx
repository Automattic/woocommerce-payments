/**
 * External dependencies
 */
import React from 'react';
import { getQuery, updateQueryString } from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import PaymentCardReaderChargeDetails from './readers';
import { PaymentDetailsProps } from './types';
import PaymentOrderDetails from './order-details';
import PaymentChargeDetails from './charge-details';
import wcpayTracks from 'tracks';

const PaymentDetails: React.FC< PaymentDetailsProps > = ( { query } ) => {
	const {
		id,
		transaction_id: transactionId,
		transaction_type: transactionType,
	} = query || {};

	const { status_is: statusIs, type_is: typeIs } = getQuery();

	if ( statusIs && typeIs ) {
		wcpayTracks.recordEvent(
			'wcpay_fraud_protection_order_details_link_clicked',
			{ status: statusIs, type: typeIs }
		);
		// Remove the tracking queries on page load so we don't track refreshes or back button reloads.
		updateQueryString( {
			status_is: undefined,
			type_is: undefined,
		} );
	}

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
