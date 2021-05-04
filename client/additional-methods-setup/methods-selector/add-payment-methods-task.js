/**
 * External dependencies
 */
import React, { useCallback } from 'react';
import { __ } from '@wordpress/i18n';
import classNames from 'classnames';
import { Button } from '@wordpress/components';

/**
 * Internal dependencies
 */
import ChildTaskController from '../setup-tasks/child-provider';
import { useChildTaskContext } from '../setup-tasks/child-context';

import { TaskItem } from './task-list';
import './add-payment-methods-task.scss';

const Body = () => {
	const { isActive, setCompleted } = useChildTaskContext();

	const handleContinueClick = useCallback( () => {
		setCompleted( 'setup-complete' );
	}, [ setCompleted ] );

	return (
		<div
			className={ classNames( 'add-payment-methods-task__body', {
				'is-active': isActive,
			} ) }
		>
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
		</div>
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
				{ __(
					// eslint-disable-next-line max-len
					"Increase your store's conversion by offering your customers preferred and convenient payment methods on checkout. You can manage them later in settings.",
					'woocommerce-payments'
				) }
				<Body />
			</TaskItem>
		</ChildTaskController>
	);
};

export default AddPaymentMethodsTask;
