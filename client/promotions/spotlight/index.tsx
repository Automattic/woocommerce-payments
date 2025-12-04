/** @format */

/**
 * External dependencies
 */
import React, { useCallback, useMemo } from 'react';

/**
 * Internal dependencies
 */
import Spotlight from 'components/spotlight';
import { usePmPromotions, usePmPromotionActions } from 'wcpay/data';
import { PmPromotion } from 'data/pm-promotions/types';
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
 * - Handles activation and dismissal of promotions
 */
const SpotlightPromotion: React.FC = () => {
	const { pmPromotions, isLoading } = usePmPromotions();
	const { activatePmPromotion, dismissPmPromotion } = usePmPromotionActions();

	// Memoize the spotlight promotion lookup to prevent recalculation on every render.
	const spotlightPromotion: PmPromotion | undefined = useMemo(
		() => pmPromotions?.find( ( promo ) => promo.type === 'spotlight' ),
		[ pmPromotions ]
	);

	/**
	 * Get common event properties for tracking.
	 * Memoized to maintain reference equality.
	 */
	const getEventProperties = useCallback(
		() => ( {
			promo_id: spotlightPromotion?.promo_id,
			payment_method: spotlightPromotion?.payment_method,
			display_context: 'spotlight',
			source: getPageSource(),
			path: window.location.pathname + window.location.search,
		} ),
		[ spotlightPromotion?.promo_id, spotlightPromotion?.payment_method ]
	);

	const handleView = useCallback( () => {
		recordEvent(
			'wcpay_payment_method_promotion_view',
			getEventProperties()
		);
	}, [ getEventProperties ] );

	const handlePrimaryClick = useCallback( () => {
		if ( ! spotlightPromotion ) return;
		recordEvent(
			'wcpay_payment_method_promotion_activate_click',
			getEventProperties()
		);
		activatePmPromotion( spotlightPromotion.id );
	}, [ getEventProperties, activatePmPromotion, spotlightPromotion ] );

	const handleSecondaryClick = useCallback( () => {
		if ( ! spotlightPromotion ) return;
		recordEvent( 'wcpay_payment_method_promotion_link_click', {
			...getEventProperties(),
			link_type: 'terms',
		} );
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
	}, [ getEventProperties, spotlightPromotion ] );

	const handleDismiss = useCallback( () => {
		if ( ! spotlightPromotion ) return;
		recordEvent(
			'wcpay_payment_method_promotion_dismiss_click',
			getEventProperties()
		);
		dismissPmPromotion( spotlightPromotion.id );
	}, [ getEventProperties, dismissPmPromotion, spotlightPromotion ] );

	// Don't render if data is still loading.
	if ( isLoading ) {
		return null;
	}

	// Don't render if no promotions available.
	if ( ! pmPromotions || pmPromotions.length === 0 ) {
		return null;
	}

	// No spotlight promotion available.
	if ( ! spotlightPromotion ) {
		return null;
	}

	return (
		<Spotlight
			badge={ spotlightPromotion.badge_text }
			badgeType={ spotlightPromotion.badge_type }
			heading={ spotlightPromotion.title }
			description={ spotlightPromotion.description }
			footnote={ spotlightPromotion.footnote }
			image={ spotlightPromotion.image }
			primaryButtonLabel={ spotlightPromotion.cta_label }
			onPrimaryClick={ handlePrimaryClick }
			secondaryButtonLabel={ spotlightPromotion.tc_label }
			onSecondaryClick={ handleSecondaryClick }
			onDismiss={ handleDismiss }
			onView={ handleView }
		/>
	);
};

export default SpotlightPromotion;
