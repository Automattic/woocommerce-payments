/** @format */
/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import CardBody from '../card-body';
import { Card, CheckboxControl, BaseControl } from '@wordpress/components';
import GeneralPaymentRequestButtonSettings from './general-payment-request-button-settings';
import {
	usePaymentRequestEnabledSettings,
	usePaymentRequestLocations,
	useAppleGooglePayInPaymentMethodsOptionsEnabledSettings,
} from 'wcpay/data';

const PaymentRequestSettings = ( { section } ) => {
	const [
		isPaymentRequestEnabled,
		updateIsPaymentRequestEnabled,
	] = usePaymentRequestEnabledSettings();

	const [
		isAppleGooglePayInPaymentMethodsOptionsEnabled,
		updateIsAppleGooglePayInPaymentMethodsOptionsEnabled,
	] = useAppleGooglePayInPaymentMethodsOptionsEnabledSettings();

	const [
		paymentRequestLocations,
		updatePaymentRequestLocations,
	] = usePaymentRequestLocations();

	const makeLocationChangeHandler = ( location ) => ( isChecked ) => {
		updatePaymentRequestLocations( location, isChecked );
	};

	return (
		<Card>
			{ section === 'enable' && (
				<CardBody className="wcpay-card-body">
					<div className="wcpay-payment-request-settings__enable">
						{ wcpaySettings.featureFlags
							.isDynamicCheckoutPlaceOrderButtonEnabled && (
							<CheckboxControl
								className="wcpay-payment-request-settings__enable__checkbox"
								checked={
									isAppleGooglePayInPaymentMethodsOptionsEnabled
								}
								onChange={
									updateIsAppleGooglePayInPaymentMethodsOptionsEnabled
								}
								label={ __(
									'Enable Apple Pay / Google Pay as options in the payment methods list',
									'woocommerce-payments'
								) }
								help={ __(
									'Customers with Apple Pay or Google Pay enabled will be able to pay with ' +
										'their preferred wallet as options in the payment methods list.',
									'woocommerce-payments'
								) }
								__nextHasNoMarginBottom
							/>
						) }
						<CheckboxControl
							className="wcpay-payment-request-settings__enable__checkbox"
							checked={ isPaymentRequestEnabled }
							onChange={ updateIsPaymentRequestEnabled }
							label={ __(
								'Enable Apple Pay / Google Pay as express payment buttons',
								'woocommerce-payments'
							) }
							help={ __(
								'Show express payment buttons on store pages for faster purchases. ' +
									'Customers with Apple Pay or Google Pay enabled will be able to pay with their preferred wallet.',
								'woocommerce-payments'
							) }
							__nextHasNoMarginBottom
						/>
						{ /* eslint-disable-next-line @wordpress/no-base-control-with-label-without-id */ }
						<BaseControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						>
							<ul className="payment-request-settings__location">
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
											'Show on product page',
											'woocommerce-payments'
										) }
										__nextHasNoMarginBottom
									/>
								</li>
								<li>
									<CheckboxControl
										disabled={ ! isPaymentRequestEnabled }
										checked={
											isPaymentRequestEnabled &&
											paymentRequestLocations.includes(
												'cart'
											)
										}
										onChange={ makeLocationChangeHandler(
											'cart'
										) }
										label={ __(
											'Show on cart page',
											'woocommerce-payments'
										) }
										__nextHasNoMarginBottom
									/>
								</li>
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
											'Show on checkout page',
											'woocommerce-payments'
										) }
										__nextHasNoMarginBottom
									/>
								</li>
							</ul>
						</BaseControl>
					</div>
				</CardBody>
			) }

			{ section === 'general' && (
				<GeneralPaymentRequestButtonSettings type="google/apple" />
			) }
		</Card>
	);
};

export default PaymentRequestSettings;
