/** @format */

/**
 * External dependencies
 */
import React, { useState } from 'react';
import { Card, CardBody } from '@wordpress/components';

/**
 * Internal dependencies
 */
import Wizard from 'additional-methods-setup/wizard/wrapper';
import WizardTaskList from 'additional-methods-setup/wizard/task-list';
import WizardTask from 'additional-methods-setup/wizard/task';
import AddBusinessInfoTask from './tasks/add-business-info-task';
import SetupCompleteTask from './tasks/setup-complete-task';
import './index.scss';

const OnboardingPage = () => {
	const [ selectedData, setSelectedData ] = useState();

	return (
		<Card
			size="large"
			className="wcpay-onboarding-card wcpay-homescreen-card"
		>
			<CardBody>
				<Wizard defaultActiveTask="complete-business-info">
					<WizardTaskList>
						<WizardTask id="complete-business-info">
							<AddBusinessInfoTask onChange={ setSelectedData } />
						</WizardTask>
						<WizardTask id="setup-complete">
							<SetupCompleteTask args={ selectedData } />
						</WizardTask>
					</WizardTaskList>
				</Wizard>
			</CardBody>
		</Card>
	);
};

export default OnboardingPage;
