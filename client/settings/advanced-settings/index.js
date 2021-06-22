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
import useToggle from './use-toggle';
import './style.scss';
import CardBody from '../card-body';

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
					<Card>
						<CardBody>
							<DebugMode />
						</CardBody>
					</Card>
				</SettingsSection>
			) }
		</>
	);
};

export default AdvancedSettings;
