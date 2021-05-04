/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';

/**
 * Internal dependencies
 */
import CollapsibleBody from './collapsible-body';
import ChildTaskController from '../setup-tasks/child-provider';

import { TaskItem } from './task-list';

const Body = () => {
	return (
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
				<Button isPrimary>
					{ __( 'Go to WooCommerce Home', 'woocommerce-payments' ) }
				</Button>
			</p>
		</CollapsibleBody>
	);
};

const SetupComplete = () => {
	return (
		<ChildTaskController id="setup-complete">
			<TaskItem
				className="setup-complete-task"
				title={ __( 'Setup complete', 'woocommerce-payments' ) }
				index={ 2 }
			>
				<Body />
			</TaskItem>
		</ChildTaskController>
	);
};

export default SetupComplete;
