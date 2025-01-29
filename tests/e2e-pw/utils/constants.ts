export const shouldRunSubscriptionsTests =
	process.env.SKIP_WC_SUBSCRIPTIONS_TESTS !== '1';

export const shouldRunActionSchedulerTests =
	process.env.SKIP_WC_ACTION_SCHEDULER_TESTS !== '1';

export const shouldRunWCBlocksTests = process.env.SKIP_WC_BLOCKS_TESTS !== '1';

export const products = {
	SUBSCRIPTION_SIGNUP_FEE: 70,
	SUBSCRIPTION_NO_SIGNUP_FEE: 88,
};
