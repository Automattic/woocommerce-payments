/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { CheckboxControl, ExternalLink } from '@wordpress/components';
import { useWCPaySubscriptions } from 'wcpay/data';
import interpolateComponents from '@automattic/interpolate-components';

const WCPaySubscriptionsToggle = () => {
	const [
		isWCPaySubscriptionsEnabled,
		isWCPaySubscriptionsEligible,
		updateIsWCPaySubscriptionsEnabled,
	] = useWCPaySubscriptions();

	const handleWCPaySubscriptionsStatusChange = ( value ) => {
		// Prevent enabling subscriptions - feature has been removed.
		if ( value ) {
			return;
		}
		updateIsWCPaySubscriptionsEnabled( value );
	};

	/**
	 * Show the toggle if the site doesn't have WC Subscriptions active.
	 * The toggle is disabled to prevent enabling bundled subscriptions.
	 * However, if subscriptions are currently enabled, allow disabling them.
	 */
	return ! wcpaySettings.isSubscriptionsActive &&
		isWCPaySubscriptionsEligible ? (
		<CheckboxControl
			label={ sprintf(
				/* translators: %s: WooPayments */
				__( 'Enable Subscriptions with %s', 'woocommerce-payments' ),
				'WooPayments'
			) }
			help={ interpolateComponents( {
				mixedString: __(
					// eslint-disable-next-line max-len
					'This feature is deprecated. Existing subscription renewals will continue to work, but creating or managing subscriptions is no longer available. Install {{learnMoreLink}}WooCommerce Subscriptions{{/learnMoreLink}} to continue managing subscriptions.',
					'woocommerce-payments'
				),
				components: {
					learnMoreLink: (
						// eslint-disable-next-line max-len
						// @ts-expect-error: children is provided when interpolating the component
						<ExternalLink href="https://woocommerce.com/products/woocommerce-subscriptions/" />
					),
				},
			} ) }
			checked={ isWCPaySubscriptionsEnabled }
			onChange={ handleWCPaySubscriptionsStatusChange }
			disabled={ ! isWCPaySubscriptionsEnabled }
			__nextHasNoMarginBottom
		/>
	) : null;
};

export default WCPaySubscriptionsToggle;
