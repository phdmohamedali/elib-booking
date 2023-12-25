<?php

if ( class_exists( 'BKAPGoogle_Client', false ) ) {
	// Prevent error with preloading in PHP 7.4
	// @see https://github.com/googleapis/google-api-php-client/issues/1976
	return;
}

$classMap = array(
	'BKAPGoogle\\BKAPClient'                      => 'BKAPGoogle_Client',
	'BKAPGoogle\\Service'                         => 'BKAPGoogle_Service',
	'BKAPGoogle\\AccessToken\\Revoke'             => 'BKAPGoogle_AccessToken_Revoke',
	'BKAPGoogle\\AccessToken\\Verify'             => 'BKAPGoogle_AccessToken_Verify',
	'BKAPGoogle\\Model'                           => 'BKAPGoogle_Model',
	'BKAPGoogle\\Utils\\UriTemplate'              => 'BKAPGoogle_Utils_UriTemplate',
	'BKAPGoogle\\AuthHandler\\Guzzle6AuthHandler' => 'BKAPGoogle_AuthHandler_Guzzle6AuthHandler',
	'BKAPGoogle\\AuthHandler\\Guzzle7AuthHandler' => 'BKAPGoogle_AuthHandler_Guzzle7AuthHandler',
	'BKAPGoogle\\AuthHandler\\Guzzle5AuthHandler' => 'BKAPGoogle_AuthHandler_Guzzle5AuthHandler',
	'BKAPGoogle\\AuthHandler\\AuthHandlerFactory' => 'BKAPGoogle_AuthHandler_AuthHandlerFactory',
	'BKAPGoogle\\Http\\Batch'                     => 'BKAPGoogle_Http_Batch',
	'BKAPGoogle\\Http\\MediaFileUpload'           => 'BKAPGoogle_Http_MediaFileUpload',
	'BKAPGoogle\\Http\\REST'                      => 'BKAPGoogle_Http_REST',
	'BKAPGoogle\\Task\\Retryable'                 => 'BKAPGoogle_Task_Retryable',
	'BKAPGoogle\\Task\\Exception'                 => 'BKAPGoogle_Task_Exception',
	'BKAPGoogle\\Task\\Runner'                    => 'BKAPGoogle_Task_Runner',
	'BKAPGoogle\\Collection'                      => 'BKAPGoogle_Collection',
	'BKAPGoogle\\Service\\Exception'              => 'BKAPGoogle_Service_Exception',
	'BKAPGoogle\\Service\\Resource'               => 'BKAPGoogle_Service_Resource',
	'BKAPGoogle\\Exception'                       => 'BKAPGoogle_Exception',
);

foreach ( $classMap as $class => $alias ) {
	class_alias( $class, $alias );
}

/**
 * This class needs to be defined explicitly as scripts must be recognized by
 * the autoloader.
 */
class BKAPGoogle_Task_Composer extends \BKAPGoogle\Task\Composer {

}

if ( \false ) {
	class BKAPGoogle_AccessToken_Revoke extends \BKAPGoogle\AccessToken\Revoke {}
	class BKAPGoogle_AccessToken_Verify extends \BKAPGoogle\AccessToken\Verify {}
	class BKAPGoogle_AuthHandler_AuthHandlerFactory extends \BKAPGoogle\AuthHandler\AuthHandlerFactory {}
	class BKAPGoogle_AuthHandler_Guzzle5AuthHandler extends \BKAPGoogle\AuthHandler\Guzzle5AuthHandler {}
	class BKAPGoogle_AuthHandler_Guzzle6AuthHandler extends \BKAPGoogle\AuthHandler\Guzzle6AuthHandler {}
	class BKAPGoogle_AuthHandler_Guzzle7AuthHandler extends \BKAPGoogle\AuthHandler\Guzzle7AuthHandler {}
	class BKAPGoogle_Client extends \BKAPGoogle\BKAPClient {}
	class BKAPGoogle_Collection extends \BKAPGoogle\Collection {}
	class BKAPGoogle_Exception extends \BKAPGoogle\Exception {}
	class BKAPGoogle_Http_Batch extends \BKAPGoogle\Http\Batch {}
	class BKAPGoogle_Http_MediaFileUpload extends \BKAPGoogle\Http\MediaFileUpload {}
	class BKAPGoogle_Http_REST extends \BKAPGoogle\Http\REST {}
	class BKAPGoogle_Model extends \BKAPGoogle\Model {}
	class BKAPGoogle_Service extends \BKAPGoogle\Service {}
	class BKAPGoogle_Service_Exception extends \BKAPGoogle\Service\Exception {}
	class BKAPGoogle_Service_Resource extends \BKAPGoogle\Service\Resource {}
	class BKAPGoogle_Task_Exception extends \BKAPGoogle\Task\Exception {}
	class BKAPGoogle_Task_Retryable extends \BKAPGoogle\Task\Retryable {}
	class BKAPGoogle_Task_Runner extends \BKAPGoogle\Task\Runner {}
	class BKAPGoogle_Utils_UriTemplate extends \BKAPGoogle\Utils\UriTemplate {}
}
