/**
 * External dependencies
 */
import { TaskItem } from '@woocommerce/experimental';

export interface TaskItemProps
	extends Omit<
		React.ComponentProps< typeof TaskItem >,
		'inProgress' | 'inProgressLabel' | 'content'
	> {
	/**
	 * Unique key for the task.
	 */
	key: string;
	/**
	 * Used to pass data attributes be rendered with a task, e.g. `data-urgent="true"`.
	 */
	dataAttrs?: Record< string, string | boolean >;

	/**
	 * Whether the task is dismissable.
	 */
	isDismissable?: boolean;

	/**
	 * The task stores its own dismissal and decides its visibility from it. The task
	 * list then hides it for the session only, and neither writes it to nor filters it
	 * by the dismissed-tasks option.
	 */
	ownsDismissal?: boolean;

	/**
	 * Called when the merchant undoes the dismissal of a task that owns its dismissal.
	 */
	onUndoDismiss?: () => void;

	inProgress?: boolean;

	inProgressLabel?: string;

	content: string | React.ReactElement;
}
