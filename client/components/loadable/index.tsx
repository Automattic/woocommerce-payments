/** @format **/

/**
 * External dependencies
 */
import React from 'react';

/**
 * Internal dependencies
 */
import './style.scss';

interface LoadableProps
	extends Omit<
		React.HTMLAttributes< HTMLSpanElement >,
		'className' | 'style' | 'children'
	> {
	isLoading: boolean;
	display?: string;
	placeholder?: JSX.Element | string;
	value?: string;
	children?: any[] | JSX.Element;
}

interface LoadableBlockProps extends LoadableProps {
	numLines: number;
}

/**
 * Renders placeholder while data are being loaded.
 *
 * Any extra span-level props (`aria-*`, `id`, `role`, `data-*`, etc.) are
 * forwarded to the placeholder span. `className` and `style` are intentionally
 * omitted because the component manages those internally to render the gray
 * shimmer. Use `aria-hidden` when the placeholder text is shape-only filler
 * (e.g., "Change payout schedule" used as a width approximator) and would
 * mislead screen readers if announced as real content.
 *
 * @param {Object}    props               Component props.
 * @param {boolean}   props.isLoading     Flag used to display placeholder or content.
 * @param {string}    props.display       Defines how the placeholder is displayed: inline-block (default), inline or block.
 * @param {ReactNode} [props.placeholder] Custom placeholder content.
 * @param {ReactNode} [props.value]       Content rendered when data are loaded. Has lower priority than `children`.
 * @param {ReactNode} [props.children]    Content rendered when data are loaded. Has higher priority than `value`.
 *
 * @return {ReactNode} Loadable content
 */
const Loadable = ( {
	isLoading,
	display,
	placeholder,
	value,
	children,
	...restProps
}: LoadableProps ): JSX.Element =>
	isLoading ? (
		<span
			className={
				display
					? `is-loadable-placeholder is-${ display }`
					: 'is-loadable-placeholder'
			}
			aria-busy="true"
			{ ...restProps }
		>
			{ undefined === placeholder ? children || value : placeholder }
		</span>
	) : (
		<>{ children || value }</>
	);

/**
 * Helper component for rendering loadable block which takes several lines in the ui.
 *
 * Always sets `aria-hidden` because the inner placeholder text ("Block placeholder")
 * is hardcoded filler and would only confuse screen readers if announced.
 *
 * @param {Object} props          Component props.
 * @param {number} props.numLines Vertical size of the component in lines.
 *
 * @return {ReactNode} Loadable content
 */
export const LoadableBlock = ( {
	numLines,
	...rest
}: LoadableBlockProps ): JSX.Element => {
	const placeholder = (
		<p style={ { lineHeight: numLines } }>Block placeholder</p>
	);
	return (
		<Loadable
			{ ...rest }
			placeholder={ placeholder }
			display="block"
			aria-hidden
		/>
	);
};

export default Loadable;
