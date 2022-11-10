<?php
/**
 * Core loader.
 *
 * @package WooCommerce\Payments
 */

/** Load contracts. */

require_once __DIR__ . '/Contracts/Server/Request/class-base-request-interface.php';
require_once __DIR__ . '/Contracts/Server/Response/class-base-response-interface.php';


/** Load enums. */

require_once __DIR__ . '/Enums/class-endpoints.php';

/** Load Core Server files */

require_once __DIR__ . '/Server/Request/class-base-request.php';
require_once __DIR__ . '/Server/Request/class-create-charge.php';
require_once __DIR__ . '/Server/Request/class-create-intention.php';
require_once __DIR__ . '/Server/Response/class-response.php';
