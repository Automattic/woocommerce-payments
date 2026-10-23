/**
 * External dependencies
 */
import React, { useState } from 'react';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import InlineNotice from 'wcpay/components/inline-notice';
import FraudPreventionSettingsContext from '../settings/fraud-protection/advanced-settings/context';
import AVSMismatchRuleCard from '../settings/fraud-protection/advanced-settings/cards/avs-mismatch';
import CVCVerificationRuleCard from '../settings/fraud-protection/advanced-settings/cards/cvc-verification';
import InternationalIPAddressRuleCard from '../settings/fraud-protection/advanced-settings/cards/international-ip-address';
import IPAddressMismatchRuleCard from '../settings/fraud-protection/advanced-settings/cards/ip-address-mismatch';
import AddressMismatchRuleCard from '../settings/fraud-protection/advanced-settings/cards/address-mismatch';
import PurchasePriceThresholdRuleCard, {
	PurchasePriceThresholdValidation,
} from '../settings/fraud-protection/advanced-settings/cards/purchase-price-threshold';
import OrderItemsThresholdRuleCard, {
	OrderItemsThresholdValidation,
} from '../settings/fraud-protection/advanced-settings/cards/order-items-threshold';
import {
	Outcomes,
	ProtectionLevel,
	Rules,
} from '../settings/fraud-protection/advanced-settings/constants';
import {
	readRuleset,
	writeRuleset,
} from '../settings/fraud-protection/advanced-settings/utils';
import {
	FraudProtectionRule,
	ProtectionSettingsUI,
} from '../settings/fraud-protection/interfaces';
import { EditProps, Settings } from './express-checkout-controls';

type Ruleset = FraudProtectionRule[] | string;

const getRuleset = ( item: Settings ) =>
	item.advanced_fraud_protection_settings as Ruleset;

/**
 * Reads the rules being edited, which can include a threshold rule without limits yet.
 * The existing reader expects every rule to have a check, as only valid rules are saved.
 */
export const readEditedRuleset = ( ruleset: Ruleset ): ProtectionSettingsUI => {
	if ( ! Array.isArray( ruleset ) ) {
		return readRuleset( ruleset );
	}
	const settings = readRuleset(
		ruleset.filter( ( rule ) => rule.check !== null )
	);
	ruleset
		.filter( ( rule ) => rule.check === null )
		.forEach( ( rule ) => {
			settings[ rule.key ] = {
				...settings[ rule.key ],
				enabled: true,
				block:
					! wcpaySettings.featureFlags.isFRTReviewFeatureActive ||
					rule.outcome === Outcomes.BLOCK,
			};
		} );
	return settings;
};

/** Turns the edited rules into settings edits, setting the protection level the way the existing page saves it. */
export const getRulesEdits = ( settings: ProtectionSettingsUI ): Settings => {
	const ruleset = writeRuleset( settings );

	// The AVS rule's default is read from the account until the account cache is updated, as on the existing page.
	if ( wcpaySettings?.accountStatus?.fraudProtection ) {
		wcpaySettings.accountStatus.fraudProtection.declineOnAVSFailure =
			ruleset.some(
				( rule ) => rule.key === Rules.RULE_AVS_VERIFICATION
			);
	}

	return {
		advanced_fraud_protection_settings: ruleset,
		current_protection_level:
			ruleset.length > 0
				? ProtectionLevel.ADVANCED
				: ProtectionLevel.BASIC,
	};
};

/** The first validation error of the edited rules, using the existing page's validators. */
export const getRulesError = ( item: Settings ): string | null => {
	if ( getRuleset( item ) === 'error' ) {
		return __(
			'There was an error retrieving your fraud protection settings.',
			'woocommerce-payments'
		);
	}
	const settings = readEditedRuleset( getRuleset( item ) );
	let error: string | null = null;
	const setError = ( message: React.SetStateAction< string | null > ) => {
		error = typeof message === 'function' ? message( error ) : message;
	};
	if (
		! PurchasePriceThresholdValidation(
			settings[ Rules.RULE_PURCHASE_PRICE_THRESHOLD ],
			setError
		)
	) {
		return error;
	}
	OrderItemsThresholdValidation(
		settings[ Rules.RULE_ORDER_ITEMS_THRESHOLD ],
		setError
	);
	return error;
};

/** The existing rule cards, editing the ruleset in the settings entity. */
export const FraudRules = ( { data, onChange }: EditProps ) => {
	const ruleset = getRuleset( data );
	const [ settings, setSettings ] = useState( () =>
		readEditedRuleset( ruleset )
	);

	// Keeps the edits as typed, and starts again from the ruleset when it changes elsewhere, such as on discard.
	const [ ownRuleset, setOwnRuleset ] = useState( ruleset );
	if ( ownRuleset !== ruleset ) {
		setOwnRuleset( ruleset );
		setSettings( readEditedRuleset( ruleset ) );
	}

	if ( ruleset === 'error' ) {
		return (
			<InlineNotice status="error" isDismissible={ false }>
				{ __(
					'There was an error retrieving your fraud protection settings.' +
						' Please refresh the page to try again.',
					'woocommerce-payments'
				) }
			</InlineNotice>
		);
	}

	const setProtectionSettingsUI = (
		action: React.SetStateAction< ProtectionSettingsUI >
	) => {
		const next = typeof action === 'function' ? action( settings ) : action;
		const edits = getRulesEdits( next );
		setOwnRuleset( edits.advanced_fraud_protection_settings as Ruleset );
		setSettings( next );
		onChange( edits );
	};

	return (
		<FraudPreventionSettingsContext.Provider
			value={ {
				protectionSettingsUI: settings,
				setProtectionSettingsUI,
				setIsDirty: () => null,
			} }
		>
			<div className="wcpay-settings-screen__fraud-rules">
				<AVSMismatchRuleCard />
				<InternationalIPAddressRuleCard />
				<IPAddressMismatchRuleCard />
				<AddressMismatchRuleCard />
				<PurchasePriceThresholdRuleCard />
				<OrderItemsThresholdRuleCard />
				<CVCVerificationRuleCard />
			</div>
		</FraudPreventionSettingsContext.Provider>
	);
};
