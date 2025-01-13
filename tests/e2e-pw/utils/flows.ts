export const runSubscriptionsTests =
	process.env.SKIP_WC_SUBSCRIPTIONS_TESTS !== '1';

export const runActionSchedulerTests =
	process.env.SKIP_WC_ACTION_SCHEDULER_TESTS !== '1';
