/**
 * External dependencies
 */
import React from 'react';
import { Card, CardBody } from '@wordpress/components';

/**
 * Internal dependencies
 */
import Wizard from 'wcpay/additional-methods-setup/wizard/wrapper';
import WizardTaskList from 'wcpay/additional-methods-setup/wizard/task-list';
import WizardTask from 'wcpay/additional-methods-setup/wizard/task';
import AddBusinessInfo from './add-business-info-task';
import SetupComplete from './setup-complete-task';

const OnboardingPage = () => {
	return (
		<Card
			size="large"
			className="woocommerce-onboarding-card woocommerce-homescreen-card"
		>
			<CardBody>
				<Wizard defaultActiveTask="complete-business-info">
					<WizardTaskList>
						<WizardTask id="complete-business-info">
							<AddBusinessInfo />
						</WizardTask>
						<WizardTask id="setup-complete">
							<SetupComplete />
						</WizardTask>
					</WizardTaskList>
				</Wizard>
			</CardBody>
		</Card>
	);
};

export default OnboardingPage;
