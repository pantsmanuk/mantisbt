<?php
# MantisBT - A PHP based bugtracking system

# MantisBT is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 2 of the License, or
# (at your option) any later version.
#
# MantisBT is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with MantisBT.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Mantis Webservice Tests
 *
 * @package Tests
 * @subpackage UnitTests
 * @copyright Copyright MantisBT Team - mantisbt-dev@lists.sourceforge.net
 * @link http://www.mantisbt.org
 */

namespace Mantis\tests\rest;

use Mantis\tests\core\MantisTestCase;
use Mantis\tests\core\RequestBuilder;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;
use stdClass;

# Includes
require_once dirname( __DIR__ ) . '/TestConfig.php';

# MantisBT Core API
require_mantis_core();

/**
 * Base class for REST API test cases
 *
 * @requires extension curl
 * @group REST
 */
abstract class RestBase extends MantisTestCase {
	/**
	 * @var string Base path for REST API
	 */
	protected $base_path = '';

	/**
	 * @var string Xdebug session value to enable, empty to disable debugging.
	 */
	protected $xdebug_session;

	/**
	 * @var string Username
	 */
	protected $userName = 'administrator';

	/**
	 * @var string Password
	 */
	protected $password = 'root';

	/**
	 * @var string The API token to use for authentication
	 */
	protected $token = '';

	/**
	 * @var string Email address
	 */
	protected $email = 'root@localhost';

	/**
	 * @var int User ID
	 */
	protected $userId = 1;

	/**
	 * @var int Project ID
	 */
	protected $projectId = 1;

	/**
	 * @var array Array of Issue IDs to delete
	 */
	private $issueIdsToDelete = array();

	/**
	 * @var array Array of version IDs to delete
	 */
	private $versionIdsToDelete = array();

	/**
	 * @var array List of user ids to delete in tearDown()
	 */
	private $usersToDelete = array();
	/**
	 * setUp
	 * @return void
	 */
	protected function setUp(): void {
		if( !isset( $GLOBALS['MANTIS_TESTSUITE_REST_ENABLED'] ) ||
			!$GLOBALS['MANTIS_TESTSUITE_REST_ENABLED'] ) {
			$this->markTestSkipped( 'The REST API tests are disabled.' );
		}

		$this->assertTrue( array_key_exists( 'MANTIS_TESTSUITE_REST_HOST', $GLOBALS ) &&
			!empty( $GLOBALS['MANTIS_TESTSUITE_REST_HOST'] ),
			"You must define 'MANTIS_TESTSUITE_REST_HOST' in your bootstrap file" );

		$this->base_path = trim( $GLOBALS['MANTIS_TESTSUITE_REST_HOST'], '/' );

		if( array_key_exists( 'MANTIS_TESTSUITE_USERNAME', $GLOBALS ) ) {
			$this->userName = $GLOBALS['MANTIS_TESTSUITE_USERNAME'];
			$this->userId = user_get_id_by_name( $this->userName );
		}

		if( array_key_exists( 'MANTIS_TESTSUITE_PASSWORD', $GLOBALS ) ) {
			$this->password = $GLOBALS['MANTIS_TESTSUITE_PASSWORD'];
		}

		$this->assertTrue( array_key_exists( 'MANTIS_TESTSUITE_API_TOKEN', $GLOBALS ) &&
			!empty( $GLOBALS['MANTIS_TESTSUITE_API_TOKEN'] ),
			"You must define 'MANTIS_TESTSUITE_API_TOKEN' in your bootstrap file" );

		$this->token = $GLOBALS['MANTIS_TESTSUITE_API_TOKEN'];

		$this->xdebug_session = $GLOBALS['MANTIS_TESTSUITE_XDEBUG_SESSION'] ?? '';

		if( array_key_exists( 'MANTIS_TESTSUITE_PROJECT_ID', $GLOBALS ) ) {
			$this->projectId = $GLOBALS['MANTIS_TESTSUITE_PROJECT_ID'];
		} else {
			$this->projectId = 1;
		}
	}

	/**
	 * tearDown
	 * @return void
	 * @throws GuzzleException
	 */
	protected function tearDown(): void {
		foreach( $this->usersToDelete as $t_user_id ) {
			$this->builder()
				 ->delete( '/users/' . $t_user_id, '' )
				 ->send();
		}

		foreach ( $this->issueIdsToDelete as $t_issue_id_to_delete ) {
			$this->builder()
				 ->delete( '/issues', 'id=' . $t_issue_id_to_delete )
				 ->send();
		}

		foreach( $this->versionIdsToDelete as $t_version_id_to_delete ) {
			$this->builder()
				 ->delete( '/projects/' . $t_version_id_to_delete[0] . '/versions/' . $t_version_id_to_delete[1] )
				 ->send();
		}
	}

	/**
	 * Creates a RequestBuilder instance for building a http request.
	 *
	 * @return RequestBuilder
	 */
	public function builder() {
		return new RequestBuilder( $this->base_path, $this->token, $this->xdebug_session );
	}

	/**
	 * Gets the id of the default project used for testing.
	 *
	 * @return integer
	 */
	protected function getProjectId() {
		return $this->projectId;
	}

	/**
	 * Gets the default category used for testing.
	 * @return string
	 */
	protected function getCategory() {
		return 'General';
	}

	/**
	 * Returns a minimal data structure for tests to create a new Issue.
	 *
	 * The Issue Summary is set to TestClass::TestCase with an optional
	 * suffix, followed by a random number.
	 *
	 * @param string $p_suffix Optional Test case suffix.
	 *
	 * @return array
	 */
	protected function getIssueToAdd( $p_suffix = '' ) {
		$t_summary = $this->getTestName();
		if( $p_suffix ) {
			$t_summary .= '-' . $p_suffix;
		}
		return array(
			'summary' => $t_summary . ': test issue ' . rand( 1, 1000000 ),
			'description' => 'description of test issue.',
			'project' => array( 'id' => $this->getProjectId() ),
			'category' => array( 'name' => $this->getCategory() )
		);
	}

	/**
	 * Checks that response was successful and returns JSON body as object.
	 *
	 * @param ResponseInterface $p_response
	 * @param int               $p_status_code Expected HTTP status code
	 *
	 * @return stdClass
	 */
	protected function getJson( ResponseInterface $p_response, $p_status_code = HTTP_STATUS_SUCCESS ) {
		$this->assertEquals( $p_status_code,
			$p_response->getStatusCode(),
			"REST API returned unexpected Status Code"
		);
		return json_decode( $p_response->getBody(), false );
	}


	/**
	 * Marks a test as skipped if there is no configured Anonymous account.
	 *
	 * @return void
	 */
	protected function skipTestIfAnonymousDisabled(){
		if( ! auth_anonymous_enabled() ) {
			$this->markTestSkipped( 'Anonymous access is not enabled' );
		}
	}

	/**
	 * Registers an issue for deletion after the test method has run
	 *
	 * @param integer $p_issue_id Issue identifier.
	 * @return void
	 */
	protected function deleteIssueAfterRun( $p_issue_id ) {
		$this->issueIdsToDelete[] = $p_issue_id;
	}

	/**
	 * Registers a version id to be deleted in tearDown
	 *
	 * @param integer $p_version_id Version identifier.
	 * @return void
	 */
	protected function deleteAfterRunVersion( $p_version_id, $p_project_id = null ) {
		if( is_null( $p_project_id ) ) {
			$p_project_id = $this->getProjectId();
		}

		$this->versionIdsToDelete[] = array( $p_project_id, $p_version_id );
	}

	/**
	 * Capture user id to be deleted in tearDown
	 *
	 * return int|bool The user id or false if no user was created.
	 */
	protected function deleteAfterRunUserIfCreated( $p_response ) {
		$t_user_id = false;
		$t_response_code = $p_response->getStatusCode();

		if( $t_response_code >= 200 && $t_response_code < 300 ) {
			$t_body = json_decode( $p_response->getBody(), true );

			if( isset( $t_body['users'] ) ) {
				$t_users = $t_body['users'];
				$t_user_id = (int)$t_users[0]['id'];
			} elseif( isset( $t_body['user'] ) ) {
				$t_user = $t_body['user'];
				$t_user_id = (int)$t_user['id'];
			} else {
				$t_user_id = (int)$t_body['id'];
			}

			$this->usersToDelete[] = $t_user_id;
		}

		return $t_user_id;
	}

	/**
	 * Utility function to establish DB connection.
	 *
	 * PHPUnit seems to kill the connection after each test case execution;
	 * this allows individual test cases that need the DB to reopen it easily.
	 *
	 * @todo Copied from MantisCoreBase class - see if code duplication can be avoided
	 */
	public static function dbConnect() {
		global $g_hostname, $g_db_username, $g_db_password, $g_database_name,
			   $g_use_persistent_connections;

		db_connect(
			config_get_global( 'dsn', false ),
			$g_hostname,
			$g_db_username,
			$g_db_password,
			$g_database_name,
			$g_use_persistent_connections == ON
		);
	}
}
