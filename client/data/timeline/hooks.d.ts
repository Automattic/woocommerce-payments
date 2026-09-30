/** @format */

/**
 * Type declarations for the (untyped) timeline hooks in `./hooks.js`.
 *
 * `useTimeline` was previously typed via an ambient declaration in the data
 * barrel; now that consumers import it from this slice directly, the type lives
 * alongside the implementation.
 */
import { TimelineItem } from './types';
import { ApiError } from '../../types/errors';

export function useTimeline( transactionId: string ): {
	timeline: Array< TimelineItem >;
	timelineError: ApiError | undefined;
	isLoading: boolean;
};
