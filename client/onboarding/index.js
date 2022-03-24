/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { Card, CardBody } from '@wordpress/components';

/**
 * Internal dependencies
 */
import Wizard from 'additional-methods-setup/wizard/wrapper';
import WizardTaskList from 'additional-methods-setup/wizard/task-list';
import WizardTask from 'additional-methods-setup/wizard/task';
import AddBusinessInfo from './tasks/add-business-info-task';
import SetupComplete from './tasks/setup-complete-task';

import './index.scss';

const OnboardingPage = () => {
	return (
		<Card
			size="large"
			className="wcpay-onboarding-card wcpay-homescreen-card"
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
