/** @format */

/**
 * External dependencies
 */
import { createContext } from 'react';

const MultiCurrencySettingsContext = createContext( {
	isDirty: false,
	setIsDirty: () => null,
} );

export default MultiCurrencySettingsContext;
