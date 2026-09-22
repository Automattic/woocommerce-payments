/**
 * External dependencies
 */
import { createContext, Dispatch, SetStateAction } from 'react';
import { PaymentMethodToPluginsMap } from '../../components/duplicate-notice';

const DuplicatedPaymentMethodsContext = createContext< {
	duplicates: PaymentMethodToPluginsMap;
	dismissedDuplicateNotices: PaymentMethodToPluginsMap;
	setDismissedDuplicateNotices: Dispatch<
		SetStateAction< PaymentMethodToPluginsMap >
	>;
} >( {
	duplicates: {} as PaymentMethodToPluginsMap,
	dismissedDuplicateNotices: {} as PaymentMethodToPluginsMap,
	setDismissedDuplicateNotices: () => null,
} );

export default DuplicatedPaymentMethodsContext;
