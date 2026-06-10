/** @format */

/**
 * External dependencies
 */
import React from 'react';
import clsx from 'clsx';
import { VisuallyHidden } from '@wordpress/components';

/**
 * Internal dependencies
 */
import './style.scss';

interface Props {
	/** Rendered inside the 44x44 icon container. */
	icon: JSX.Element;
	title: string;
	description: string;
	/**
	 * Optional right-aligned action (button, link). On small screens the
	 * action reflows to full-width; the icon is hidden instead.
	 */
	action?: React.ReactNode;
	/** Caller-specific modifiers (e.g. urgency tints). */
	className?: string;
	/** `article` when the item is a list landmark; `div` for action-step rows. */
	as?: 'div' | 'article';
	/**
	 * Default `div` preserves "Steps you can take" non-heading semantics;
	 * pass a heading tag when items should be navigable.
	 */
	titleAs?: 'div' | 'h3' | 'h4' | 'h5' | 'h6';
	/** SR-only severity prefix; the icon is aria-hidden. */
	titleSrPrefix?: string;
}

/**
 * Shared row used by "Steps you can take" and the dispute recommendations
 * card. Owns row geometry; callers attach urgency tints, semantic root
 * tag, and heading level via `className`, `as`, and `titleAs`.
 */
const DisputeStepItem: React.FC< Props > = ( {
	icon,
	title,
	description,
	action,
	className,
	as: Tag = 'div',
	titleAs: TitleTag = 'div',
	titleSrPrefix,
} ) => {
	return (
		<Tag className={ clsx( 'dispute-step-item', className ) }>
			<div className="dispute-step-item__icon" aria-hidden="true">
				{ icon }
			</div>
			<div className="dispute-step-item__content">
				<TitleTag className="dispute-step-item__name">
					{ titleSrPrefix && (
						<VisuallyHidden>{ titleSrPrefix + ' ' }</VisuallyHidden>
					) }
					{ title }
				</TitleTag>
				<div className="dispute-step-item__description">
					{ description }
				</div>
			</div>
			{ action && (
				<div className="dispute-step-item__action">{ action }</div>
			) }
		</Tag>
	);
};

export default DisputeStepItem;
