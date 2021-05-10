/**
 * External dependencies
 */
import React, { useCallback, useContext } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { Button, Card, CardBody, CardDivider } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import interpolateComponents from 'interpolate-components';

/**
 * Internal dependencies
 */
import WizardTaskContext from '../wizard/task/context';
import CollapsibleBody from '../wizard/collapsible-body';
import TaskItem from '../wizard/task-item';
import './add-payment-methods-task.scss';

const useGetCountryName = () => {
	const baseLocation = useSelect(
		( select ) =>
			select( 'wc/admin/settings' ).getSetting(
				'wc_admin',
				'baseLocation'
			),
		[]
	);

	const countries = useSelect(
		( select ) =>
			select( 'wc/admin/settings' ).getSetting( 'wc_admin', 'countries' ),
		[]
	);

	return countries[ baseLocation.country ];
};

const AddPaymentMethodsTask = () => {
	const { setCompleted } = useContext( WizardTaskContext );

	const handleContinueClick = useCallback( () => {
		setCompleted( true, 'setup-complete' );
	}, [ setCompleted ] );

	const countryName = useGetCountryName();

	return (
		<TaskItem
			className="add-payment-methods-task"
			title={ __(
				'Set up additional payment methods',
				'woocommerce-payments'
			) }
			index={ 1 }
		>
			<p className="woocommerce-timeline__parent-list__description-element">
				{ interpolateComponents( {
					mixedString: __(
						"Increase your store's conversion by offering " +
							'your customers preferred and convenient payment methods on checkout. ' +
							'You can manage them later in {{settingsLink /}}.',
						'woocommerce-payments'
					),
					components: {
						settingsLink: (
							<a href="admin.php?page=wc-settings">
								{ __( 'settings', 'woocommerce-payments' ) }
							</a>
						),
					},
				} ) }
			</p>
			<CollapsibleBody>
				<div className="add-payment-methods-task__payment-selector-wrapper woocommerce-timeline__parent-list__description-element">
					<Card>
						<CardBody>
							{ /* eslint-disable-next-line max-len */ }
							<p className="add-payment-methods-task__payment-selector-title woocommerce-timeline__parent-list__description-element">
								{ sprintf(
									__(
										'Popular with customers in %1$s',
										'woocommerce-payments'
									),
									countryName
								) }
							</p>
						</CardBody>
						<CardDivider />
						<CardBody>
							<p className="add-payment-methods-task__payment-selector-title">
								{ __(
									'Additional payment methods',
									'woocommerce-payments'
								) }
							</p>
						</CardBody>
					</Card>
				</div>
				<p className="woocommerce-timeline__parent-list__description-element">
					{ __(
						'Selected payment methods need new currencies to be added to your store.',
						'woocommerce-payments'
					) }
				</p>
				<p>
					<Button onClick={ handleContinueClick } isPrimary>
						{ __( 'Continue', 'woocommerce-payments' ) }
					</Button>
				</p>
			</CollapsibleBody>
		</TaskItem>
	);
};

export default AddPaymentMethodsTask;
