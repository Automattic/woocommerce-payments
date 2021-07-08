/**
 * Internal dependencies
 */
import { usePaymentCompleteHandler } from './hooks';

export const SavedTokenHandler = ( {
	api,
	stripe,
	elements,
	eventRegistration: { onCheckoutAfterProcessingWithSuccess },
	emitResponse,
} ) => {
	// Once the server has completed payment processing, confirm the intent of necessary.
	usePaymentCompleteHandler(
		api,
		stripe,
		elements,
		onCheckoutAfterProcessingWithSuccess,
		emitResponse
	);

	return <></>;
};
