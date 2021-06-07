/**
 * External dependencies
 */
import React, { useContext } from 'react';
import { useEffect, useRef } from '@wordpress/element';
import { Icon, chevronDown, chevronUp } from '@wordpress/icons';
import { __ } from '@wordpress/i18n';
import { Card, CardBody, Button } from '@wordpress/components';

/**
 * Internal dependencies
 */
import SettingsSection from '../settings-section';
import BlockAppearance from './block-appearance';
import DebugMode from './debug-mode';
import useToggle from './use-toggle';
import './style.scss';
import WCPaySettingsContext from '../wcpay-settings-context';

const AdvancedSettings = () => {
	const [ isSectionExpanded, toggleIsSectionExpanded ] = useToggle( false );
	const advancedSectionContainerRef = useRef( null );
	const {
		featureFlags: { upe: isUPEEnabled },
	} = useContext( WCPaySettingsContext );

	useEffect( () => {
		if ( ! isSectionExpanded ) return;
		if ( ! advancedSectionContainerRef.current ) return;

		advancedSectionContainerRef.current.focus();
	}, [ isSectionExpanded ] );

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
							<div
								ref={ advancedSectionContainerRef }
								tabIndex="-1"
							>
								<DebugMode />
								{ isUPEEnabled && <BlockAppearance /> }
							</div>
						</CardBody>
					</Card>
				</SettingsSection>
			) }
		</>
	);
};

export default AdvancedSettings;
