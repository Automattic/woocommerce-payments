/**
 * External dependencies
 */
import { createContext } from 'react';

const DuplicatedPaymentMethodsContext = createContext( {
	duplicates: {} as { [ key: string ]: string[] },
	dismissedDuplicateNotices: {} as { [ key: string ]: string[] },
	setDismissedDuplicateNotices: () => null,
} );

export default DuplicatedPaymentMethodsContext;
