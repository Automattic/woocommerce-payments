export const shouldRunSubscriptionsTests =
	process.env.SKIP_WC_SUBSCRIPTIONS_TESTS !== '1';

export const shouldRunActionSchedulerTests =
	process.env.SKIP_WC_ACTION_SCHEDULER_TESTS !== '1';

export const shouldRunWCBlocksTests = process.env.SKIP_WC_BLOCKS_TESTS !== '1';

export const wooCoreVersion = process.env.E2E_WC_VERSION;

export const isAtomicSite = process.env.NODE_ENV === 'atomic';
