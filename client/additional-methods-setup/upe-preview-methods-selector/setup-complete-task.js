/**
 * External dependencies
 */
import React from 'react';
import { useEffect, useContext } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import { useDispatch } from '@wordpress/data';

/**
 * Internal dependencies
 */
import CollapsibleBody from '../wizard/collapsible-body';
import WizardTaskItem from '../wizard/task-item';
import WizardTaskContext from '../wizard/task/context';

const SetupComplete = () => {
	const { isActive } = useContext( WizardTaskContext );

	const { updateOptions } = useDispatch( 'wc/admin/options' );

	useEffect( () => {
		if ( ! isActive ) {
			return;
		}

		updateOptions( {
			// eslint-disable-next-line camelcase
			wcpay_additional_methods_setup_completed: 'yes',
		} );

		// Set the local `isSetupCompleted` to `yes` so that task appears completed on the list.
		// Please note that marking an item as "completed" is different from "dismissing" it.
		window.wcpayAdditionalMethodsSetup.isSetupCompleted = 'yes';
		window.wcpayAdditionalMethodsSetup.isUpeEnabled = true;
	}, [ isActive, updateOptions ] );

	return (
		<WizardTaskItem
			title={ __( 'Enjoy the new features', 'woocommerce-payments' ) }
			index={ 3 }
		>
			<CollapsibleBody>
				<p className="wcpay-wizard-task__description-element is-muted-color">
					{ __( 'Setup complete!', 'woocommerce-payments' ) }
				</p>
				<div className="setup-complete-task__buttons">
					<Button
						href="admin.php?page=wc-settings&tab=checkout&section=woocommerce_payments"
						isPrimary
					>
						{ __(
							'Go to payments settings',
							'woocommerce-payments'
						) }
					</Button>
					<Button
						href="admin.php?page=wc-settings&tab=wcpay_multi_currency"
						isTertiary
					>
						{ __(
							'Go to multi-currency settings',
							'woocommerce-payments'
						) }
					</Button>
				</div>
			</CollapsibleBody>
		</WizardTaskItem>
	);
};

export default SetupComplete;
