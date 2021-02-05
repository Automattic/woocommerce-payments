/**
 * Internal dependencies
 */
import { Event as TimelineEvent } from '../types';

export interface Event extends TimelineEvent {
	type: 'authorized';
	id: string;
	datetime: number;
}
export type Timeline = Event[];

export class Event implements TimelineEvent {
	constructor( id: string, date: number ) {
		return {
			type: 'authorized',
			id: id,
			datetime: date,
		};
	}
}
