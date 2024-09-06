/** @format */

/**
 * External dependencies
 */
import { createContext } from 'react';

const MultiCurrencySettingsContext = createContext( {
	hasChanges: false,
	setHasChanges: () => null,
} );

export default MultiCurrencySettingsContext;
