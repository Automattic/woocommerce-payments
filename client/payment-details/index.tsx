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
import { ApiError } from 'wcpay/types/errors';
import { PaymentIntent } from '../types/payment-intents';
import { Charge } from '../types/charges';
import {
	getIsChargeId,
	usePaymentIntentWithChargeFallback,
} from 'wcpay/data/payment-intents';

interface PaymentChargeDetails {
	data: PaymentIntent | Charge;
	error: ApiError;
	isLoading: boolean;
}

function isCharge( data: PaymentIntent | Charge ): data is PaymentIntent {
	return ( data as PaymentIntent ).charge === undefined;
}

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

	const chargeData: Charge = ( data as unknown ) as Charge;

	useEffect( () => {
		if ( isCharge( data ) ) {
			const shouldRedirect = !! (
				isChargeId && chargeData.payment_intent
			);

			if ( shouldRedirect ) {
				const url = getAdminUrl( {
					page: 'wc-admin',
					path: '/payments/transactions/details',
					id: chargeData.payment_intent,
				} );

				window.location.href = url;
			}
		}
	}, [ data, chargeData.payment_intent, isChargeId ] );

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

	const paymentIntentData: PaymentIntent = ( data as unknown ) as PaymentIntent;

	return (
		<Page maxWidth={ 1032 } className="wcpay-payment-details">
			{ testModeNotice }
			<ErrorBoundary>
				<PaymentDetailsSummary
					charge={ paymentIntentData.charge || ( {} as Charge ) }
					metadata={ paymentIntentData.metadata }
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
					charge={ paymentIntentData.charge || ( {} as Charge ) }
					isLoading={ isLoading }
				/>
			</ErrorBoundary>
		</Page>
	);
};

export default PaymentDetails;
