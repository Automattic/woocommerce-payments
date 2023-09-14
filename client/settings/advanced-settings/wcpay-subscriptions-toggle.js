/**
 * External dependencies
 */
import { CheckboxControl, ExternalLink } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { useEffect, useRef } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { useWCPaySubscriptions } from 'wcpay/data';
import interpolateComponents from '@automattic/interpolate-components';

const WCPaySubscriptionsToggle = () => {
	const [
		isWCPaySubscriptionsEnabled,
		isWCPaySubscriptionsEligible,
		updateIsWCPaySubscriptionsEnabled,
	] = useWCPaySubscriptions();

	const headingRef = useRef( null );

	useEffect( () => {
		if ( ! headingRef.current ) return;

		headingRef.current.focus();
	}, [] );

	const handleWCPaySubscriptionsStatusChange = ( value ) => {
		updateIsWCPaySubscriptionsEnabled( value );
	};

	/**
	 * Only show the toggle if the site doesn't have WC Subscriptions active and is eligible
	 * for wcpay subscriptions or if wcpay subscriptions are already enabled.
	 */
	return ! wcpaySettings.isSubscriptionsActive &&
		( isWCPaySubscriptionsEligible || isWCPaySubscriptionsEnabled ) ? (
		<CheckboxControl
			label={ sprintf(
				/* translators: %s: WooPayments */
				__( 'Enable Subscriptions with %s', 'woocommerce-payments' ),
				'WooPayments'
			) }
			help={ interpolateComponents( {
				mixedString: sprintf(
					/* translators: %s: WooPayments */
					__(
						'Sell subscription products and services with %s. {{learnMoreLink}}Learn more{{/learnMoreLink}}',
						'woocommerce-payments'
					),
					'WooPayments'
				),
				components: {
					learnMoreLink: (
						// eslint-disable-next-line max-len
						<ExternalLink href="https://woocommerce.com/document/woopayments/built-in-subscriptions/" />
					),
				},
			} ) }
			checked={ isWCPaySubscriptionsEnabled }
			onChange={ handleWCPaySubscriptionsStatusChange }
		/>
	) : null;
};

export default WCPaySubscriptionsToggle;
