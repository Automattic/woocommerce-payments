Syntax: https://mermaid.js.org/syntax/stateDiagram.html

See a better visualization and more up-to-date version at https://excalidraw.com/#json=JwZ2GsZgG4Jm1Wyx-ASc_,X6eXxqskkufGdmtppPxqOw

## Mermaid Diagram for Payment Processing States

Notes: 

- green background: happy state.
- red background: failure state.
- yellow background: happy state but the final state, i.e. ready to relay some information to the user or another system.

```mermaid
stateDiagram-v2
	%% Define styles
	classDef failureState fill:OrangeRed,color:white,font-weight:bold
	classDef happyState fill:ForestGreen,color:white,font-weight:bold
	classDef finalState fill:Yellow,color:black,font-weight:bold
	classDef stateAction fill:Black,color:white,font-style:italic

	%% Define states and their styles. 

	%% Start
	[*] --> initial:::happyState : sync_checkout
	initial --> prepare_data:::happyState
	state prepare_data {
		direction LR
		[*] --> prepare_metadata
		[*] --> prepare_customer_details
	}
	class prepare_metadata, prepare_customer_details stateAction
	
	prepare_data --> prepare_data_failed:::failureState
	prepare_data --> validate_data:::happyState
	state validate_data {
 		direction LR
		[*] --> check_rate_limit
		[*] --> verify_fraud_token
		[*] --> verify_received_and_stored_intents %% Load_Intent_After_Authentication_Step::compare_received_and_stored_intents
		[*] --> verify_minimum_amount
	}
	class check_rate_limit, verify_fraud_token, verify_received_and_stored_intents, verify_minimum_amount stateAction

	validate_data --> validate_data_failed:::failureState
	validate_data --> avoid_duplicate:::happyState
	state avoid_duplicate {
		direction LR
		[*] --> Check_Session_Against_Processing_Order
		[*] --> Check_Attached_Intent_Success
	}
	class Check_Session_Against_Processing_Order, Check_Attached_Intent_Success stateAction

	avoid_duplicate --> detect_duplicate:::finalState 
	avoid_duplicate --> start_processing:::happyState : no duplicate
	state start_processing {
		decide_which_flow_to_go
	}
	class decide_which_flow_to_go stateAction

	start_processing
	start_processing --> process_standard_payment:::happyState
	process_standard_payment --> processing_failed:::failureState
	process_standard_payment --> success:::finalState
	process_standard_payment --> success_with_3ds:::finalState

	start_processing --> update_upe_intent:::happyState
	update_upe_intent --> processing_failed:::failureState
	update_upe_intent --> success:::finalState
	update_upe_intent --> success_with_3ds:::finalState

	start_processing --> setup_payment_intent:::happyState
	setup_payment_intent --> processing_failed:::failureState
	setup_payment_intent --> success:::finalState
	setup_payment_intent --> success_with_3ds:::finalState

	success --> post_payment_actions : emit actions to finish

	state post_payment_actions {
		direction LR
		[*] --> update_saved_payment_method
		[*] --> save_payment_method
		[*] --> store_order_metata
		[*] --> update_order
		[*] --> cleanup
	}

  %% async flow
	[*] --> redirect_upe_processing:::happyState : async_finish_checkout
	state redirect_upe_processing {
		verify_redirect_upe_params
	}
	class verify_redirect_upe_params stateAction

	redirect_upe_processing --> redirect_upe_nothing_todo:::finalState
	redirect_upe_processing --> redirect_upe_invalid_params:::failureState
	redirect_upe_processing --> success
```
