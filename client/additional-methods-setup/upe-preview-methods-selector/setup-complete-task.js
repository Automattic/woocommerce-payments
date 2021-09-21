/**
 * External dependencies
 */
import React from 'react';
import { useEffect, useContext } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import { useDispatch } from '@wordpress/data';

/**
 * Internal dependencies
 */
import CollapsibleBody from '../wizard/collapsible-body';
import WizardTaskItem from '../wizard/task-item';
import WizardTaskContext from '../wizard/task/context';
import WCPaySettingsContext from '../../settings/wcpay-settings-context';
import { useEnabledPaymentMethodIds } from '../../data';
import WizardContext from '../wizard/wrapper/context';
import PaymentMethodIcon from '../../settings/payment-method-icon';
import './setup-complete-task.scss';

const SetupCompleteMessaging = () => {
	const [ enabledPaymentMethods ] = useEnabledPaymentMethodIds();
	const enabledMethodsCount = enabledPaymentMethods.length;

	const { completedTasks } = useContext( WizardContext );
	const enableUpePreviewPayload = completedTasks[ 'add-payment-methods' ];

	if ( ! enableUpePreviewPayload ) {
		return null;
	}

	// we need to check that the type of `enableUpePreviewPayload` is an object - it can also just be `true` or `undefined`
	let addedPaymentMethodsCount = 0;
	if (
		'object' === typeof enableUpePreviewPayload &&
		enableUpePreviewPayload.initialMethods
	) {
		const { initialMethods } = enableUpePreviewPayload;
		addedPaymentMethodsCount = enabledMethodsCount - initialMethods.length;
	}

	// can't just check for "0", some methods could have been disabled
	if ( 0 >= addedPaymentMethodsCount ) {
		return __( 'Setup complete!', 'woocommerce-payments' );
	}

	return sprintf(
		_n(
			'Setup complete! One new payment method is now live on your store!',
			'Setup complete! %s new payment methods are now live on your store!',
			addedPaymentMethodsCount,
			'woocommerce-payments'
		),
		addedPaymentMethodsCount
	);
};

const EnabledMethodsList = () => {
	const [ enabledPaymentMethods ] = useEnabledPaymentMethodIds();

	return (
		<ul className="wcpay-wizard-task__description-element setup-complete-task__enabled-methods-list">
			{ enabledPaymentMethods.map( ( methodId ) => (
				<li key={ methodId }>
					<PaymentMethodIcon name={ methodId } showName={ false } />
				</li>
			) ) }
		</ul>
	);
};

const SetupComplete = () => {
	const { isActive } = useContext( WizardTaskContext );
	const {
		featureFlags: { multiCurrency },
	} = useContext( WCPaySettingsContext );
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
		window.wcpaySettings.additionalMethodsSetup.isSetupCompleted = 'yes';
		window.wcpaySettings.additionalMethodsSetup.isUpeEnabled = true;
	}, [ isActive, updateOptions ] );

	return (
		<WizardTaskItem
			title={ __( 'Enjoy the new features', 'woocommerce-payments' ) }
			index={ 3 }
		>
			<CollapsibleBody>
				<p className="wcpay-wizard-task__description-element is-muted-color">
					<SetupCompleteMessaging />
				</p>
				<EnabledMethodsList />
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
					{ multiCurrency && (
						<Button
							href="admin.php?page=wc-settings&tab=wcpay_multi_currency"
							isTertiary
						>
							{ __(
								'Go to multi-currency settings',
								'woocommerce-payments'
							) }
						</Button>
					) }
				</div>
			</CollapsibleBody>
		</WizardTaskItem>
	);
};

export default SetupComplete;
