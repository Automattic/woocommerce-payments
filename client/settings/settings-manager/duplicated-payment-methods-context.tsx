/**
 * External dependencies
 */
import { createContext } from 'react';

const DuplicatedPaymentMethodsContext = createContext( {
	duplicates: [] as string[],
	dismissedDuplicateNotices: [] as string[],
	setDismissedDuplicateNotices: () => null,
} );

export default DuplicatedPaymentMethodsContext;
