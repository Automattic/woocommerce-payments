/**
 * Internal dependencies
 */
import './style.scss';

const Loadable = ( { isLoading, display, placeholder = 'placeholder', children } ) =>
	isLoading ? (
		<div className={ display ? `is-placeholder is-${ display }` : 'is-placeholder' }>
			{ placeholder }
		</div>
	) : (
		<>{ children }</>
	);

export default Loadable;
