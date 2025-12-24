<?php
/**
 * Stripe Customer Data Redaction Script
 *
 * One-time administrative script to redact customer data using Stripe's Redaction API.
 * This script uses Stripe's preview redaction feature to permanently remove customer
 * information in compliance with data deletion requests.
 *
 * This script uses cURL to communicate directly with the Stripe API and does not
 * require the Stripe PHP library. All API requests are made using native PHP cURL.
 *
 * ⚠️  WARNING: REDACTION IS IRREVERSIBLE
 * - Redacted data cannot be recovered
 * - Disputes on redacted transactions will be automatically lost
 * - Refunds become impossible after redaction
 * - Always test in TEST mode first before running in LIVE mode
 *
 * ============================================================================
 * REQUIREMENTS
 * ============================================================================
 *
 * - PHP 7.0 or higher
 * - cURL extension enabled (typically enabled by default in PHP)
 * - No external dependencies required (no Composer packages needed)
 *
 * ============================================================================
 * SETUP INSTRUCTIONS
 * ============================================================================
 *
 * 1. Configure the script:
 *    Edit the configuration constants below with your values:
 *    - PLATFORM_KEY_TEST: Your platform test secret key (sk_test_...)
 *    - PLATFORM_KEY_LIVE: Your platform live secret key (sk_live_...)
 *    - CONNECTED_ACCOUNT: The express/connected account ID (acct_...)
 *    - CUSTOMER_ID: The customer to redact (cus_...)
 *
 *    NOTE: When you redact a Customer, Stripe automatically identifies and redacts
 *    all related objects (PaymentIntents, Charges, Subscriptions, Invoices, etc.).
 *    You typically only need to specify the Customer ID.
 *
 * 2. Security Note:
 *    ⚠️  NEVER commit API keys to version control
 *    ⚠️  Clear your command history after running if using CLI arguments
 *    Consider using environment variables for production use
 *
 * ============================================================================
 * USAGE
 * ============================================================================
 *
 * Method 1: Using Configuration Constants (Recommended for one-time use)
 * ----------------------------------------------------------------------
 * 1. Edit the constants in the CONFIGURATION section below
 * 2. Run in test mode first:
 *    $ php bin/stripe-redaction.php --mode=test --auto-fix
 *
 * 3. If test succeeds, run in live mode:
 *    $ php bin/stripe-redaction.php --mode=live --auto-fix
 *
 * Method 2: Using Command-Line Arguments
 * ---------------------------------------
 * Test mode:
 * $ php bin/stripe-redaction.php \
 *     --mode=test \
 *     --platform-key=sk_test_xxx \
 *     --connected-account=acct_xxx \
 *     --customer=cus_xxx \
 *     --auto-fix
 *
 * Live mode (requires confirmation):
 * $ php bin/stripe-redaction.php \
 *     --mode=live \
 *     --platform-key=sk_live_xxx \
 *     --connected-account=acct_xxx \
 *     --customer=cus_xxx \
 *     --auto-fix
 *
 * ============================================================================
 * COMMAND-LINE OPTIONS
 * ============================================================================
 *
 * Required:
 *   --mode=test|live          Stripe environment to use
 *
 * Optional (override constants):
 *   --platform-key=sk_xxx     Platform account secret key
 *   --connected-account=acct  Connected account ID
 *   --customer=cus_xxx        Customer ID to redact
 *
 * Flags:
 *   --auto-fix                Automatically fix validation errors when possible
 *   --dry-run                 Validate only, don't execute redaction
 *   --skip-confirmation       Skip confirmation prompt (dangerous in live mode)
 *
 * ============================================================================
 * WORKFLOW
 * ============================================================================
 *
 * This script follows Stripe's redaction workflow:
 * 1. Create redaction job with target objects
 * 2. Wait for validation (Stripe identifies all related objects)
 * 3. Handle validation errors if any (auto-fix or manual resolution)
 * 4. Execute redaction when job status is "ready"
 * 5. Verify completion
 *
 * ============================================================================
 * EXAMPLE OUTPUT
 * ============================================================================
 *
 * [TEST MODE] Starting Stripe Redaction Process
 * ==============================================
 * Connected Account: acct_xxx
 * Objects to redact:
 *   - Customer: cus_OVcoGXUUSJfDOn
 *   (Related objects like PaymentIntents, Charges, etc. will be automatically identified)
 *
 * Creating redaction job... ✓
 * Job ID: redact_xxx
 * Status: validating...
 * Status: ready ✓
 *
 * ⚠️  WARNING: This operation is IRREVERSIBLE
 * Type 'CONFIRM' to execute redaction: _
 *
 * @package WooCommerce Payments
 * @version 1.0.0
 */

// Prevent direct access
if ( php_sapi_name() !== 'cli' ) {
	die( 'This script can only be run from the command line.' );
}

// ============================================================================
// CONFIGURATION
// ============================================================================

/**
 * Configuration constants - edit these values for your redaction job
 */
const PLATFORM_KEY_TEST = ''; // sk_test_...
const PLATFORM_KEY_LIVE = ''; // sk_live_...
const CONNECTED_ACCOUNT = ''; // acct_...
const CUSTOMER_ID = ''; // cus_...

// ============================================================================
// BOOTSTRAP
// ============================================================================

// Verify cURL is available
if ( ! function_exists( 'curl_init' ) ) {
	echo "\n❌ Error: cURL extension not available.\n";
	echo "Please enable the cURL extension in your PHP configuration.\n\n";
	exit( 1 );
}

// ============================================================================
// CUSTOM EXCEPTION CLASSES
// ============================================================================

/**
 * Base Stripe exception
 */
class Stripe_Exception extends Exception {}

/**
 * Rate limit exception
 */
class Stripe_RateLimitException extends Stripe_Exception {}

/**
 * Invalid request exception
 */
class Stripe_InvalidRequestException extends Stripe_Exception {}

/**
 * Authentication exception
 */
class Stripe_AuthenticationException extends Stripe_Exception {}

/**
 * API connection exception
 */
class Stripe_ApiConnectionException extends Stripe_Exception {}

/**
 * Generic API error exception
 */
class Stripe_ApiErrorException extends Stripe_Exception {}

// ============================================================================
// STRIPE REDACTION SCRIPT
// ============================================================================

/**
 * Main script class
 */
class Stripe_Redaction_Script {
	/**
	 * CLI arguments
	 *
	 * @var array
	 */
	private $args = [];

	/**
	 * Configuration
	 *
	 * @var array
	 */
	private $config = [];

	/**
	 * Stripe API key
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * Current mode (test or live)
	 *
	 * @var string
	 */
	private $mode;

	/**
	 * Redaction job ID
	 *
	 * @var string
	 */
	private $job_id;

	/**
	 * Constructor
	 *
	 * @param array $argv Command line arguments.
	 */
	public function __construct( $argv ) {
		$this->parse_arguments( $argv );
		$this->setup_configuration();
		$this->validate_configuration();
	}

	/**
	 * Parse command line arguments
	 *
	 * @param array $argv Command line arguments.
	 */
	private function parse_arguments( $argv ) {
		foreach ( $argv as $arg ) {
			if ( strpos( $arg, '--' ) === 0 ) {
				$arg = substr( $arg, 2 );
				if ( strpos( $arg, '=' ) !== false ) {
					list( $key, $value ) = explode( '=', $arg, 2 );
					$this->args[ $key ] = $value;
				} else {
					$this->args[ $arg ] = true;
				}
			}
		}
	}

	/**
	 * Setup configuration from constants and arguments
	 */
	private function setup_configuration() {
		// Mode is required
		$this->mode = $this->args['mode'] ?? '';

		if ( ! in_array( $this->mode, [ 'test', 'live' ], true ) ) {
			$this->error( 'Mode is required. Use --mode=test or --mode=live' );
		}

		// Setup API key
		$this->api_key = $this->args['platform-key'] ?? (
			$this->mode === 'test' ? PLATFORM_KEY_TEST : PLATFORM_KEY_LIVE
		);

		// Setup configuration
		$this->config = [
			'connected_account' => $this->args['connected-account'] ?? CONNECTED_ACCOUNT,
			'customer_id'       => $this->args['customer'] ?? CUSTOMER_ID,
			'auto_fix'          => isset( $this->args['auto-fix'] ),
			'dry_run'           => isset( $this->args['dry-run'] ),
			'skip_confirmation' => isset( $this->args['skip-confirmation'] ),
		];
	}

	/**
	 * Validate configuration
	 */
	private function validate_configuration() {
		$required = [
			'api_key'           => 'Platform API key',
			'connected_account' => 'Connected account ID',
			'customer_id'       => 'Customer ID',
		];

		$missing = [];
		foreach ( $required as $key => $label ) {
			$value = $key === 'api_key' ? $this->api_key : $this->config[ $key ];
			if ( empty( $value ) ) {
				$missing[] = $label;
			}
		}

		if ( ! empty( $missing ) ) {
			$this->error( 'Missing required configuration: ' . implode( ', ', $missing ) );
		}
	}

	/**
	 * Run the redaction process
	 */
	public function run() {
		$this->print_header();
		$this->print_configuration();

		try {
			// Step 1: Create redaction job
			$this->log( "\nCreating redaction job..." );
			$this->create_redaction_job();
			$this->success( "Job ID: {$this->job_id}" );

			// Step 2: Wait for validation
			$this->log( 'Waiting for validation...' );
			$status = $this->wait_for_validation();

			// Step 3: Handle validation errors if needed
			if ( $status === 'validation_failed' ) {
				$this->handle_validation_errors();
				$status = $this->wait_for_validation();
			}

			// Step 4: Execute redaction
			if ( $status === 'ready' ) {
				$this->success( 'Validation complete!' );

				if ( $this->config['dry_run'] ) {
					$this->warning( "\n[DRY RUN] Stopping before execution. Job is ready but not executed." );
					$this->log( "To execute, run again without --dry-run flag." );
					return;
				}

				$this->print_warnings();

				if ( ! $this->confirm_execution() ) {
					$this->warning( "\nRedaction cancelled by user." );
					return;
				}

				$this->execute_redaction();
				$this->verify_redaction();

				$this->success( "\n✓ Redaction completed successfully!" );
			} else {
				$this->error( "Unexpected job status: {$status}" );
			}

		} catch ( Stripe_RateLimitException $e ) {
			$this->error( 'Rate limit exceeded: ' . $e->getMessage() );
		} catch ( Stripe_InvalidRequestException $e ) {
			$this->error( 'Invalid request: ' . $e->getMessage() );
		} catch ( Stripe_AuthenticationException $e ) {
			$this->error( 'Authentication failed: ' . $e->getMessage() . "\nCheck your API key and permissions." );
		} catch ( Stripe_ApiConnectionException $e ) {
			$this->error( 'API connection failed: ' . $e->getMessage() );
		} catch ( Stripe_ApiErrorException $e ) {
			$this->error( 'Stripe API error: ' . $e->getMessage() );
		} catch ( Exception $e ) {
			$this->error( 'Unexpected error: ' . $e->getMessage() );
		}
	}

	/**
	 * Create redaction job
	 */
	private function create_redaction_job() {
		$response = $this->stripe_request(
			'post',
			'/v1/privacy/redaction_jobs',
			[
				'objects' => [
					'customers' => [ $this->config['customer_id'] ],
				],
			]
		);

		$this->job_id = $response['id'];
	}

	/**
	 * Build query string for Stripe API (handles nested arrays properly)
	 *
	 * @param array  $params Request parameters.
	 * @param string $prefix Optional prefix for nested keys.
	 * @return string Query string.
	 */
	private function build_stripe_query( $params, $prefix = '' ) {
		$query_parts = [];

		foreach ( $params as $key => $value ) {
			$full_key = $prefix ? $prefix . '[' . $key . ']' : $key;

			if ( is_array( $value ) ) {
				// Check if this is a nested associative array or indexed array
				if ( array_keys( $value ) !== range( 0, count( $value ) - 1 ) ) {
					// Nested associative array - recurse
					$query_parts[] = $this->build_stripe_query( $value, $full_key );
				} else {
					// Indexed array - use key[] notation for each value
					foreach ( $value as $item ) {
						$query_parts[] = urlencode( $full_key ) . '[]=' . urlencode( $item );
					}
				}
			} else {
				$query_parts[] = urlencode( $full_key ) . '=' . urlencode( $value );
			}
		}

		return implode( '&', $query_parts );
	}

	/**
	 * Make a Stripe API request using cURL
	 *
	 * @param string $method HTTP method (get, post, delete).
	 * @param string $path API endpoint path.
	 * @param array  $params Request parameters.
	 * @return array Response data.
	 * @throws Stripe_RateLimitException When rate limit is exceeded.
	 * @throws Stripe_InvalidRequestException When request is invalid.
	 * @throws Stripe_AuthenticationException When authentication fails.
	 * @throws Stripe_ApiConnectionException When connection fails.
	 * @throws Stripe_ApiErrorException For other API errors.
	 */
	private function stripe_request( $method, $path, $params = [] ) {
		$url = 'https://api.stripe.com' . $path;

		// Prepare headers
		$headers = [
			'Authorization: Bearer ' . $this->api_key,
			'Stripe-Account: ' . $this->config['connected_account'],
			'Content-Type: application/x-www-form-urlencoded',
			'Stripe-Version: 2025-12-15.clover',
		];

		// Initialize cURL
		$curl = curl_init();

		// Set common options
		curl_setopt_array( $curl, [
			CURLOPT_URL            => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER     => $headers,
			CURLOPT_TIMEOUT        => 30,
		] );

		// Set method-specific options
		$method = strtoupper( $method );
		if ( $method === 'POST' ) {
			curl_setopt( $curl, CURLOPT_POST, true );
			if ( ! empty( $params ) ) {
				curl_setopt( $curl, CURLOPT_POSTFIELDS, $this->build_stripe_query( $params ) );
			}
		} elseif ( $method === 'GET' ) {
			if ( ! empty( $params ) ) {
				curl_setopt( $curl, CURLOPT_URL, $url . '?' . $this->build_stripe_query( $params ) );
			}
		} else {
			curl_setopt( $curl, CURLOPT_CUSTOMREQUEST, $method );
			if ( ! empty( $params ) ) {
				curl_setopt( $curl, CURLOPT_POSTFIELDS, $this->build_stripe_query( $params ) );
			}
		}

		// Execute request
		$response_body = curl_exec( $curl );
		$http_code     = curl_getinfo( $curl, CURLINFO_HTTP_CODE );
		$curl_error    = curl_error( $curl );
		curl_close( $curl );

		// Handle connection errors
		if ( $response_body === false ) {
			throw new Stripe_ApiConnectionException( 'cURL error: ' . $curl_error );
		}

		// Decode response
		$response = json_decode( $response_body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			throw new Stripe_ApiErrorException( 'Failed to decode JSON response: ' . json_last_error_msg() );
		}

		// Handle HTTP errors
		if ( $http_code >= 400 ) {
			$error_message = isset( $response['error']['message'] )
				? $response['error']['message']
				: 'Unknown error';

			switch ( $http_code ) {
				case 429:
					throw new Stripe_RateLimitException( $error_message );
				case 401:
				case 403:
					throw new Stripe_AuthenticationException( $error_message );
				case 400:
				case 404:
					throw new Stripe_InvalidRequestException( $error_message );
				default:
					throw new Stripe_ApiErrorException( $error_message );
			}
		}

		return $response;
	}

	/**
	 * Wait for validation to complete
	 *
	 * @param int $max_attempts Maximum polling attempts.
	 * @return string Job status
	 */
	private function wait_for_validation( $max_attempts = 30 ) {
		$attempts = 0;

		while ( $attempts < $max_attempts ) {
			$job = $this->stripe_request(
				'get',
				'/v1/privacy/redaction_jobs/' . $this->job_id
			);

			$status = $job['status'];

			if ( $status === 'ready' || $status === 'validation_failed' ) {
				return $status;
			}

			if ( $status === 'validating' ) {
				$this->log( '  Status: validating...' );
			} else {
				$this->log( "  Status: {$status}" );
			}

			sleep( 2 );
			$attempts++;
		}

		$this->error( 'Validation timeout after ' . ( $max_attempts * 2 ) . ' seconds' );
	}

	/**
	 * Handle validation errors
	 */
	private function handle_validation_errors() {
		$this->warning( "\nValidation failed. Checking errors..." );

		// Get validation errors
		$errors = $this->stripe_request(
			'get',
			'/v1/privacy/redaction_jobs/' . $this->job_id . '/validation_errors'
		);

		if ( isset( $errors['data'] ) && ! empty( $errors['data'] ) ) {
			$this->log( "\nValidation Errors:" );
			foreach ( $errors['data'] as $error ) {
				$this->log( sprintf(
					'  - Object: %s, Code: %s, Message: %s',
					$error['object_id'] ?? 'unknown',
					$error['code'] ?? 'unknown',
					$error['message'] ?? 'No message'
				) );
			}
		}

		if ( $this->config['auto_fix'] ) {
			$this->log( "\nAttempting auto-fix..." );

			$this->stripe_request(
				'post',
				'/v1/privacy/redaction_jobs/' . $this->job_id . '/validate',
				[
					'validation_behavior' => 'fix',
				]
			);

			$this->log( 'Re-validating...' );
		} else {
			$this->error( 'Validation failed. Use --auto-fix to automatically resolve eligible errors.' );
		}
	}

	/**
	 * Execute the redaction
	 */
	private function execute_redaction() {
		$this->log( "\nExecuting redaction..." );

		$this->stripe_request(
			'post',
			'/v1/privacy/redaction_jobs/' . $this->job_id . '/run'
		);

		// Wait for completion
		$attempts = 0;
		$max_attempts = 30;

		while ( $attempts < $max_attempts ) {
			$job = $this->stripe_request(
				'get',
				'/v1/privacy/redaction_jobs/' . $this->job_id
			);

			if ( $job['status'] === 'succeeded' ) {
				$this->success( '  Status: succeeded ✓' );
				return;
			}

			if ( $job['status'] === 'failed' ) {
				$failure_reason = $job['failure_reason'] ?? 'Unknown reason';
				$this->error( 'Redaction failed: ' . $failure_reason );
			}

			$this->log( "  Status: {$job['status']}..." );
			sleep( 5 );
			$attempts++;
		}

		$this->error( 'Redaction timeout after ' . ( $max_attempts * 2 ) . ' seconds' );
	}

	/**
	 * Verify redaction was successful
	 */
	private function verify_redaction() {
		$this->log( "\nVerifying redaction..." );

		try {
			// Try to retrieve customer (should be deleted)
			try {
				$customer = $this->stripe_request(
					'get',
					'/v1/customers/' . $this->config['customer_id']
				);

				if ( isset( $customer['deleted'] ) && $customer['deleted'] ) {
					$this->success( '  Customer deleted: ✓' );
				} else {
					$this->warning( '  Customer still exists (may take time to propagate)' );
				}
			} catch ( Stripe_InvalidRequestException $e ) {
				if ( strpos( $e->getMessage(), 'No such customer' ) !== false ) {
					$this->success( '  Customer deleted: ✓' );
				} else {
					throw $e;
				}
			}

			$this->log( '  Note: All related objects (PaymentIntents, Charges, etc.) have also been redacted' );

		} catch ( Exception $e ) {
			$this->warning( '  Verification error: ' . $e->getMessage() );
		}
	}

	/**
	 * Confirm execution with user
	 *
	 * @return bool True if confirmed
	 */
	private function confirm_execution() {
		if ( $this->config['skip_confirmation'] ) {
			return true;
		}

		if ( $this->mode === 'live' ) {
			echo "\nType 'CONFIRM' to execute LIVE redaction: ";
		} else {
			echo "\nType 'CONFIRM' to execute TEST redaction: ";
		}

		$handle = fopen( 'php://stdin', 'r' );
		$input  = trim( fgets( $handle ) );
		fclose( $handle );

		return $input === 'CONFIRM';
	}

	/**
	 * Print script header
	 */
	private function print_header() {
		$mode_display = strtoupper( $this->mode );
		$color        = $this->mode === 'live' ? "\033[1;31m" : "\033[1;33m";
		$reset        = "\033[0m";

		echo "\n";
		echo "{$color}[{$mode_display} MODE]{$reset} Stripe Redaction Process\n";
		echo str_repeat( '=', 50 ) . "\n";
	}

	/**
	 * Print configuration summary
	 */
	private function print_configuration() {
		echo "Connected Account: {$this->config['connected_account']}\n";
		echo "Objects to redact:\n";
		echo "  - Customer: {$this->config['customer_id']}\n";
		echo "  (Related objects like PaymentIntents, Charges, etc. will be automatically identified)\n";

		if ( $this->config['dry_run'] ) {
			$this->warning( "\n[DRY RUN MODE] Validation only, will not execute redaction" );
		}
	}

	/**
	 * Print warnings before execution
	 */
	private function print_warnings() {
		$this->warning( "\n⚠️  WARNING: This operation is IRREVERSIBLE" );
		$this->warning( "⚠️  Disputes will be automatically lost" );
		$this->warning( "⚠️  Refunds will become impossible" );
	}

	/**
	 * Log message
	 *
	 * @param string $message Message to log.
	 */
	private function log( $message ) {
		echo $message . "\n";
	}

	/**
	 * Log success message
	 *
	 * @param string $message Message to log.
	 */
	private function success( $message ) {
		echo "\033[0;32m{$message}\033[0m\n";
	}

	/**
	 * Log warning message
	 *
	 * @param string $message Message to log.
	 */
	private function warning( $message ) {
		echo "\033[0;33m{$message}\033[0m\n";
	}

	/**
	 * Log error and exit
	 *
	 * @param string $message Error message.
	 */
	private function error( $message ) {
		echo "\n\033[0;31m❌ Error: {$message}\033[0m\n\n";
		exit( 1 );
	}
}

// ============================================================================
// RUN SCRIPT
// ============================================================================

$script = new Stripe_Redaction_Script( $argv );
$script->run();
