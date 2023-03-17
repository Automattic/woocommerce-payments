/**
 * External dependencies
 */
import React, { useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import { Card, CardBody } from '@wordpress/components';
import { getAdminUrl } from 'wcpay/utils';

/**
 * Internal dependencies
 */
import PaymentDetailsSummary from './summary';
import PaymentDetailsTimeline from './timeline';
import PaymentDetailsPaymentMethod from './payment-method';
import Page from 'components/page';
import ErrorBoundary from 'components/error-boundary';
import { TestModeNotice, topics } from 'components/test-mode-notice';
import PaymentCardReaderChargeDetails from './readers';
import { PaymentChargeDetails, isPaymentIntent, isCharge } from './types';
import { Charge } from '../types/charges';
import {
	getIsChargeId,
	usePaymentIntentWithChargeFallback,
} from 'wcpay/data/payment-intents';

const PaymentDetails = ( props: Record< string, any > ): JSX.Element => {
	if ( 'card_reader_fee' === props.query.transaction_type ) {
		return (
			<PaymentCardReaderChargeDetails
				chargeId={ props.query.id }
				transactionId={ props.query.transaction_id }
			/>
		);
	}

	return <PaymentChargeDetails id={ props.query.id } />;
};

const PaymentChargeDetails = ( { id }: { id: string } ): JSX.Element => {
	const {
		data,
		error,
		isLoading: isLoadingData,
	} = usePaymentIntentWithChargeFallback( id ) as PaymentChargeDetails;

	const isChargeId = getIsChargeId( id );
	const isLoading = isChargeId || isLoadingData;

	const testModeNotice = <TestModeNotice topic={ topics.paymentDetails } />;

	useEffect( () => {
		if ( isCharge( data ) ) {
			const shouldRedirect = !! ( isChargeId && data.payment_intent );

			if ( shouldRedirect ) {
				const url = getAdminUrl( {
					page: 'wc-admin',
					path: '/payments/transactions/details',
					id: data.payment_intent,
				} );

				window.location.href = url;
			}
		}
	}, [ data, isChargeId ] );

	// Check instance of error because its default value is empty object
	if ( ! isLoading && error instanceof Error ) {
		return (
			<Page maxWidth={ 1032 } className="wcpay-payment-details">
				{ testModeNotice }
				<Card>
					<CardBody>
						{ __(
							'Payment details not loaded',
							'woocommerce-payments'
						) }
					</CardBody>
				</Card>
			</Page>
		);
	}

	return (
		<Page maxWidth={ 1032 } className="wcpay-payment-details">
			{ testModeNotice }
			<ErrorBoundary>
				<PaymentDetailsSummary
					charge={
						( isPaymentIntent( data ) ? data.charge : data ) ||
						( {} as Charge )
					}
					metadata={ isPaymentIntent( data ) ? data.metadata : {} }
					isLoading={ isLoading }
				/>
			</ErrorBoundary>
			{ ! isChargeId && wcpaySettings.featureFlags.paymentTimeline && (
				<ErrorBoundary>
					<PaymentDetailsTimeline paymentIntentId={ id } />
				</ErrorBoundary>
			) }
			<ErrorBoundary>
				<PaymentDetailsPaymentMethod
					charge={
						( isPaymentIntent( data ) ? data.charge : data ) ||
						( {} as Charge )
					}
					isLoading={ isLoading }
				/>
			</ErrorBoundary>
		</Page>
	);
};

export default PaymentDetails;
