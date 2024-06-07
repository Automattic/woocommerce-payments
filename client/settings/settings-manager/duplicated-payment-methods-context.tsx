/**
 * External dependencies
 */
import { createContext } from 'react';
import { DismissedNotices } from '../../components/duplicate-notice';

const DuplicatedPaymentMethodsContext = createContext( {
	duplicates: {} as DismissedNotices,
	dismissedDuplicateNotices: {} as DismissedNotices,
	setDismissedDuplicateNotices: () => null,
} );

export default DuplicatedPaymentMethodsContext;
