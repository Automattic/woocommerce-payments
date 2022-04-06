/**
 * External dependencies
 */
import React, { useContext } from 'react';
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import { addQueryArgs } from '@wordpress/url';

import WizardTaskItem from 'additional-methods-setup/wizard/task-item';
import WizardTaskContext from 'additional-methods-setup/wizard/task/context';
import { OnboardingState } from 'onboarding/types';

interface TaskProps {
	args: OnboardingState;
}

const SetupCompleteTask = ( { args }: TaskProps ): JSX.Element => {
	const { connectUrl } = wcpaySettings;
	const { setCompleted } = useContext( WizardTaskContext );

	const url = addQueryArgs( connectUrl, { prefill: args } );

	return (
		<WizardTaskItem
			index={ 2 }
			title={ __(
				'Connect your account and finish setup',
				'woocommerce-payments'
			) }
			className={ null }
		>
			<div className="wcpay-onboarding-setup-complete-task__buttons">
				<Button
					href={ url }
					isPrimary
					onClick={ () => setCompleted( true, 'setup-complete' ) }
				>
					{ __( 'Connect', 'woocommerce-payments' ) }
				</Button>
			</div>
		</WizardTaskItem>
	);
};

export default SetupCompleteTask;
