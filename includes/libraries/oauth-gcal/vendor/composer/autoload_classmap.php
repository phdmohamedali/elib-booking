<?php

namespace Tyche\BKAP;

$vendorDir = dirname( dirname( __FILE__ ) );
$baseDir   = dirname( $vendorDir );

return array(
	'BKAPGoogle_AccessToken_Revoke'             => $vendorDir . '/google/apiclient/src/aliases.php',
	'BKAPGoogle_AccessToken_Verify'             => $vendorDir . '/google/apiclient/src/aliases.php',
	'BKAPGoogle_AuthHandler_AuthHandlerFactory' => $vendorDir . '/google/apiclient/src/aliases.php',
	'BKAPGoogle_AuthHandler_Guzzle5AuthHandler' => $vendorDir . '/google/apiclient/src/aliases.php',
	'BKAPGoogle_AuthHandler_Guzzle6AuthHandler' => $vendorDir . '/google/apiclient/src/aliases.php',
	'BKAPGoogle_AuthHandler_Guzzle7AuthHandler' => $vendorDir . '/google/apiclient/src/aliases.php',
	'BKAPGoogle_Client'                         => $vendorDir . '/google/apiclient/src/aliases.php',
	'BKAPGoogle_Collection'                     => $vendorDir . '/google/apiclient/src/aliases.php',
	'BKAPGoogle_Exception'                      => $vendorDir . '/google/apiclient/src/aliases.php',
	'BKAPGoogle_Http_Batch'                     => $vendorDir . '/google/apiclient/src/aliases.php',
	'BKAPGoogle_Http_MediaFileUpload'           => $vendorDir . '/google/apiclient/src/aliases.php',
	'BKAPGoogle_Http_REST'                      => $vendorDir . '/google/apiclient/src/aliases.php',
	'BKAPGoogle_Model'                          => $vendorDir . '/google/apiclient/src/aliases.php',
	'BKAPGoogle_Service'                        => $vendorDir . '/google/apiclient/src/aliases.php',
	'BKAPGoogle_Service_Exception'              => $vendorDir . '/google/apiclient/src/aliases.php',
	'BKAPGoogle_Service_Resource'               => $vendorDir . '/google/apiclient/src/aliases.php',
	'BKAPGoogle_Task_Composer'                  => $vendorDir . '/google/apiclient/src/aliases.php',
	'BKAPGoogle_Task_Exception'                 => $vendorDir . '/google/apiclient/src/aliases.php',
	'BKAPGoogle_Task_Retryable'                 => $vendorDir . '/google/apiclient/src/aliases.php',
	'BKAPGoogle_Task_Runner'                    => $vendorDir . '/google/apiclient/src/aliases.php',
	'BKAPGoogle_Utils_UriTemplate'              => $vendorDir . '/google/apiclient/src/aliases.php',
);
