/**
 * External dependencies
 */
import React, { useLayoutEffect, useRef } from 'react';
import classNames from 'classnames';

/**
 * Internal dependencies
 */
import { useChildTaskContext } from '../setup-tasks/child-context';
import { useSetupTasksControllerContext } from '../setup-tasks/setup-context';
import './task-list.scss';

export const TaskList = ( { children } ) => {
	const isFirstMount = useRef( true );
	const wrapperRef = useRef( null );
	const { activeTask } = useSetupTasksControllerContext();

	useLayoutEffect( () => {
		// set the focus on the next active heading.
		// but need to set the focus only after the first mount, only when the active task changes.
		if ( true === isFirstMount.current ) {
			isFirstMount.current = false;
			return;
		}

		if ( ! wrapperRef.current ) {
			return;
		}

		const nextActiveTitle = wrapperRef.current.querySelector(
			'.woocommerce-timeline-group.is-active .woocommerce-timeline-item__headline'
		);
		if ( ! nextActiveTitle ) {
			return;
		}

		nextActiveTitle.focus();
	}, [ activeTask ] );

	return (
		<div
			className="woocommerce-timeline woocommerce-timeline__task-list"
			ref={ wrapperRef }
		>
			<ul>{ children }</ul>
		</div>
	);
};

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
