/**
 * External dependencies
 */
import React from 'react';
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

const AdvancedSettings = () => {
	const [ isSectionExpanded, toggleIsSectionExpanded ] = useToggle( false );
	const firstHeadingElementRef = useRef( null );

	useEffect( () => {
		if ( ! isSectionExpanded ) return;
		if ( ! firstHeadingElementRef.current ) return;

		firstHeadingElementRef.current.focus();
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
						<CardBody size="large">
							<div ref={ firstHeadingElementRef } tabIndex="-1">
								<DebugMode />
								<BlockAppearance />
							</div>
						</CardBody>
					</Card>
				</SettingsSection>
			) }
		</>
	);
};

export default AdvancedSettings;
