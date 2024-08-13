/** @format */

export const TYPES = {
	INIT_EMBEDDED_ONBOARDING: 'INIT_EMBEDDED_ONBOARDING',
};

export interface OnboardingState {
	accountSession: AccountSession | null;
}

export interface AccountSession {
	id: string;
	data: Record< string, any >;
}

// eslint-disable-next-line @typescript-eslint/naming-convention
export const INITIAL_STATE: OnboardingState = {
	accountSession: null,
};
