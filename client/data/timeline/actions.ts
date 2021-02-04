/** @format */

/**
 * Internal Dependencies
 */
import { Timeline } from './types';

export function updateTimeline( id: string, data: Timeline ) {
	return {
		id,
		type: 'SET_TIMELINE' as const,
		data,
	};
}

export function updateErrorForTimeline( id: string, error: Error ) {
	return {
		id,
		type: 'SET_ERROR_FOR_TIMELINE' as const,
		error,
	};
}

export type TimelineAction = ReturnType<
	typeof updateTimeline | typeof updateErrorForTimeline
>;
