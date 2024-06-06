/**
 * External dependencies
 */
import { createContext } from 'react';

interface DismissedDuplicateNotice {
    [key: string]: string[];
}

const DuplicatedPaymentMethodsContext = createContext( {
	duplicates: {} as { [key: string]: string[] },
	dismissedDuplicateNotices: {} as DismissedDuplicateNotice[],
	setDismissedDuplicateNotices: () => null,
} );

export default DuplicatedPaymentMethodsContext;
