/**
 * External dependencies
 */
import React, { useState } from 'react';
import { addQueryArgs } from '@wordpress/url';
import { Button, CardDivider } from '@wordpress/components';

/**
 * Internal dependencies
 */
import strings from './strings';
import './styles.scss';
import ResetAccountModal from 'wcpay/overview/modal/reset-account';
import { isInTestModeOnboarding } from 'wcpay/utils';

const handleReset = () => {
	window.location.href = addQueryArgs( wcpaySettings.connectUrl, {
		'wcpay-reset-account': 'true',
		source: 'wcpay-reset-account', // Overwrite any existing source because we are starting over.
	} );
};

export const AccountTools = () => {
	const [ modalVisible, setModalVisible ] = useState( false );

	// Only render when in test/sandbox mode onboarding.
	if ( ! isInTestModeOnboarding() ) {
		return null;
	}

	return (
		<>
			<div className="account-tools">
				<CardDivider />
				<h4>{ strings.title }</h4>
				<p>{ strings.description }</p>
				{ /* Use wrapping div to keep buttons grouped together. */ }
				<div className="account-tools__actions">
					<Button
						variant={ 'secondary' }
						onClick={ () => setModalVisible( true ) }
						__next40pxDefaultSize
					>
						{ strings.reset }
					</Button>
				</div>
			</div>

			<ResetAccountModal
				isVisible={ modalVisible }
				onDismiss={ () => setModalVisible( false ) }
				onSubmit={ handleReset }
			/>
		</>
	);
};
