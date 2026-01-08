/**
 * External dependencies
 */
import React, { useContext } from 'react';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import InlineNotice from 'wcpay/components/inline-notice';
import WCPaySettingsContext from '../wcpay-settings-context';
import {
	useWooPayEnabledSettings,
	usePaymentRequestEnabledSettings,
	useAmazonPayEnabledSettings,
} from 'wcpay/data';

type ExpressCheckoutMethod = 'woopay' | 'google/apple' | 'amazon_pay';

interface ExpressCheckoutSettingsNoticesProps {
	currentMethod: ExpressCheckoutMethod;
}

/**
 * Builds a human-readable list of button names.
 * E.g., ["WooPay"] => "WooPay button"
 * E.g., ["WooPay", "Apple Pay / Google Pay"] => "WooPay and Apple Pay / Google Pay buttons"
 * E.g., ["WooPay", "Apple Pay / Google Pay", "Amazon Pay"] => "WooPay, Apple Pay / Google Pay, and Amazon Pay buttons"
 */
const formatButtonList = ( buttonNames: string[] ) => {
	if ( buttonNames.length === 1 ) {
		return sprintf(
			/* translators: %s: name of a button type (e.g., "WooPay") */
			__( '%s button', 'woocommerce-payments' ),
			buttonNames[ 0 ]
		);
	}

	if ( buttonNames.length === 2 ) {
		return sprintf(
			/* translators: %1$s and %2$s: names of button types */
			__( '%1$s and %2$s buttons', 'woocommerce-payments' ),
			buttonNames[ 0 ],
			buttonNames[ 1 ]
		);
	}

	// For 3+ items, use Oxford comma style
	const lastItem = buttonNames[ buttonNames.length - 1 ];
	const otherItems = buttonNames.slice( 0, -1 ).join( ', ' );

	return sprintf(
		/* translators: %1$s: comma-separated list of button types, %2$s: last button type in the list */
		__( '%1$s, and %2$s buttons', 'woocommerce-payments' ),
		otherItems,
		lastItem
	);
};

const ExpressCheckoutSettingsNotices: React.FC< ExpressCheckoutSettingsNoticesProps > = ( {
	currentMethod,
} ) => {
	const [ isWooPayEnabled ] = useWooPayEnabledSettings();
	const [ isPaymentRequestEnabled ] = usePaymentRequestEnabledSettings();
	const [ isAmazonPayEnabled ] = useAmazonPayEnabledSettings();
	const {
		featureFlags: {
			woopay: isWooPayFeatureFlagEnabled,
			amazonPay: isAmazonPayFeatureFlagEnabled,
		},
	} = useContext( WCPaySettingsContext );

	// need to ensure that if the feature flag is disabled, we ignore the button's state.
	const isWooPayEffectivelyEnabled =
		isWooPayEnabled && isWooPayFeatureFlagEnabled;
	const isAmazonPayEffectivelyEnabled =
		isAmazonPayEnabled && isAmazonPayFeatureFlagEnabled;

	const otherButtons = [
		currentMethod !== 'woopay' &&
			isWooPayEffectivelyEnabled &&
			__( 'WooPay', 'woocommerce-payments' ),
		currentMethod !== 'google/apple' &&
			isPaymentRequestEnabled &&
			__( 'Apple Pay / Google Pay', 'woocommerce-payments' ),
		currentMethod !== 'amazon_pay' &&
			isAmazonPayEffectivelyEnabled &&
			__( 'Amazon Pay', 'woocommerce-payments' ),
	].filter( Boolean );

	if ( otherButtons.length === 0 ) {
		return null;
	}

	const formattedList = formatButtonList( otherButtons as string[] );

	return (
		<>
			<InlineNotice
				status="warning"
				icon={ true }
				isDismissible={ false }
			>
				{ sprintf(
					/* translators: %s: formatted list of button types that share these settings */
					__(
						'These settings will also apply to the %s on your store.',
						'woocommerce-payments'
					),
					formattedList
				) }
			</InlineNotice>
			<InlineNotice
				status="warning"
				icon={ true }
				isDismissible={ false }
			>
				{ __(
					'Some appearance settings may be overridden in the express payment section of the Cart & Checkout blocks.',
					'woocommerce-payments'
				) }
			</InlineNotice>
		</>
	);
};

export default ExpressCheckoutSettingsNotices;
