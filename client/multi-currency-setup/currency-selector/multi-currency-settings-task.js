/**
 * External dependencies
 */
import React, { useCallback, useContext, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { Button, Card, CardBody, CheckboxControl } from '@wordpress/components';
import interpolateComponents from 'interpolate-components';
import { Link } from '@woocommerce/components';
/**
 * Internal dependencies
 */
import WizardTaskContext from '../wizard/task/context';
import CollapsibleBody from '../wizard/collapsible-body';
import WizardTaskItem from '../wizard/task-item';
import './multi-currency-settings-task.scss';
import { submitSettingsUpdate } from 'wcpay/data/multi-currency/actions';

const MultiCurrencySettingsTask = () => {
	const initialIsAutomaticSwitchEnabled = true;
	const initialWillAddCurrencySelectorToCartWidget = true;
	const [ status, setStatus ] = useState( 'resolved' );
	const [
		isAutomaticSwitchEnabledValue,
		setIsAutomaticSwitchEnabledValue,
	] = useState( initialIsAutomaticSwitchEnabled );
	const [
		willAddCurrencySelectorToCartWidgetValue,
		setWillAddCurrencySelectorToCartWidgetValue,
	] = useState( initialWillAddCurrencySelectorToCartWidget );

	const { setCompleted } = useContext( WizardTaskContext );

	const handleContinueClick = useCallback( () => {
		setStatus( 'pending' );
		submitSettingsUpdate(
			isAutomaticSwitchEnabledValue,
			willAddCurrencySelectorToCartWidgetValue
		);
		setStatus( 'resolved' );
		setCompleted( true, 'setup-complete' );
	}, [
		setCompleted,
		isAutomaticSwitchEnabledValue,
		willAddCurrencySelectorToCartWidgetValue,
		setStatus,
	] );

	return (
		<WizardTaskItem
			title={ interpolateComponents( {
				mixedString: __(
					'{{wrapper}}Review store settings{{/wrapper}}',
					'woocommerce-payments'
				),
				components: {
					wrapper: <span />,
				},
			} ) }
			visibleDescription={ __(
				'These settings can be changed any time by visiting the multi-currency settings',
				'woocommerce-payments'
			) }
			index={ 2 }
		>
			<CollapsibleBody className="multi-currency-settings-task__body">
				<p className="wcpay-wizard-task__description-element is-muted-color">
					{ __(
						'These settings can be changed any time by visiting the multi-currency settings',
						'woocommerce-payments'
					) }
				</p>
				<Card className="multi-currency-settings-task__wrapper">
					<CardBody>
						<CheckboxControl
							checked={ isAutomaticSwitchEnabledValue }
							onChange={ setIsAutomaticSwitchEnabledValue }
							label={ __(
								'Automatically switch customers to their local currency if it has been enabled',
								'woocommerce-payments'
							) }
						/>
						<div className="multi-currency-settings-task__description">
							Customers will be notified via store alert banner.
						</div>
						<CheckboxControl
							checked={ willAddCurrencySelectorToCartWidgetValue }
							onChange={
								setWillAddCurrencySelectorToCartWidgetValue
							}
							label={ __(
								'Add a currency switcher to the cart widget',
								'woocommerce-payments'
							) }
						/>
					</CardBody>
				</Card>
				<Button
					isBusy={ 'pending' === status }
					disabled={ 'pending' === status }
					onClick={ handleContinueClick }
					isPrimary
				>
					{ __( 'Continue', 'woocommerce-payments' ) }
				</Button>
				<Link>Preview</Link>
			</CollapsibleBody>
		</WizardTaskItem>
	);
};

export default MultiCurrencySettingsTask;
