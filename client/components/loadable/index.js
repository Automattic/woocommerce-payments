/**
 * Internal dependencies
 */
import './style.scss';

const Loadable = ( { isLoading, display, placeholder = 'placeholder', value, children } ) =>
	isLoading ? (
		<div className={ display ? `is-placeholder is-${ display }` : 'is-placeholder' }>
			{ placeholder }
		</div>
	) : (
		<>{ children || value }</>
	);

export default Loadable;
