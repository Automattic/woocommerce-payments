/** @format **/

/**
 * External dependencies
 */
import * as React from 'react';

/**
 * Internal dependencies
 */
import './style.scss';

interface LoadableProps {
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
 * @param {Object} props Component props.
 * @param {boolean} props.isLoading Flag used to display placeholder or content.
 * @param {string} props.display Defines how the placeholder is displayed: inline-block (default), inline or block.
 * @param {ReactNode} [props.placeholder] Custom placeholder content.
 * @param {ReactNode} [props.value] Content rendered when data are loaded. Has lower priority than `children`.
 * @param {ReactNode} [props.children] Content rendered when data are loaded. Has higher priority than `value`.
 *
 * @return {ReactNode} Loadable content
 */
const Loadable = ( {
	isLoading,
	display,
	placeholder,
	value,
	children,
}: LoadableProps ): JSX.Element =>
	isLoading ? (
		<span
			className={
				display
					? `is-loadable-placeholder is-${ display }`
					: 'is-loadable-placeholder'
			}
			aria-busy="true"
		>
			{ undefined === placeholder ? children || value : placeholder }
		</span>
	) : (
		<>{ children || value }</>
	);

/**
 * Helper component for rendering loadable block which takes several lines in the ui.
 *
 * @param {Object} props Component props.
 * @param {number} props.numLines Vertical size of the component in lines.
 *
 * @return {ReactNode} Loadable content
 */
export const LoadableBlock = ( props: LoadableBlockProps ): JSX.Element => {
	const placeholder = (
		<p style={ { lineHeight: props.numLines } }>Block placeholder</p>
	);
	return (
		<Loadable { ...props } placeholder={ placeholder } display="block" />
	);
};

export default Loadable;
