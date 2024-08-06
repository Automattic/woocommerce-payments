/**
 * External dependencies
 */
import testUserFlow from '../flows/testUserFlow.js';

export const options = {
	scenarios: {
		smokeTest: {
			executor: 'ramping-arrival-rate',
			startTime: '0s',
			startRate: 0,
			timeUnit: '1s',
			preAllocatedVUs: 5,
			maxVUs: 10,
			gracefulStop: '30s',
			exec: 'userFlows',
			stages: [
				{ target: 33, duration: '30s' }, // go from 0 to 33 iters/s in the first 30 seconds
				{ target: 33, duration: '2m' }, // hold at 33 iters/s for 2 minutes
				{ target: 0, duration: '30s' }, // ramp down back to 0 iters/s over the last 30 seconds
			],
		},
	},
};

export function userFlows() {
	testUserFlow();
}
