/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import Spotlight from 'components/spotlight';
import { usePromotions, usePromotionActions } from 'data';
import { Promotion } from 'data/promotions/types';
import { recordEvent } from 'tracks';

/**
 * Determine a human-readable source identifier based on the current page.
 *
 * @return {string} Source identifier for tracking.
 */
const getPageSource = (): string => {
	const path = window.location.pathname + window.location.search;

	if ( path.includes( 'path=%2Fpayments%2Foverview' ) ) {
		return 'wcpay-overview';
	}
	if ( path.includes( 'path=%2Fpayments%2Fsettings' ) ) {
		return 'wcpay-settings';
	}
	if (
		path.includes( 'page=wc-settings' ) &&
		path.includes( 'tab=checkout' )
	) {
		return 'wc-settings-payments';
	}

	return 'unknown';
};

/**
 * Container component that fetches promotions and renders the Spotlight component.
 *
 * This component:
 * - Fetches promotions from the API
 * - Filters for 'spotlight' type promotions
 * - Only displays if account is onboarded (status is 'complete' or 'enabled')
 * - Handles activation and dismissal of promotions
 */
const SpotlightPromotion: React.FC = () => {
	const { promotions, isLoading } = usePromotions();
	const { activatePromotion, dismissPromotion } = usePromotionActions();

	// Check if account is onboarded - only show if status is 'complete' or 'enabled'
	const accountStatus = window.wcpaySettings?.accountStatus?.status;
	const isAccountOnboarded =
		accountStatus === 'complete' || accountStatus === 'enabled';

	// Don't render if account is not onboarded or data is still loading
	if ( ! isAccountOnboarded || isLoading ) {
		return null;
	}

	// Don't render if no promotions available
	if ( ! promotions || promotions.length === 0 ) {
		return null;
	}

	// Find the first spotlight promotion
	const spotlightPromotion: Promotion | undefined = promotions.find(
		( promo ) => promo.type === 'spotlight'
	);

	// No spotlight promotion available
	if ( ! spotlightPromotion ) {
		return null;
	}

	/**
	 * Get common event properties for tracking.
	 */
	const getEventProperties = () => ( {
		promotion_id: spotlightPromotion.promo_id,
		payment_method: spotlightPromotion.payment_method,
		id: spotlightPromotion.id,
		display_context: 'spotlight',
		source: getPageSource(),
		path: window.location.pathname + window.location.search,
	} );

	const handleView = () => {
		recordEvent(
			'wcpay_payment_method_promotion_view',
			getEventProperties()
		);
	};

	const handlePrimaryClick = () => {
		recordEvent(
			'wcpay_payment_method_promotion_activate_click',
			getEventProperties()
		);
		activatePromotion( spotlightPromotion.promo_id );
	};

	const handleSecondaryClick = () => {
		recordEvent(
			'wcpay_payment_method_promotion_secondary_click',
			getEventProperties()
		);
		if ( spotlightPromotion.tc_url ) {
			try {
				const parsedUrl = new URL( spotlightPromotion.tc_url );
				if (
					parsedUrl.protocol === 'https:' ||
					parsedUrl.protocol === 'http:'
				) {
					window.open(
						spotlightPromotion.tc_url,
						'_blank',
						'noopener,noreferrer'
					);
				}
			} catch {
				// Invalid URL, don't open
			}
		}
	};

	const handleDismiss = () => {
		recordEvent(
			'wcpay_payment_method_promotion_dismiss',
			getEventProperties()
		);
		dismissPromotion( spotlightPromotion.id );
	};

	const handleTermsClick = () => {
		recordEvent( 'wcpay_payment_method_promotion_link_click', {
			...getEventProperties(),
			link_type: 'terms',
		} );
	};

	// Build disclaimer content if footnote exists
	let disclaimer: React.ReactNode | undefined;
	if ( spotlightPromotion.footnote ) {
		disclaimer = (
			<>
				{ spotlightPromotion.footnote }{ ' ' }
				<a
					href={ spotlightPromotion.tc_url }
					target="_blank"
					rel="noopener noreferrer"
					onClick={ handleTermsClick }
				>
					{ spotlightPromotion.tc_label }
				</a>
			</>
		);
	}

	return (
		<Spotlight
			heading={ spotlightPromotion.title }
			description={ spotlightPromotion.description }
			disclaimer={ disclaimer }
			image={ spotlightPromotion.image }
			primaryButtonLabel={ spotlightPromotion.cta_label }
			onPrimaryClick={ handlePrimaryClick }
			secondaryButtonLabel={ __( 'Learn more', 'woocommerce-payments' ) }
			onSecondaryClick={ handleSecondaryClick }
			onDismiss={ handleDismiss }
			onView={ handleView }
		/>
	);
};

export default SpotlightPromotion;
