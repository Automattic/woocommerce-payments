/**
 * External dependencies
 */
import React from 'react';

/**
 * Internal dependencies
 */
import { WordPressComponentsContext } from './context';

const BundledWpComponentsProvider = ( { children } ) => (
	<WordPressComponentsContext.Provider value={ undefined }>
		{ children }
	</WordPressComponentsContext.Provider>
);

export default BundledWpComponentsProvider;
