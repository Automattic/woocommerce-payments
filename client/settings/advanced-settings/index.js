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

const AdvancedSettings = () => {
	const [ isSectionExpanded, toggleIsSectionExpanded ] = useToggle( false );

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
								<WCPaySubscriptionsToggle />
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
