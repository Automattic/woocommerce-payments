/** @format */
/**
 * External dependencies
 */
import { Button, Card, CardBody, SelectControl } from '@wordpress/components';
import React, { useState } from 'react';

/**
 * Internal dependencies
 */
// import strings from './strings';
import Wizard from '../additional-methods-setup/wizard/wrapper';
import WizardTask from '../additional-methods-setup/wizard/task';
import WizardTaskList from '../additional-methods-setup/wizard/task-list';
import WizardTaskItem from 'wcpay/additional-methods-setup/wizard/task-item';
import { __ } from '@wordpress/i18n/build-types';

const OnboardingPage = () => {
	const [ country, businessType, setCountry, setBusinessType ] = useState();

	return (
		<Card
			size="large"
			className="woocommerce-onboarding-card woocommerce-homescreen-card"
		>
			<CardBody>
				<Wizard defaultActiveTask="complete-business-info">
					<WizardTaskList>
						<WizardTask id="complete-business-info">
							<WizardTaskItem
								className="complete-business-info-task"
								index={ 1 }
								title={ __(
									'Tell us more about your business',
									'woocommerce-payments'
								) }
								visibleDescription={ __(
									"Preview the details we'll require to verify your business and enable deposits.",
									'woocommerce-payments'
								) }
							>
								<SelectControl
									label={ __(
										'Country',
										'woocommerce-payments'
									) }
									value={ country }
									onChange={ ( value ) =>
										setCountry( value )
									}
									options={ [
										{ label: 'United States', value: 'US' },
										{
											label: 'United Kingdom',
											value: 'UK',
										},
									] }
								/>

								<p>
									{ __(
										'The primary country where your business operates',
										'woocommerce-payments'
									) }
								</p>

								<SelectControl
									label={ __(
										'Business type',
										'woocommerce-payments'
									) }
									value={ businessType }
									onChange={ ( value ) =>
										setBusinessType( value )
									}
									options={ [
										{
											label: 'Individual',
											value: 'individual',
										},
										{ label: 'Company', value: 'company' },
										{
											label: 'Non-profit Organisation',
											value: 'nonprofit_organisation',
										},
									] }
								/>
							</WizardTaskItem>
						</WizardTask>
						<WizardTask id="setup-complete">
							<WizardTaskItem
								index={ 2 }
								title={ __(
									'Connect your account and finish setup',
									'woocommerce-payments'
								) }
							>
								<div className="setup-complete-task__buttons">
									{ /* Todo: make this link go to the onboarding */ }
									<Button
										href="admin.php?page=wc-admin"
										isPrimary
									>
										{ __(
											'Connect',
											'woocommerce-payments'
										) }
									</Button>
								</div>
							</WizardTaskItem>
						</WizardTask>
					</WizardTaskList>
				</Wizard>
			</CardBody>
		</Card>
	);
};

export default OnboardingPage;
