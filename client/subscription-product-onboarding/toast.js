/**
 * External dependencies
 */
import { useDispatch } from '@wordpress/data';
import { useEffect } from '@wordpress/element';
import { registerPlugin } from '@wordpress/plugins';
import { removeQueryArgs } from '@wordpress/url';
import { __ } from '@wordpress/i18n';

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
			__(
				'Thank you for setting up WooCommerce Payments! We’ve published your first subscription product.',
				'woocommerce-payments'
			)
		);
	}, [ createInfoNotice ] );

	return null;
};

registerPlugin( 'wcpay-subscription-product-onboarding-toast', {
	icon: null,
	render: SubscriptionProductOnboardingToast,
	scope: 'woocommerce',
} );
