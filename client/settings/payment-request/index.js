/** @format **/

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	Button,
	Card,
	CardDivider,
	CheckboxControl,
} from '@wordpress/components';
import classNames from 'classnames';
import interpolateComponents from 'interpolate-components';

/**
 * Internal dependencies
 */
import { getPaymentMethodSettingsUrl } from '../../utils';
import {
	usePaymentRequestEnabledSettings,
	usePaymentRequestLocations,
} from 'wcpay/data';
import CardBody from '../card-body';
import './style.scss';

const PaymentRequest = () => {
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
		<Card className="payment-request">
			<CardBody>
				<CheckboxControl
					checked={ isPaymentRequestEnabled }
					onChange={ updateIsPaymentRequestEnabled }
					label={ __(
						'Enable express checkouts',
						'woocommerce-payments'
					) }
					/* eslint-disable jsx-a11y/anchor-has-content */
					help={ interpolateComponents( {
						mixedString: __(
							'By enabling this feature, you agree to {{stripeLink}}Stripe{{/stripeLink}}, ' +
								"{{appleLink}}Apple{{/appleLink}}, and {{googleLink}}Google{{/googleLink}}'s terms of use.",
							'woocommerce-payments'
						),
						components: {
							stripeLink: (
								<a
									target="_blank"
									rel="noreferrer"
									href="https://stripe.com/apple-pay/legal"
								/>
							),
							appleLink: (
								<a
									target="_blank"
									rel="noreferrer"
									href="https://developer.apple.com/apple-pay/acceptable-use-guidelines-for-websites/"
								/>
							),
							googleLink: (
								<a
									target="_blank"
									rel="noreferrer"
									href="https://androidpay.developers.google.com/terms/sellertos"
								/>
							),
						},
					} ) }
					/* eslint-enable jsx-a11y/anchor-has-content */
				/>
				<div
					className={ classNames(
						'payment-request__additional-controls-wrapper',
						{ 'is-enabled': isPaymentRequestEnabled }
					) }
				>
					<h4>
						{ __(
							'Show express checkouts on',
							'woocommerce-payments'
						) }
					</h4>
					<ul>
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
									'Checkout',
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
									'Product page',
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
								label={ __( 'Cart', 'woocommerce-payments' ) }
							/>
						</li>
					</ul>
				</div>
			</CardBody>
			<CardDivider />
			<CardBody>
				<Button
					isSecondary
					href={ getPaymentMethodSettingsUrl( 'payment_request' ) }
				>
					{ __( 'Customize appearance', 'woocommerce-payments' ) }
				</Button>
			</CardBody>
		</Card>
	);
};

export default PaymentRequest;
