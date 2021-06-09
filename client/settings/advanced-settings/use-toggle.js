/**
 * External dependencies
 */
import { useCallback, useState } from '@wordpress/element';

const useToggle = ( initialValue = false ) => {
	const [ value, setValue ] = useState( initialValue );
	const toggleValue = useCallback(
		() => setValue( ( oldValue ) => ! oldValue ),
		[ setValue ]
	);

	return [ value, toggleValue ];
};

export default useToggle;
