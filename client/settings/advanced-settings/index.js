/**
 * External dependencies
 */
import React from 'react';
import { Card } from 'wcpay/components/wp-components-wrapped';

/**
 * Internal dependencies
 */
import DebugMode from './debug-mode';
import MultiCurrencyToggle from './multi-currency-toggle';
import WCPaySubscriptionsToggle from './wcpay-subscriptions-toggle';
import './style.scss';
import CardBody from '../card-body';
import StripeBillingSection from './stripe-billing-section';

const AdvancedSettings = () => {
	return (
		<>
			<Card>
				<CardBody>
					<MultiCurrencyToggle />
					{ wcpaySettings.isSubscriptionsActive &&
					wcpaySettings.isStripeBillingEligible ? (
						<StripeBillingSection />
					) : (
						<WCPaySubscriptionsToggle />
					) }
					<DebugMode />
				</CardBody>
			</Card>
		</>
	);
};

export default AdvancedSettings;
