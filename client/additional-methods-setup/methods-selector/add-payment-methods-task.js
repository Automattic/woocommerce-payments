/**
 * External dependencies
 */
import React, { useCallback } from 'react';
import { __ } from '@wordpress/i18n';
import { Button, Card, CardBody, CardDivider } from '@wordpress/components';

/**
 * Internal dependencies
 */
import ChildTaskController from '../setup-tasks/child-provider';
import { useChildTaskContext } from '../setup-tasks/child-context';
import CollapsibleBody from './collapsible-body';
import { TaskItem } from './task-list';
import './add-payment-methods-task.scss';

const TaskControls = () => {
	const { setCompleted } = useChildTaskContext();

	const handleContinueClick = useCallback( () => {
		setCompleted( 'setup-complete' );
	}, [ setCompleted ] );

	return (
		<CollapsibleBody>
			<div className="add-payment-methods-task__payment-selector-wrapper woocommerce-timeline__task-list__description-element">
				<Card>
					<CardBody>
						{ /* eslint-disable-next-line max-len */ }
						<p className="add-payment-methods-task__payment-selector-title woocommerce-timeline__task-list__description-element">
							Popular with customers in Germany
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
			<p className="woocommerce-timeline__task-list__description-element">
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
	);
};

const AddPaymentMethodsTask = () => {
	return (
		<ChildTaskController id="add-payment-methods">
			<TaskItem
				className="add-payment-methods-task"
				title={ __(
					'Set up additional payment methods',
					'woocommerce-payments'
				) }
				index={ 1 }
			>
				<p className="woocommerce-timeline__task-list__description-element">
					{ __(
						// eslint-disable-next-line max-len
						"Increase your store's conversion by offering your customers preferred and convenient payment methods on checkout. You can manage them later in settings.",
						'woocommerce-payments'
					) }
				</p>
				<TaskControls />
			</TaskItem>
		</ChildTaskController>
	);
};

export default AddPaymentMethodsTask;
