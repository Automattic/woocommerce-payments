/**
 * External dependencies
 */
import React from 'react';
import { useEffect, useContext } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import { useDispatch } from '@wordpress/data';

/**
 * Internal dependencies
 */
import CollapsibleBody from '../../../additional-methods-setup/wizard/collapsible-body';
import WizardTaskItem from '../../wizard/task-item';
import WizardTaskContext from '../../../additional-methods-setup/wizard/task/context';

import './index.scss';

import { useDefaultCurrency } from 'wcpay/data';

const SetupComplete = () => {
	const { isActive } = useContext( WizardTaskContext );
	const defaultCurrency = useDefaultCurrency();
	const { updateOptions } = useDispatch( 'wc/admin/options' );

	useEffect( () => {
		if ( ! isActive ) {
			return;
		}

		updateOptions( {
			// eslint-disable-next-line camelcase
			wcpay_multi_currency_setup_completed: 'yes',
		} );

		// Set the local `isSetupCompleted` to `yes` so that task appears completed on the list.
		// Please note that marking an item as "completed" is different from "dismissing" it.
		window.wcpaySettings.multiCurrencySetup.isSetupCompleted = 'yes';
	}, [ isActive, updateOptions ] );

	return (
		<WizardTaskItem
			title={ __( 'Setup complete', 'woocommerce-payments' ) }
			index={ 3 }
		>
			<CollapsibleBody>
				<p className="wcpay-wizard-task__description-element is-muted-color">
					{ __(
						"You're ready to begin accepting payments using foreign currencies!",
						'woocommerce-payments'
					) }
				</p>
				<p className="wcpay-wizard-task__description-element is-muted-color">
					{ sprintf(
						__(
							'Your product prices are automatically converted from your default currency (%s) based on the currency ' +
								'exchange rate and formatting rules for each currency.',
							'woocommerce-payments'
						),
						defaultCurrency && defaultCurrency.name
					) }
				</p>
				<p className="wcpay-wizard-task__description-element is-muted-color">
					{ __(
						'To enter your own exchange rates or update the formatting rules for each currency, visit the Multi-Currency ' +
							'settings.',
						'woocommerce-payments'
					) }
				</p>
				<br />
				<div className="setup-complete-task__buttons">
					<Button href="admin.php?page=wc-admin" isPrimary>
						{ __( 'Back to home', 'woocommerce-payments' ) }
					</Button>
					<Button
						href="admin.php?page=wc-settings&tab=wcpay_multi_currency"
						isTertiary
					>
						{ __(
							'View Multi-Currency settings',
							'woocommerce-payments'
						) }
					</Button>
				</div>
			</CollapsibleBody>
		</WizardTaskItem>
	);
};

export default SetupComplete;
