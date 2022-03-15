/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';

import WizardTaskItem from 'wcpay/additional-methods-setup/wizard/task-item';

const SetupComplete = () => {
	return (
		<WizardTaskItem
			index={ 2 }
			title={ __(
				'Connect your account and finish setup',
				'woocommerce-payments'
			) }
		>
			<div className="setup-complete-task__buttons">
				{ /* Todo: make this link go to the onboarding */ }
				<Button href="admin.php?page=wc-admin" isPrimary>
					{ __( 'Connect', 'woocommerce-payments' ) }
				</Button>
			</div>
		</WizardTaskItem>
	);
};

export default SetupComplete;
