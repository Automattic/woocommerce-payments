/**
 * External dependencies
 */
import { CheckboxControl, ExternalLink } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { useEffect, useRef, useContext } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { useWCPaySubscriptions } from 'wcpay/data';
import interpolateComponents from '@automattic/interpolate-components';
import WCPaySettingsContext from '../wcpay-settings-context';

const WCPaySubscriptionsToggle = () => {
	const [
		isWCPaySubscriptionsEnabled,
		isWCPaySubscriptionsEligible,
		updateIsWCPaySubscriptionsEnabled,
	] = useWCPaySubscriptions();

	const headingRef = useRef( null );

	const { setHasChanges } = useContext( WCPaySettingsContext );

	useEffect( () => {
		if ( ! headingRef.current ) return;

		headingRef.current.focus();
	}, [] );

	const handleWCPaySubscriptionsStatusChange = ( value ) => {
		updateIsWCPaySubscriptionsEnabled( value );
		setHasChanges( true );
	};

	/**
	 * Only show the toggle if the site doesn't have WC Subscriptions active and is eligible
	 * for wcpay subscriptions or if wcpay subscriptions are already enabled.
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
						<ExternalLink href="https://woocommerce.com/document/woopayments/subscriptions/" />
					),
				},
			} ) }
			checked={ isWCPaySubscriptionsEnabled }
			onChange={ handleWCPaySubscriptionsStatusChange }
		/>
	) : null;
};

export default WCPaySubscriptionsToggle;
