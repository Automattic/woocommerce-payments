/** @format **/

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Card, CardBody, CardHeader } from '@wordpress/components';
import { Badge } from '@woocommerce/components';
import {
	CollapsibleList,
	List,
	TaskItem,
	Text,
} from '@woocommerce/experimental';
import { useDispatch } from '@wordpress/data';

const DAY_IN_MS = 24 * 60 * 60 * 1000;

const TaskList = ( {
	collapsible = false,
	deletedTasks,
	dismissedTasks,
	remindMeLaterTasks,
	tasks,
} ) => {
	const { createNotice } = useDispatch( 'core/notices' );
	const { updateOptions } = useDispatch( 'wc/admin/options' );

	const undoDismissTask = ( key, dismissedTasksList, optionName ) => {
		const updatedDismissedTasks = dismissedTasksList.filter(
			( task ) => task !== key
		);

		/*eslint-disable camelcase*/
		updateOptions( {
			[ optionName ]: updatedDismissedTasks,
		} );
		/*eslint-enable camelcase*/
	};

	const dismissSelectedTask = ( {
		task,
		dismissedTasksList,
		noticeMessage,
		optionName,
	} ) => {
		const { key, onDismiss } = task;
		createNotice( 'success', noticeMessage, {
			actions: [
				{
					label: __( 'Undo', 'woocommerce-payments' ),
					onClick: () =>
						undoDismissTask( key, dismissedTasksList, optionName ),
				},
			],
		} );

		/*eslint-disable camelcase*/
		updateOptions( {
			[ optionName ]: [ ...dismissedTasksList, key ],
		} );
		/*eslint-enable camelcase*/
		if ( onDismiss ) {
			onDismiss();
		}
	};

	const dismissTask = ( task, type ) => {
		const params =
			'dismiss' === type
				? {
						task,
						dismissedTasksList: dismissedTasks,
						noticeMessage: __(
							'Task dismissed',
							'woocommerce-payments'
						),
						optionName: 'woocommerce_dismissed_todo_tasks',
				  }
				: {
						task,
						dismissedTasksList: deletedTasks,
						noticeMessage: __(
							'Task deleted',
							'woocommerce-payments'
						),
						optionName: 'woocommerce_deleted_todo_tasks',
				  };
		dismissSelectedTask( params );
	};

	const undoRemindTaskLater = ( key ) => {
		const {
			// eslint-disable-next-line no-unused-vars
			[ key ]: oldValue,
			...updatedRemindMeLaterTasks
		} = remindMeLaterTasks;

		/*eslint-disable camelcase*/
		updateOptions( {
			woocommerce_remind_me_later_todo_tasks: updatedRemindMeLaterTasks,
		} );
		/*eslint-enable camelcase*/
	};

	const remindTaskLater = ( { key, onDismiss } ) => {
		createNotice(
			'success',
			__( 'Task postponed until tomorrow', 'woocommerce-payments' ),
			{
				actions: [
					{
						label: __( 'Undo', 'woocommerce-payments' ),
						onClick: () => undoRemindTaskLater( key ),
					},
				],
			}
		);

		const dismissTime = Date.now() + DAY_IN_MS;
		/*eslint-disable camelcase*/
		updateOptions( {
			woocommerce_remind_me_later_todo_tasks: {
				...remindMeLaterTasks,
				[ key ]: dismissTime,
			},
		} );
		/*eslint-enable camelcase*/
		if ( onDismiss ) {
			onDismiss();
		}
	};

	const nowTimestamp = Date.now();
	const visibleTasks = tasks.filter(
		( task ) =>
			! deletedTasks.includes( task.key ) &&
			! dismissedTasks.includes( task.key ) &&
			( ! remindMeLaterTasks[ task.key ] ||
				remindMeLaterTasks[ task.key ] < nowTimestamp )
	);

	const ListComp = collapsible ? CollapsibleList : List;

	const listProps = collapsible
		? {
				collapseLabel: __( 'Hide tasks', 'woocommerce-payments' ),
				expandLabel: __( 'Show tasks', 'woocommerce-payments' ),
				collapsed: false,
		  }
		: {};

	const pendingTaskCount = visibleTasks.filter( ( task ) => ! task.completed )
		.length;
	return (
		<Card
			size="large"
			className="woocommerce-task-card woocommerce-homescreen-card"
		>
			<CardHeader size="medium">
				<div className="wooocommerce-task-card__header">
					<Text variant="title.small">
						{ __( 'Things to do', 'woocommerce-payments' ) }
					</Text>
					{ 0 < pendingTaskCount && (
						<Badge count={ pendingTaskCount } />
					) }
				</div>
			</CardHeader>
			<CardBody>
				<ListComp animation="custom" { ...listProps }>
					{ visibleTasks.map( ( task ) => (
						<TaskItem
							key={ task.key }
							title={ task.title }
							completed={ task.completed }
							content={ task.content || task.additionalInfo }
							expanded
							action={ task.onClick }
							time={ task.time }
							level={ task.level }
							onDelete={
								task.isDeletable && task.completed
									? () => dismissTask( task, 'delete' )
									: undefined
							}
							onDismiss={
								task.isDismissable
									? () => dismissTask( task, 'dismiss' )
									: undefined
							}
							remindMeLater={
								task.allowRemindMeLater
									? () => remindTaskLater( task )
									: undefined
							}
						/>
					) ) }
				</ListComp>
			</CardBody>
		</Card>
	);
};

export default TaskList;
