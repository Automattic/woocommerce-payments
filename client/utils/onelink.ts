/**
 * Stripe brands Link as "Onelink" for UK merchants, matching `LinkDefinition::get_title()` on the server.
 */
export const isOnelinkAccount = (): boolean =>
	wcpaySettings?.accountStatus?.country === 'GB';
