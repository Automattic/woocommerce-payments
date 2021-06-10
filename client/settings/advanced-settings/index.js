/**
 * External dependencies
 */
import React, { useContext } from 'react';
import { Icon, chevronDown, chevronUp } from '@wordpress/icons';
import { __ } from '@wordpress/i18n';
import { Card, Button } from '@wordpress/components';

/**
 * Internal dependencies
 */
import SettingsSection from '../settings-section';
import BlockAppearance from './block-appearance';
import DebugMode from './debug-mode';
import useToggle from './use-toggle';
import './style.scss';
import WCPaySettingsContext from '../wcpay-settings-context';
import CardBody from '../card-body';

const AdvancedSettings = () => {
	const [ isSectionExpanded, toggleIsSectionExpanded ] = useToggle( false );
	const {
		featureFlags: { upe: isUPEEnabled },
	} = useContext( WCPaySettingsContext );

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
							{ isUPEEnabled && <BlockAppearance /> }
						</CardBody>
					</Card>
				</SettingsSection>
			) }
		</>
	);
};

export default AdvancedSettings;
