/**
 * Internal dependencies
 */
import './style.scss';

const Placeholder = ( { isActive, display, content = 'placeholder', children } ) =>
	isActive ? (
		<div className={ display ? `is-placeholder is-${ display }` : 'is-placeholder' }>
			{content}
		</div>
	) : (
		<>{children}</>
	);

export default Placeholder;
