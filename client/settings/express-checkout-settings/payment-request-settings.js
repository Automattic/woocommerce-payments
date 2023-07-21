/** @format */
/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { Card, CheckboxControl } from '@wordpress/components';

/**
 * Internal dependencies
 */
import CardBody from '../card-body';
import GeneralPaymentRequestButtonSettings from './general-payment-request-button-settings';
import ExpressCheckoutIncompatibilityNotice from '../settings-warnings/express-checkout-incompatibility-notice';
import {
	usePaymentRequestEnabledSettings,
	usePaymentRequestLocations,
} from 'wcpay/data';

const PaymentRequestSettings = ( { section } ) => {
	const [
		isPaymentRequestEnabled,
		updateIsPaymentRequestEnabled,
	] = usePaymentRequestEnabledSettings();

	const [
		paymentRequestLocations,
		updatePaymentRequestLocations,
	] = usePaymentRequestLocations();

	const makeLocationChangeHandler = ( location ) => ( isChecked ) => {
		if ( isChecked ) {
			updatePaymentRequestLocations( [
				...paymentRequestLocations,
				location,
			] );
		} else {
			updatePaymentRequestLocations(
				paymentRequestLocations.filter( ( name ) => name !== location )
			);
		}
	};

	return (
		<Card>
			{ 'enable' === section && (
				<CardBody>
					<ExpressCheckoutIncompatibilityNotice />
					<CheckboxControl
						checked={ isPaymentRequestEnabled }
						onChange={ updateIsPaymentRequestEnabled }
						label={ __(
							'Enable Apple Pay / Google Pay',
							'woocommerce-payments'
						) }
						help={ __(
							'When enabled, customers who have configured Apple Pay or Google Pay enabled devices ' +
								'will be able to pay with their respective choice of Wallet.',
							'woocommerce-payments'
						) }
					/>
					<h4>
						{ __(
							'Enable Apple Pay and Google Pay on selected pages',
							'woocommerce-payments'
						) }
					</h4>
					<ul className="payment-request-settings__location">
						<li>
							<CheckboxControl
								disabled={ ! isPaymentRequestEnabled }
								checked={
									isPaymentRequestEnabled &&
									paymentRequestLocations.includes(
										'checkout'
									)
								}
								onChange={ makeLocationChangeHandler(
									'checkout'
								) }
								label={ __(
									'Checkout Page',
									'woocommerce-payments'
								) }
							/>
						</li>
						<li>
							<CheckboxControl
								disabled={ ! isPaymentRequestEnabled }
								checked={
									isPaymentRequestEnabled &&
									paymentRequestLocations.includes(
										'product'
									)
								}
								onChange={ makeLocationChangeHandler(
									'product'
								) }
								label={ __(
									'Product Page',
									'woocommerce-payments'
								) }
							/>
						</li>
						<li>
							<CheckboxControl
								disabled={ ! isPaymentRequestEnabled }
								checked={
									isPaymentRequestEnabled &&
									paymentRequestLocations.includes( 'cart' )
								}
								onChange={ makeLocationChangeHandler( 'cart' ) }
								label={ __(
									'Cart Page',
									'woocommerce-payments'
								) }
							/>
						</li>
					</ul>
				</CardBody>
			) }

			{ 'general' === section && (
				<GeneralPaymentRequestButtonSettings type="google/apple" />
			) }
		</Card>
	);
};

export default PaymentRequestSettings;
