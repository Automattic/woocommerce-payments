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
} from 'wcpay/data';
import GooglePayTestModeCompatibilityNotice from '../google-pay-test-mode-compatibility-notice';

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
			{ section === 'enable' && (
				<CardBody className="wcpay-card-body">
					<GooglePayTestModeCompatibilityNotice />
					<div className="wcpay-payment-request-settings__enable">
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
