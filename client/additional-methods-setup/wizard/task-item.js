/**
 * External dependencies
 */
import React, { useContext } from 'react';
import classNames from 'classnames';

/**
 * Internal dependencies
 */
import TaskContext from './task/context';

const TaskItem = ( { children, title, index, className } ) => {
	const { isCompleted, isActive } = useContext( TaskContext );

	return (
		<li
			className={ classNames( 'woocommerce-timeline-group', className, {
				'is-completed': isCompleted,
				'is-active': isActive,
			} ) }
		>
			<ul>
				<li className="woocommerce-timeline-item">
					<div className="woocommerce-timeline-item__top-border" />
					<div className="woocommerce-timeline-item__title">
						<div
							className="woocommerce-timeline-item__headline"
							// tabindex with value `-1` is necessary to programmatically set the focus
							// on an element that is not interactive.
							tabIndex="-1"
						>
							<div className="woocommerce-timeline-item__icon-wrapper">
								<div className="woocommerce-timeline-item__icon-text">
									{ index }
								</div>
							</div>
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

export default TaskItem;
