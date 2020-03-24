/**
 * Internal dependencies
 */
import './style.scss';

const Loadable = ( { isLoading, display, placeholder = 'placeholder', value, children } ) =>
	isLoading ? (
		<span className={ display ? `is-placeholder is-${ display }` : 'is-placeholder' }>
			{ placeholder }
		</span>
	) : (
		<>{ children || value }</>
	);

export default Loadable;
