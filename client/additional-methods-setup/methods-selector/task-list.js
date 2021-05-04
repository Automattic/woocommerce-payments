/**
 * External dependencies
 */
import React from 'react';
import classNames from 'classnames';

/**
 * Internal dependencies
 */
import { useChildTaskContext } from '../setup-tasks/child-context';
import './task-list.scss';

export const TaskList = ( { children } ) => (
	<div className="woocommerce-timeline woocommerce-timeline__task-list">
		<ul>{ children }</ul>
	</div>
);

export const TaskItem = ( { children, title, index, className } ) => {
	const { isCompleted, isActive } = useChildTaskContext();

	return (
		<li
			className={ classNames( 'woocommerce-timeline-group', className, {
				'is-completed': isCompleted,
				'is-active': isActive,
			} ) }
		>
			<ul>
				<li className="woocommerce-timeline-item">
					<div className="woocommerce-timeline-item__top-border"></div>
					<div className="woocommerce-timeline-item__title">
						<div className="woocommerce-timeline-item__headline">
							{ index }
							<span>{ title }</span>
						</div>
					</div>
					<div className="woocommerce-timeline-item__body">
						{ children }
					</div>
				</li>
			</ul>
		</li>
	);
};
