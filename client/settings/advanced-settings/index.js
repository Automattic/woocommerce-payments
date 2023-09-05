/**
 * External dependencies
 */
import React from 'react';
import { Icon, chevronDown, chevronUp } from '@wordpress/icons';
import { __ } from '@wordpress/i18n';
import { Card, Button } from '@wordpress/components';

/**
 * Internal dependencies
 */
import SettingsSection from '../settings-section';
import DebugMode from './debug-mode';
import MultiCurrencyToggle from './multi-currency-toggle';
import WCPaySubscriptionsToggle from './wcpay-subscriptions-toggle';
import useToggle from './use-toggle';
import './style.scss';
import CardBody from '../card-body';
import ErrorBoundary from '../../components/error-boundary';
import ClientSecretEncryptionToggle from './client-secret-encryption-toggle';
import StripeBillingSection from './stripe-billing-section';

const AdvancedSettings = () => {
	const [ isSectionExpanded, toggleIsSectionExpanded ] = useToggle( true );

	return (
		<>
			<SettingsSection>
				<Button onClick={ toggleIsSectionExpanded } isTertiary>
					{ __( 'Advanced settings', 'wordpress-components' ) }
					<Icon
						icon={ isSectionExpanded ? chevronUp : chevronDown }
					/>
				</Button>
			</SettingsSection>
			{ isSectionExpanded && (
				<SettingsSection>
					<ErrorBoundary>
						<Card>
							<CardBody>
								<MultiCurrencyToggle />
								{ wcpaySettings.isClientEncryptionEligible && (
									<ClientSecretEncryptionToggle />
								) }
								{ wcpaySettings.isSubscriptionsActive &&
								wcpaySettings.isStripeBillingEligible ? (
									<StripeBillingSection />
								) : (
									<WCPaySubscriptionsToggle />
								) }
								<DebugMode />
							</CardBody>
						</Card>
					</ErrorBoundary>
				</SettingsSection>
			) }
		</>
	);
};

export default AdvancedSettings;
