/** @format **/

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	Button,
	Card,
	CardBody,
	CardDivider,
	CheckboxControl,
} from '@wordpress/components';
import interpolateComponents from 'interpolate-components';
import { getPaymentMethodSettingsUrl } from '../../utils';

/**
 * Internal dependencies
 */
import {
	useDigitalWalletsEnabledSettings,
	useDigitalWalletsLocations,
} from 'data';

const DigitalWallets = () => {
	const [
		isDigitalWalletsEnabled,
		updateIsDigitalWalletsEnabled,
	] = useDigitalWalletsEnabledSettings();
	const [
		digitalWalletsLocations,
		updateDigitalWalletsLocations,
	] = useDigitalWalletsLocations();

	const makeLocationChangeHandler = ( location ) => ( status ) => {
		updateDigitalWalletsLocations( {
			[ location ]: status,
		} );
	};

	return (
		<Card className="digital-wallets">
			<CardBody size="large">
				<CheckboxControl
					checked={ isDigitalWalletsEnabled }
					onChange={ updateIsDigitalWalletsEnabled }
					label={ __(
						'Enable 1-click checkouts',
						'woocommerce-payments'
					) }
					/* eslint-disable jsx-a11y/anchor-has-content */
					help={ interpolateComponents( {
						mixedString: __(
							'By enabling this feature, you agree to {{stripeLink}}Stripe{{/stripeLink}}, ' +
								'{{appleLink}}Apple{{/appleLink}}, {{googleLink}}Google{{/googleLink}} ' +
								"and {{microsoftLink}}Microsoft{{/microsoftLink}}'s terms of use.",
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
							microsoftLink: (
								<a
									target="_blank"
									rel="noreferrer"
									href="https://www.microsoft.com/en/servicesagreement/"
								/>
							),
						},
					} ) }
					/* eslint-enable jsx-a11y/anchor-has-content */
				/>
				<h4>
					{ __(
						'Show 1-click checkouts on:',
						'woocommerce-payments'
					) }
				</h4>
				<ul>
					<li>
						<CheckboxControl
							disabled={ ! isDigitalWalletsEnabled }
							checked={
								isDigitalWalletsEnabled &&
								digitalWalletsLocations.checkout
							}
							onChange={ makeLocationChangeHandler( 'checkout' ) }
							label={ __( 'Checkout', 'woocommerce-payments' ) }
						/>
					</li>
					<li>
						<CheckboxControl
							disabled={ ! isDigitalWalletsEnabled }
							checked={
								isDigitalWalletsEnabled &&
								digitalWalletsLocations.product_page
							}
							onChange={ makeLocationChangeHandler(
								'product_page'
							) }
							label={ __(
								'Product page',
								'woocommerce-payments'
							) }
						/>
					</li>
					<li>
						<CheckboxControl
							disabled={ ! isDigitalWalletsEnabled }
							checked={
								isDigitalWalletsEnabled &&
								digitalWalletsLocations.cart
							}
							onChange={ makeLocationChangeHandler( 'cart' ) }
							label={ __( 'Cart', 'woocommerce-payments' ) }
						/>
					</li>
				</ul>
			</CardBody>
			<CardDivider />
			<CardBody>
				<Button
					isSecondary
					href={ getPaymentMethodSettingsUrl( 'digital_wallets' ) }
				>
					{ __( 'Customize appearance', 'woocommerce-payments' ) }
				</Button>
			</CardBody>
		</Card>
	);
};

export default DigitalWallets;
