/** @format */
/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { Card, CardHeader } from '@wordpress/components';

/**
 * Internal dependencies
 */
import SettingsSection from '../settings-section';
import LoadableSettingsSection from '../loadable-settings-section';
import ErrorBoundary from '../../components/error-boundary';
import { useGetAvailablePaymentMethodIds } from '../../data';
import CardBody from 'wcpay/settings/card-body';
import PaymentMethodsList from '../payment-methods-list';
import methodsConfiguration from 'wcpay/payment-methods-map';
import PAYMENT_METHOD_IDS from 'wcpay/constants/payment-method';

const PaymentMethodsDescription = () => (
	<>
		<h2>
			{ __( 'Payments accepted on checkout', 'woocommerce-payments' ) }
		</h2>
		<p>
			{ __(
				'Add and edit payments available to customers at checkout. ' +
					'Based on their device type, location, and purchase history, ' +
					'your customers will only see the most relevant payment methods.',
				'woocommerce-payments'
			) }
		</p>
	</>
);

const PaymentMethodsSection = () => {
	const availablePaymentMethodIds = useGetAvailablePaymentMethodIds();

	const availableNonBuyNowPayLaterMethodIds = availablePaymentMethodIds
		.filter(
			( id ) =>
				methodsConfiguration[ id ] &&
				! methodsConfiguration[ id ].allows_pay_later &&
				// Stripe Link is displayed in the Express Checkout section
				PAYMENT_METHOD_IDS.LINK !== id
		)
		.reduce( ( acc, methodId ) => {
			// Ensuring that card is at the top of the list
			if ( methodId === PAYMENT_METHOD_IDS.CARD ) {
				return [ methodId, ...acc ];
			}

			return [ ...acc, methodId ];
		}, [] );

	return (
		<SettingsSection
			description={ PaymentMethodsDescription }
			id="payment-methods"
		>
			<LoadableSettingsSection numLines={ 60 }>
				<ErrorBoundary>
					<Card className="payment-methods">
						<CardHeader className="payment-methods__header">
							<h4 className="payment-methods__heading">
								<span>
									{ __(
										'Payment methods',
										'woocommerce-payments'
									) }
								</span>
							</h4>
						</CardHeader>

						<CardBody size={ null }>
							<PaymentMethodsList
								methodIds={
									availableNonBuyNowPayLaterMethodIds
								}
							/>
						</CardBody>
					</Card>
				</ErrorBoundary>
			</LoadableSettingsSection>
		</SettingsSection>
	);
};

export default PaymentMethodsSection;
