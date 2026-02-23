/**
 * External dependencies
 */
import React, { useCallback, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { Icon, external } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import Spotlight from 'components/spotlight';
import { useReviewPromptState } from './hooks';
import { recordEvent } from 'wcpay/tracks';
import MegaphoneIcon from './megaphone-icon';

const wordpressOrgReviewUrl =
	'https://wordpress.org/support/plugin/woocommerce-payments/reviews/#new-post';
const marketplaceReviewUrl =
	'https://woocommerce.com/products/woocommerce-payments/#reviews';

/**
 * Helper to record an event with base properties and optional additional properties.
 */
const recordPromptEvent = (
	eventName: string,
	baseProperties: Record< string, unknown >,
	additionalProperties?: Record< string, unknown >
) => {
	recordEvent( eventName, { ...baseProperties, ...additionalProperties } );
};

/**
 * Helper to calculate time-to-click properties.
 */
const getTimeToClickProps = (
	viewTimestamp: number | null
): Record< string, number > => {
	const timeToClickMs = viewTimestamp ? Date.now() - viewTimestamp : null;
	return timeToClickMs !== null ? { time_to_click_ms: timeToClickMs } : {};
};

/**
 * Helper to get base event properties per PRO2-35 telemetry requirements.
 * Eligibility is always true when this script loads (checked server-side).
 */
const getBaseEventProperties = () => {
	return {
		prompt_id: 'phase0_payments_settings_001',
		extension: 'woopayments',
		location: 'payments_settings_top_level',
		trigger: 'none',
		flag_enabled: true,
		version: window.wcpayReviewPromptSettings?.version || 'unknown',
	};
};

const ReviewPrompt: React.FC = () => {
	const { dismissPrompt, setMaybeLater } = useReviewPromptState();

	const [ viewTimestamp, setViewTimestamp ] = useState< number | null >(
		null
	);
	const [ isVisible, setIsVisible ] = useState( true );

	const handleView = useCallback( () => {
		const timestamp = Date.now();
		setViewTimestamp( timestamp );
		recordPromptEvent(
			'payments_review_prompt_shown',
			getBaseEventProperties()
		);
	}, [] );

	const handlePrimaryClick = useCallback( async () => {
		// Determine destination based on connection state
		const isLive = window.wcpayReviewPromptSettings?.isLive;
		const destination = isLive ? 'wordpress_org' : 'marketplace';
		const reviewUrl = isLive ? wordpressOrgReviewUrl : marketplaceReviewUrl;

		const baseProps = getBaseEventProperties();
		const eventProps = {
			action: 'write_review',
			destination,
			...getTimeToClickProps( viewTimestamp ),
		};

		recordPromptEvent(
			'payments_review_prompt_action',
			baseProps,
			eventProps
		);
		recordPromptEvent(
			'payments_review_destination_selected',
			baseProps,
			eventProps
		);

		const windowOpened = window.open(
			reviewUrl,
			'_blank',
			'noopener,noreferrer'
		);
		if ( ! windowOpened ) {
			// Make sure the request completes before redirecting away.
			await dismissPrompt();

			// Fallback: navigate away from the current tab.
			window.location.href = reviewUrl;
		} else {
			dismissPrompt();
		}
		setIsVisible( false );
	}, [ viewTimestamp, dismissPrompt ] );

	const handleSecondaryClick = useCallback( () => {
		recordPromptEvent(
			'payments_review_prompt_action',
			getBaseEventProperties(),
			{
				action: 'maybe_later',
				...getTimeToClickProps( viewTimestamp ),
			}
		);
		setMaybeLater();
		setIsVisible( false );
	}, [ viewTimestamp, setMaybeLater ] );

	const handleDismiss = useCallback( () => {
		recordPromptEvent(
			'payments_review_prompt_action',
			getBaseEventProperties(),
			{
				action: 'dismiss_x',
				...getTimeToClickProps( viewTimestamp ),
			}
		);
		dismissPrompt();
		setIsVisible( false );
	}, [ viewTimestamp, dismissPrompt ] );

	if ( ! isVisible ) {
		return null;
	}

	return (
		<Spotlight
			icon={ <MegaphoneIcon /> }
			heading={ __(
				'Enjoying WooPayments so far?',
				'woocommerce-payments'
			) }
			description={ __(
				'Your feedback shapes our roadmap and supports the WooCommerce community. We are all ears!',
				'woocommerce-payments'
			) }
			primaryButtonLabel={
				<>
					{ __( 'Leave review', 'woocommerce-payments' ) }
					<Icon
						icon={ external }
						size={ 20 }
						style={ { marginLeft: '6px' } }
					/>
				</>
			}
			onPrimaryClick={ handlePrimaryClick }
			secondaryButtonLabel={ __( 'Maybe later', 'woocommerce-payments' ) }
			onSecondaryClick={ handleSecondaryClick }
			onDismiss={ handleDismiss }
			onView={ handleView }
			showImmediately={ false }
			showDelayMs={ 2000 }
			reverseButtons={ true }
		/>
	);
};

export default ReviewPrompt;
