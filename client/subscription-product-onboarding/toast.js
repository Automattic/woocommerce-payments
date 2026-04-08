/**
 * External dependencies
 */
import { useDispatch } from '@wordpress/data';
import { useEffect } from '@wordpress/element';
import { registerPlugin } from '@wordpress/plugins';
import { removeQueryArgs } from '@wordpress/url';
import { __, sprintf } from '@wordpress/i18n';

const { pluginScope } = window.wcpaySubscriptionProductOnboardingToast;

const SubscriptionProductOnboardingToast = () => {
	const { createInfoNotice } = useDispatch( 'core/notices' );

	useEffect( () => {
		if ( window?.history ) {
			window.history.replaceState(
				null,
				null,
				removeQueryArgs(
					window.location.href,
					'wcpay-subscriptions-onboarded'
				)
			);
		}

		createInfoNotice(
			sprintf(
				/* translators: %s: WooPayments */
				__(
					'Thank you for setting up %s! We’ve published your first subscription product.',
					'woocommerce-payments'
				),
				'WooPayments'
			)
		);
	}, [ createInfoNotice ] );

	return null;
};

registerPlugin( 'wcpay-subscription-product-onboarding-toast', {
	icon: null,
	render: SubscriptionProductOnboardingToast,
	scope: pluginScope,
} );
