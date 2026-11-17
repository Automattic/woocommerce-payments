/**
 * How long, in seconds, the Connect page waits for a new test-drive account before redirecting anyway.
 *
 * Outside the US, Stripe checks the test identity document asynchronously, which usually takes 45-90 seconds
 * after the account is created, so the account stays `pending_verification` until then.
 */
export const testDriveSetupMaxDuration = 150;

/**
 * Whether the Connect page can stop waiting for a new test-drive account and redirect the merchant.
 *
 * @param status         The account status, if the account data has one.
 * @param elapsedSeconds Seconds since the test-drive setup started.
 */
export const isTestDriveSetupDone = (
	status: string | undefined,
	elapsedSeconds: number
): boolean =>
	( !! status && ! status.includes( 'pending' ) ) ||
	elapsedSeconds > testDriveSetupMaxDuration;
