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
import './task-item.scss';

interface WizardTaskItemProps {
	children: React.ReactNode;
	title: string;
	index: number;
	className?: string;
	visibleDescription?: string;
}

const WizardTaskItem: React.FC< WizardTaskItemProps > = ( {
	children,
	title,
	index,
	className,
	visibleDescription,
} ) => {
	const { isCompleted, isActive } = useContext( WizardTaskContext );

	return (
		<li
			className={ classNames( 'wcpay-wizard-task', className, {
				'is-completed': isCompleted,
				'is-active': isActive,
			} ) }
		>
			<div className="wcpay-wizard-task__top-border" />
			<div
				className="wcpay-wizard-task__headline"
				// tabindex with value `-1` is necessary to programmatically set the focus
				// on an element that is not interactive.
				tabIndex={ -1 }
			>
				<div className="wcpay-wizard-task__icon-wrapper">
					<div className="wcpay-wizard-task__icon-text">
						{ index }
					</div>
					<Icon
						icon={ check }
						className="wcpay-wizard-task__icon-checkmark"
					/>
				</div>
				<span className="wcpay-wizard-task__title">{ title }</span>
			</div>
			{ visibleDescription && ! isActive && (
				<span
					className={ classNames(
						'wcpay-wizard-task__visible-description-element',
						'is-muted-color'
					) }
				>
					{ visibleDescription }
				</span>
			) }
			<div className="wcpay-wizard-task__body">{ children }</div>
		</li>
	);
};

export default WizardTaskItem;
