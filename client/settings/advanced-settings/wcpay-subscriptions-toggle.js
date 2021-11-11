/**
 * External dependencies
 */
import { CheckboxControl, ExternalLink } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useEffect, useRef } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { useWCPaySubscriptions } from 'wcpay/data';
import interpolateComponents from 'interpolate-components';

const WCPaySubscriptionsToggle = () => {
	const [
		isWCPaySubscriptionsEnabled,
		isWCPaySubscriptionsEligible,
		isSubscriptionsPluginActive,
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

	return ! isSubscriptionsPluginActive &&
		( isWCPaySubscriptionsEligible || isWCPaySubscriptionsEnabled ) ? (
		<CheckboxControl
			label={ __(
				'Enable Subscriptions with WooCommerce Payments',
				'woocommerce-payments'
			) }
			help={ interpolateComponents( {
				mixedString: __(
					'Sell subscription products and services with WooCommerce Payments. {{learnMoreLink}}Learn more{{/learnMoreLink}}',
					'woocommerce-payments'
				),
				components: {
					learnMoreLink: (
						// eslint-disable-next-line max-len
						<ExternalLink href="https://woocommerce.com/document/payments/subscriptions/" />
					),
				},
			} ) }
			checked={ isWCPaySubscriptionsEnabled }
			onChange={ handleWCPaySubscriptionsStatusChange }
		/>
	) : null;
};

export default WCPaySubscriptionsToggle;
