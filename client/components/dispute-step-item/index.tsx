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
	/**
	 * Leading icon (typically a `<Icon icon={...} size={24} />` from
	 * `@wordpress/icons`). Rendered inside the 44x44 bordered container.
	 */
	icon: JSX.Element;
	title: string;
	description: string;
	/**
	 * Optional right-aligned action (button, link). Hidden on small screens
	 * by the parent layout when used inline with text.
	 */
	action?: React.ReactNode;
	/**
	 * Extra class names appended to the root. Use this to attach caller-
	 * specific modifiers (urgency tints, original BEM contracts) without
	 * the shared component knowing about them.
	 */
	className?: string;
	/**
	 * Root element tag. Use `article` when the item is an independent unit
	 * within a list (e.g. a catalog entry exposed as a separate landmark);
	 * `div` is the default and the right choice for action-step rows.
	 */
	as?: 'div' | 'article';
	/**
	 * Heading element used for the item title. Default `div` preserves the
	 * existing non-heading semantics in "Steps you can take". Pass `h3`/
	 * `h4`/etc. when the item should be navigable as a heading (e.g.
	 * recommendations nested inside an `h2` accordion title).
	 */
	titleAs?: 'div' | 'h3' | 'h4' | 'h5' | 'h6';
	/**
	 * Screen-reader-only prefix prepended to the title (e.g. "Important:",
	 * "Tip:"). Used to qualify items whose severity is conveyed visually
	 * via icon color/shape but needs a textual cue for SR users.
	 */
	titleSrPrefix?: string;
}

/**
 * Shared row used by the "Steps you can take" accordion and the dispute
 * outcome recommendations card. Owns the row geometry — 44x44 icon
 * container with a 1px gray-200 outline stroke, 16px vertical padding,
 * gray-100 hairline between items, and a mobile collapse. Variant
 * styling (icon color, urgency BEM hooks, semantic root tag) is up to
 * the caller via `className`, `as`, and `titleAs`.
 *
 * Lucy review on PR #11703 (2026-06-01): the recommendations card should
 * literally reuse the Steps you can take row, not just visually match. This
 * is that shared row.
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
