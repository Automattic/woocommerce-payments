/** @format **/

/**
 * External dependencies
 */
import React from 'react';

/**
 * Internal dependencies
 */
import './style.scss';

interface LoadableProps {
	isLoading: boolean;
	display?: string;
	placeholder?: JSX.Element | string;
	children?: React.ReactNode;
	/**
	 * When true, children are rendered (but hidden) while loading.
	 * Useful when children need to mount and initialize before being displayed.
	 */
	renderChildrenWhileLoading?: boolean;
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
 * @param {ReactNode} [props.children] Content rendered when data are loaded.
 * @param {boolean} [props.renderChildrenWhileLoading] When true, children are rendered but hidden while loading.
 *
 * @return {ReactNode} Loadable content
 */
const Loadable = ( {
	isLoading,
	display,
	placeholder,
	children,
	renderChildrenWhileLoading = false,
}: LoadableProps ): JSX.Element => {
	if ( isLoading ) {
		return (
			<>
				<span
					className={
						display
							? `is-loadable-placeholder is-${ display }`
							: 'is-loadable-placeholder'
					}
					aria-busy="true"
				>
					{ undefined === placeholder ? children : placeholder }
				</span>
				{ renderChildrenWhileLoading && (
					<div className="is-loadable-placeholder__hidden-content">
						{ children }
					</div>
				) }
			</>
		);
	}

	return <>{ children }</>;
};

/**
 * Helper component for rendering a loadable block which takes several lines in the ui.
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
