declare const wcpaySettings: {
	connectUrl: string;
	isSubscriptionsActive: boolean;
	featureFlags: {
		customSearch: boolean;
	};
	fraudServices: unknown[];
	isJetpackConnected: boolean;
	accountStatus: {
		error?: boolean;
		status?: string;
	};
	connect: {
		country: string;
	};
};
