/**
 * External dependencies
 */
import React, { useContext } from 'react';
import classNames from 'classnames';
import { Icon, check } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import WizardTaskContext from './task/context';

const TaskItem = ( { children, title, index, className } ) => {
	const { isCompleted, isActive } = useContext( WizardTaskContext );

	return (
		<li
			className={ classNames( 'wcpay-wizard__task', className, {
				'is-completed': isCompleted,
				'is-active': isActive,
			} ) }
		>
			<div className="wcpay-wizard__task__top-border" />
			<div className="wcpay-wizard__task__title">
				<div
					className="wcpay-wizard__task__headline"
					// tabindex with value `-1` is necessary to programmatically set the focus
					// on an element that is not interactive.
					tabIndex="-1"
				>
					<div className="wcpay-wizard__task__icon-wrapper">
						<div className="wcpay-wizard__task__icon-text">
							{ index }
						</div>
						<Icon
							icon={ check }
							className="wcpay-wizard__task__icon-checkmark"
						/>
					</div>
					<span>{ title }</span>
				</div>
			</div>
			<div className="wcpay-wizard__task__body">{ children }</div>
		</li>
	);
};

export default TaskItem;
