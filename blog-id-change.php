<?php
/**
 * For testing purposes
 */

add_action( 'admin_menu', 'as_submenu', 11 );

function as_submenu() {
	add_menu_page(
		'Blog ID change',
		'Blog ID change',
		'manage_woocommerce',
		'/blog_id_change',
		'as_page',
		null,
		'55.8'
	); 
}

/**
 * @psalm-suppress UndefinedClass
 */
function as_page() {
	process_form();
	process_server_request();

	$curr_blog_id = Jetpack_Options::get_option( 'id' );
	$jetpack_options  = get_option("jetpack_options");
	$jetpack_private_options  = get_option("jetpack_private_options");
	$wcpay_account_data = get_option('wcpay_account_data');
	$get_all_jetpack_options = Jetpack_Options::get_all_jetpack_options();

	// Jetpack uses Manager (as Connection_Manager)
	// is_connected() only checks if a blog id a access tokens are present in the DB
	$is_jetpack_connected = Jetpack::connection()->is_connected();
	$jetpack_has_connected_owner = Jetpack::connection()->has_connected_owner();
	$has_valid_tokens = Jetpack::connection()->get_tokens()->validate();

	$wc_payments_api_client = \WC_Payments::create_api_client();
?>
<style>
	.a {
		display: flex;
	}

	.b {
  	flex: 1;
  	border:  solid 1px #ccc;
  	width: 50%;
  	padding: 20px;
  	margin: 20px;
	}

	.c {
		overflow: auto;
	}
</style>
<div class="a">
	<div class="b">
		<pre class="c">
is jetpack connected? <?php echo $is_jetpack_connected ? 'true' : 'false'; ?>

has connected owner (site is active)? <?php echo $jetpack_has_connected_owner ? 'true' : 'false'; ?>

is jetpack (WCPay) connected?: <?php echo $wc_payments_api_client->is_server_connected() ? 'true' : 'false'; ?>

current blog_id (Jetpack_Options::get_option( 'id' )): <?php echo $curr_blog_id; ?>

has valid tokens? <?php print_r($has_valid_tokens); ?>

$jetpack_options:
<?php print_r($jetpack_options); ?>

$jetpack_private_options:
<?php print_r($jetpack_private_options); ?>

$wcpay_account_data:
<?php print_r($wcpay_account_data); ?>

$get_all_jetpack_options:
<?php print_r($get_all_jetpack_options); ?>
		</pre>
	</div>
	<div class="b">
Replace options
<form action="<?php echo admin_url('admin.php?page=blog_id_change'); ?>" method="POST">
	<p>
		<label for="blog_id">Blog ID</label>
		<br>
		<input type="text" name="blog_id" id="blog_id">
	</p>
	<p>
		<label for="blog_token">Blog Token</label>
		<br>
		<input type="text" name="blog_token" id="blog_token">
	</p>
	<p>
		<label for="user_token">User Token</label>
		<br>
		<input type="text" name="user_token" id="user_token">
	</p>
<button type="submit">Replace options</button>
</form>
<hr>
<?php fixConnectionForm(); ?>
	</div>
</div>
<?php
}

function process_form(){
	if (isset($_POST['blog_id']) && !empty($_POST['blog_id'])) {
		$blog_id = $_POST['blog_id'];
		Jetpack_Options::update_option( 'id', $blog_id );
	}

	if (isset($_POST['blog_token']) && !empty($_POST['blog_token'])) {
		$blog_token = $_POST['blog_token'];
		Jetpack_Options::update_option( 'blog_token', $blog_token );
	}

	if (isset($_POST['user_token']) && !empty($_POST['user_token'])) {
		$user_tokens = [
			1 => $_POST['user_token']
		];
		Jetpack_Options::update_option( 'user_tokens', $user_tokens );
	}
}

function process_server_request () {
	if (isset($_POST['fix_wcpay_account'])) {
		require_once __DIR__ . '/includes/wc-payment-api/class-wc-payments-http-interface.php';
		require_once __DIR__ . '/includes/wc-payment-api/class-wc-payments-http.php';

		$user_agent = 'WooCommerce Payments/' . WCPAY_VERSION_NUMBER;
		$db_helper = new WC_Payments_DB();
		$http_class = new WC_Payments_Http( new Automattic\Jetpack\Connection\Manager( 'woocommerce-payments' ) );

		$api = new WC_Payments_API_Client($user_agent, $http_class, $db_helper);

		$previous_blog_id = $_POST['previous_blog_id'];
		$response = $api->link_to_existing_account($previous_blog_id);
		?>
		<pre>
		<?php
		var_dump($response);
		?>
		</pre>
		<?php
	}
}

function fixConnectionForm() {
	$previous_blog_id = '197232839'; // Stored somewhere;
	$current_blog_id = Jetpack_Options::get_option( 'id' );

	// if ($previous_blog_id !== $current_blog_id) {
		?>
<form action="<?php echo admin_url('admin.php?page=blog_id_change'); ?>" method="POST">
	<p>
		<label for="fix_wcpay_account">Action</label>
		<br>
		<input type="text" name="fix_wcpay_account" id="fix_wcpay_account" value="fix_wcpay_account">
	</p>
	<p>
		<label for="">Previous Blog ID</label><br>
		<input type="text" name="previous_blog_id" value="197232839">
	</p>
	<p>
		<label for="">Current Blog ID</label><br>
		<input type="text" name="current_blog_id" value="<?php echo $current_blog_id; ?>">
	</p>
	<button type="submit">Fix WCPay account</button>
</form>
		<?php		
	// }
}