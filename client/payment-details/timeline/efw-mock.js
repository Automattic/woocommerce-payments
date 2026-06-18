/** @format **/

/**
 * MOCKUP (Early Fraud Warnings in the dashboard — issue #304).
 *
 * This module fabricates Early Fraud Warning (EFW) data so the timeline UI can
 * be previewed before the real server-side EFW pipeline exists. None of this
 * talks to Stripe or the server — it injects a synthetic `early_fraud_warning`
 * event into the timeline so the new `map-events.js` case can be exercised.
 *
 * Pick a scenario with the `efw_mock` query param on the transaction details
 * URL, e.g. `…/transactions/details/{id}?efw_mock=actionable_stolen`. With no
 * param, the scenario is derived deterministically from the payment intent ID,
 * so each transaction is stable across reloads but the store shows variety.
 *
 * Remove this file (and its callers) when the real EFW feature lands.
 */

/**
 * External dependencies
 */
import { getQuery } from '@woocommerce/navigation';

/**
 * Human-readable labels for Stripe's `fraud_type` enum on the EFW object.
 * See https://docs.stripe.com/api/radar/early_fraud_warnings/object
 */
export const EFW_FRAUD_TYPE_LABELS = {
	card_never_received: 'Card never received',
	fraudulent_card_application: 'Fraudulent card application',
	made_with_counterfeit_card: 'Made with counterfeit card',
	made_with_lost_card: 'Made with lost card',
	made_with_stolen_card: 'Made with stolen card',
	misc: 'Other',
	unauthorized_use_of_card: 'Unauthorized use of card',
};

/**
 * The scenarios the mockup can render. `null` means "no EFW on this charge".
 */
const SCENARIOS = {
	none: null,
	actionable_stolen: {
		actionable: true,
		fraud_type: 'made_with_stolen_card',
	},
	actionable_unauthorized: {
		actionable: true,
		fraud_type: 'unauthorized_use_of_card',
	},
	resolved: {
		actionable: false,
		fraud_type: 'made_with_stolen_card',
	},
};

// `actionable` is a convenient alias for one of the actionable scenarios.
const SCENARIO_ALIASES = {
	actionable: 'actionable_stolen',
};

// Scenarios cycled through when no query param forces a specific one.
const DETERMINISTIC_POOL = [
	'actionable_stolen',
	'actionable_unauthorized',
	'resolved',
	'none',
];

const hashString = ( value ) => {
	let hash = 0;
	for ( let i = 0; i < value.length; i++ ) {
		hash = ( hash * 31 + value.charCodeAt( i ) ) % 2147483647;
	}
	return hash;
};

/**
 * Resolves which EFW scenario to render for a given seed (the payment intent
 * ID). The `efw_mock` query param wins; otherwise the seed is hashed into the
 * deterministic pool.
 *
 * @param {string} seed Stable identifier for the transaction (payment intent ID).
 * @return {Object|null} The scenario descriptor, or null for "no EFW".
 */
export const resolveEfwScenario = ( seed = '' ) => {
	const requested = getQuery().efw_mock;

	if ( requested ) {
		const key = SCENARIO_ALIASES[ requested ] ?? requested;
		if ( key in SCENARIOS ) {
			return SCENARIOS[ key ];
		}
	}

	const pick =
		DETERMINISTIC_POOL[ hashString( seed ) % DETERMINISTIC_POOL.length ];
	return SCENARIOS[ pick ];
};

/**
 * Returns a copy of the timeline with a synthetic EFW event injected, when the
 * resolved scenario calls for one. EFWs arrive after the charge, so the event
 * is placed at the front (most recent).
 *
 * @param {Array}  timeline The timeline events from the server.
 * @param {string} seed     Stable identifier for the transaction (payment intent ID).
 * @return {Array} The timeline, possibly with a mock EFW event prepended.
 */
export const injectMockEfwEvent = ( timeline, seed = '' ) => {
	// Never let the mock alter test output (it would pollute timeline
	// snapshots); webpack's DefinePlugin makes this 'production'/'development'
	// in the browser, so the mock still renders live.
	if ( process.env.NODE_ENV === 'test' ) {
		return timeline;
	}

	// No seed means there's no real transaction to anchor to.
	if ( ! Array.isArray( timeline ) || ! seed ) {
		return timeline;
	}

	const scenario = resolveEfwScenario( seed );
	if ( ! scenario ) {
		return timeline;
	}

	// Anchor the EFW shortly after the latest real event so the relative
	// ordering reads naturally; fall back to "now" on an empty timeline.
	const latest = timeline.reduce(
		( max, event ) => Math.max( max, event.datetime || 0 ),
		0
	);
	const datetime = ( latest || Math.floor( Date.now() / 1000 ) ) + 60;

	const mockEvent = {
		type: 'early_fraud_warning',
		datetime,
		fraud_type: scenario.fraud_type,
		actionable: scenario.actionable,
	};

	return [ mockEvent, ...timeline ];
};
