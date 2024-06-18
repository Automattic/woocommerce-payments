/**
 * External dependencies
 */
import { createContext } from 'react';
import { PaymentMethodToPluginsMap } from '../../components/duplicate-notice';

const DuplicatedPaymentMethodsContext = createContext( {
	duplicates: {} as PaymentMethodToPluginsMap,
	dismissedDuplicateNotices: {} as PaymentMethodToPluginsMap,
	setDismissedDuplicateNotices: () => null,
} );

export default DuplicatedPaymentMethodsContext;
