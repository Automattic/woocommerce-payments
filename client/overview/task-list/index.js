/** @format **/

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Card, CardBody, CardHeader } from '@wordpress/components';
import { Badge } from '@woocommerce/components';
import { CollapsibleList, TaskItem, Text } from '@woocommerce/experimental';
import { useDispatch } from '@wordpress/data';
import { useCallback, useEffect, useState } from '@wordpress/element';

const DAY_IN_MS = 24 * 60 * 60 * 1000;

const TaskList = ( { overviewTasksVisibility, tasks } ) => {
	const { createNotice } = useDispatch( 'core/notices' );
	const { updateOptions } = useDispatch( 'wc/admin/options' );
	const [ visibleTasks, setVisibleTasks ] = useState( tasks );
	const {
		deletedTodoTasks,
		dismissedTodoTasks,
		remindMeLaterTodoTasks,
	} = overviewTasksVisibility;

	const getVisibleTasks = useCallback( () => {
		const nowTimestamp = Date.now();
		return tasks.filter(
			( task ) =>
				! deletedTodoTasks.includes( task.key ) &&
				! dismissedTodoTasks.includes( task.key ) &&
				( ! remindMeLaterTodoTasks[ task.key ] ||
					remindMeLaterTodoTasks[ task.key ] < nowTimestamp )
		);
	}, [
		deletedTodoTasks,
		dismissedTodoTasks,
		remindMeLaterTodoTasks,
		tasks,
	] );

	useEffect( () => {
		setVisibleTasks( getVisibleTasks() );
	}, [ tasks, getVisibleTasks ] );

	const undoDismissTask = async ( key, dismissedTasks, optionName ) => {
		const updatedDismissedTasks = dismissedTasks.filter(
			( task ) => task !== key
		);

		dismissedTasks.splice( dismissedTodoTasks.indexOf( key ), 1 );
		setVisibleTasks( getVisibleTasks() );

		await updateOptions( {
			[ optionName ]: updatedDismissedTasks,
		} );
	};

	const dismissSelectedTask = async ( {
		task,
		dismissedTasks,
		noticeMessage,
		optionName,
	} ) => {
		const { key, onDismiss } = task;

		dismissedTasks.push( key );
		setVisibleTasks( getVisibleTasks() );

		await updateOptions( {
			[ optionName ]: [ ...dismissedTasks ],
		} );

		createNotice( 'success', noticeMessage, {
			actions: [
				{
					label: __( 'Undo', 'woocommerce-payments' ),
					onClick: () =>
						undoDismissTask( key, dismissedTasks, optionName ),
				},
			],
		} );
		if ( onDismiss ) {
			onDismiss();
		}
	};

	const dismissTask = ( task, type ) => {
		const params =
			'dismiss' === type
				? {
						task,
						dismissedTasks: dismissedTodoTasks,
						noticeMessage: __(
							'Task dismissed',
							'woocommerce-payments'
						),
						optionName: 'woocommerce_dismissed_todo_tasks',
				  }
				: {
						task,
						dismissedTasks: deletedTodoTasks,
						noticeMessage: __(
							'Task deleted',
							'woocommerce-payments'
						),
						optionName: 'woocommerce_deleted_todo_tasks',
				  };
		dismissSelectedTask( params );
	};

	const undoRemindTaskLater = async ( key ) => {
		const {
			// eslint-disable-next-line no-unused-vars
			[ key ]: oldValue,
			...updatedRemindMeLaterTasks
		} = remindMeLaterTodoTasks;

		delete remindMeLaterTodoTasks[ key ];
		setVisibleTasks( getVisibleTasks() );

		await updateOptions( {
			woocommerce_remind_me_later_todo_tasks: updatedRemindMeLaterTasks,
		} );
	};

	const remindTaskLater = async ( { key, onDismiss } ) => {
		const dismissTime = Date.now() + DAY_IN_MS;
		remindMeLaterTodoTasks[ key ] = dismissTime;
		setVisibleTasks( getVisibleTasks() );

		await updateOptions( {
			woocommerce_remind_me_later_todo_tasks: {
				...remindMeLaterTodoTasks,
				[ key ]: dismissTime,
			},
		} );

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
		if ( onDismiss ) {
			onDismiss();
		}
	};

	if ( ! visibleTasks.length ) {
		return <div></div>;
	}

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
				<CollapsibleList
					animation="slide-right"
					collapsed={ false }
					show={ 5 }
					collapseLabel={ __( 'Hide tasks', 'woocommerce-payments' ) }
					expandLabel={ __( 'Show tasks', 'woocommerce-payments' ) }
				>
					{ visibleTasks.map( ( task ) => (
						<TaskItem
							key={ task.key }
							title={ task.title }
							actionLabel={ task.actionLabel }
							completed={ task.completed }
							content={ task.content }
							additionalInfo={ task.additionalInfo }
							showActionButton={ task.showActionButton }
							expandable={ task.expandable }
							expanded={ task.expanded }
							action={ task.action }
							onClick={ task.onClick }
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
							onSnooze={
								task.allowSnooze
									? () => remindTaskLater( task )
									: undefined
							}
						/>
					) ) }
				</CollapsibleList>
			</CardBody>
		</Card>
	);
};

export default TaskList;
