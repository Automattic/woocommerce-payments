/**
 * External dependencies
 */
import React from 'react';
import { useEffect, useCallback, useContext } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import { getHistory, getNewPath } from '@woocommerce/navigation';
import { useDispatch } from '@wordpress/data';

/**
 * Internal dependencies
 */
import CollapsibleBody from '../wizard/collapsible-body';
import TaskItem from '../wizard/task-item';
import WizardTaskContext from '../wizard/task/context';

const SetupComplete = () => {
	const { isActive } = useContext( WizardTaskContext );

	const handleGoHome = useCallback( () => {
		getHistory().push( getNewPath( {}, '/', {} ) );
	}, [] );

	const { updateOptions } = useDispatch( 'wc/admin/options' );

	useEffect( () => {
		if ( ! isActive ) {
			return;
		}

		// extracted to a separate const, just so that eslint doesn't complain about the name not being camelCase.
		const OPTION_NAME = 'wcpay_additional_methods_setup_completed';
		updateOptions( {
			[ OPTION_NAME ]: 'yes',
		} );

		// Set the local `isSetupCompleted` to `yes` so that task appears completed on the list.
		// Please note that marking an item as "completed" is different from "dismissing" it.
		window.wcpayAdditionalMethodsSetup.isSetupCompleted = 'yes';
	}, [ isActive, updateOptions ] );

	return (
		<TaskItem
			className="setup-complete-task"
			title={ __( 'Setup complete', 'woocommerce-payments' ) }
			index={ 2 }
		>
			<CollapsibleBody>
				<p>
					{ __(
						"You're ready to begin accepting payments with the new methods!.",
						'woocommerce-payments'
					) }
				</p>
				<p>
					{ __(
						// eslint-disable-next-line max-len
						'Enter your VAT account information and set up taxe to ensure smooth transactions if you plan to sell to customers in Europe.',
						'woocommerce-payments'
					) }
				</p>
				<p>
					{ __(
						'To manage other payment settings or update your payment information, visit the payment settings.',
						'woocommerce-payments'
					) }
				</p>
				<p>
					<Button onClick={ handleGoHome } isPrimary>
						{ __(
							'Go to WooCommerce Home',
							'woocommerce-payments'
						) }
					</Button>
				</p>
			</CollapsibleBody>
		</TaskItem>
	);
};

export default SetupComplete;
