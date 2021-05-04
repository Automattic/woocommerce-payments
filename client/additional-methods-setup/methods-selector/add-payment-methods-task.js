/**
 * External dependencies
 */
import React, { useCallback } from 'react';
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';

/**
 * Internal dependencies
 */
import ChildTaskController from '../setup-tasks/child-provider';
import { useChildTaskContext } from '../setup-tasks/child-context';
import CollapsibleBody from './collapsible-body';
import { TaskItem } from './task-list';

const TaskControls = () => {
	const { setCompleted } = useChildTaskContext();

	const handleContinueClick = useCallback( () => {
		setCompleted( 'setup-complete' );
	}, [ setCompleted ] );

	return (
		<CollapsibleBody>
			<p>
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
				<p>
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
