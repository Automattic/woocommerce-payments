/**
 * External dependencies
 */
import { useEffect, useRef } from '@wordpress/element';
import { CheckboxControl } from '@wordpress/components';

/**
 * Internal dependencies
 */
import useToggle from './use-toggle';

const CheckboxToggle = ( { label, defaultIsChecked, children } ) => {
	const [ isExpanded, toggleIsExpanded ] = useToggle( defaultIsChecked );
	const wrapperRef = useRef( null );

	useEffect( () => {
		if ( ! isExpanded ) return;
		if ( ! wrapperRef.current ) return;

		const input = wrapperRef.current.querySelector( 'input, textarea' );
		if ( ! input ) return;

		input.focus();
	}, [ isExpanded ] );

	return (
		<>
			<CheckboxControl
				label={ label }
				checked={ isExpanded }
				onChange={ toggleIsExpanded }
			/>
			{ isExpanded && <div ref={ wrapperRef }>{ children }</div> }
		</>
	);
};

export default CheckboxToggle;
