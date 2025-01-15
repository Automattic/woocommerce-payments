/**
 * Internal dependencies
 */
import type { PaymentMethodMapEntry } from '../payment-methods-map';
import { PaymentMethodDefinitions } from './types';
import { mapDefinitionToEntry } from './mapping';

/**
 * Generated payment method information using the backend-defined types
 */
const GeneratedPaymentMethodInformationObject: Record<
	string,
	PaymentMethodMapEntry
> = Object.fromEntries(
	Object.entries( PaymentMethodDefinitions ).map( ( [ key, def ] ) => [
		key,
		mapDefinitionToEntry( def ),
	] )
);

export default GeneratedPaymentMethodInformationObject;
