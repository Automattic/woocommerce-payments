/**
 * Internal dependencies
 */
import './style.scss';

/**
 * Renders placeholder while data are being loaded.
 *
 *
 * @param {Object} props Component props.
 * @param {boolean} props.isLoading Flag used to display placeholder or content.
 * @param {string} props.display Defines how the placeholder is displayed: inline-block (default), inline or block.
 * @param {ReactNode} props.placeholder Custom placeholder content.
 * @param {ReactNode} props.value Content rendered when data are loaded. Has lower priority then `children`.
 * @param {ReactNode} props.children Content rendered when data are loaded. Has higher priority then `value`.
 *
 * @returns {ReactNode} Loadable content
 */
const Loadable = ( { isLoading, display, placeholder, value, children } ) =>
	isLoading ? (
		<span className={ display ? `is-placeholder is-${ display }` : 'is-placeholder' }>
			{ undefined === placeholder ? children || value : placeholder }
		</span>
	) : (
		<>{ children || value }</>
	);

export const LoadableBlock = ( { lines = 1, ...loadableProps } ) => {
	const placeholder = <p style={ { lineHeight: lines } }>Block placeholder</p>;
	return <Loadable { ...loadableProps } placeholder={ placeholder } display="block" />;
};

export default Loadable;
