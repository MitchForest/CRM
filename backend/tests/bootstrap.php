<?php
/**
 * PHPUnit Bootstrap File for SuiteCRM API Tests
 * 
 * This file initializes the SuiteCRM environment for testing
 */

// Define test environment
define('PHPUNIT_TEST', true);
define('ENTRY_POINT_TYPE', 'api');

// Set up paths - Adjust for Docker environment
if (file_exists('/.dockerenv')) {
    // Running inside Docker container
    $suitecrmPath = getenv('SUITECRM_PATH') ?: '/var/www/html';
    $apiPath = '/var/www/html/custom/api';
} else {
    // Running on host
    $suitecrmPath = getenv('SUITECRM_PATH') ?: __DIR__ . '/../suitecrm';
    $apiPath = __DIR__ . '/../custom/api';
}

// Check if SuiteCRM is installed
if (!file_exists($suitecrmPath . '/config.php')) {
    die("SuiteCRM is not installed. Please run the installation first.\n");
}

// Load SuiteCRM bootstrap
if (!defined('sugarEntry')) {
    define('sugarEntry', true);
}

// Change to SuiteCRM directory for proper includes
chdir($suitecrmPath);

// Load SuiteCRM configuration and bootstrap
require_once 'config.php';
require_once 'include/entryPoint.php';
require_once 'include/SugarLogger/LoggerManager.php';
require_once 'modules/Users/User.php';
require_once 'include/utils.php';
require_once 'include/database/DBManagerFactory.php';
require_once 'include/SugarObjects/SugarConfig.php';
require_once 'data/BeanFactory.php';
require_once 'include/utils/mvc_utils.php';
require_once 'include/MVC/SugarApplication.php';

// Initialize globals
global $sugar_config, $db, $current_user, $log;

// Set up database connection
$db = DBManagerFactory::getInstance();

// Set up logger to be quiet during tests
$log = LoggerManager::getLogger('SugarCRM');
$log->setLevel('fatal');

// Create test user for authentication
$current_user = new User();
$current_user->getSystemUser();

// Load API classes
require_once $apiPath . '/auth/JWT.php';
require_once $apiPath . '/middleware/AuthMiddleware.php';
require_once $apiPath . '/Request.php';
require_once $apiPath . '/Response.php';
require_once $apiPath . '/Router.php';

// Autoload DTO classes
spl_autoload_register(function ($class) use ($apiPath) {
    $prefix = 'Api\\';
    $len = strlen($prefix);
    
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $apiPath . '/' . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require_once $file;
    }
});

// Create test database helper
class TestDatabaseHelper {
    private static $createdRecords = [];
    
    /**
     * Create a test record
     */
    public static function createTestRecord($module, $data = []) {
        $bean = BeanFactory::newBean($module);
        
        foreach ($data as $field => $value) {
            $bean->$field = $value;
        }
        
        $bean->save();
        
        // Track for cleanup
        self::$createdRecords[] = [
            'module' => $module,
            'id' => $bean->id
        ];
        
        return $bean;
    }
    
    /**
     * Clean up all test records
     */
    public static function cleanUp() {
        global $db;
        
        foreach (self::$createdRecords as $record) {
            $bean = BeanFactory::getBean($record['module'], $record['id']);
            if ($bean) {
                $bean->mark_deleted($record['id']);
            }
        }
        
        self::$createdRecords = [];
    }
}

// Base test case for integration tests
abstract class SuiteCRMIntegrationTest extends PHPUnit\Framework\TestCase {
    protected $db;
    protected $current_user;
    
    protected function setUp(): void {
        global $db, $current_user;
        
        $this->db = $db;
        $this->current_user = $current_user;
        
        // Start transaction for test isolation
        $this->db->query("START TRANSACTION");
    }
    
    protected function tearDown(): void {
        // Rollback transaction to keep database clean
        $this->db->query("ROLLBACK");
        
        // Clean up any created records
        TestDatabaseHelper::cleanUp();
    }
    
    /**
     * Create a test user with JWT token
     */
    protected function createAuthenticatedUser($username = 'testuser', $password = 'testpass') {
        $user = TestDatabaseHelper::createTestRecord('Users', [
            'user_name' => $username,
            'user_hash' => password_hash($password, PASSWORD_DEFAULT),
            'status' => 'Active',
            'is_admin' => 0,
            'first_name' => 'Test',
            'last_name' => 'User'
        ]);
        
        // Generate JWT token
        $jwt = new \Api\Auth\JWT();
        $payload = [
            'user_id' => $user->id,
            'username' => $user->user_name,
            'exp' => time() + 3600
        ];
        
        $token = $jwt->encode($payload);
        
        return [
            'user' => $user,
            'token' => $token
        ];
    }
    
    /**
     * Make API request
     */
    protected function makeApiRequest($method, $endpoint, $data = [], $token = null) {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = "/custom/api/index.php{$endpoint}";
        
        if ($token) {
            $_SERVER['HTTP_AUTHORIZATION'] = "Bearer {$token}";
        }
        
        // Set up request data
        if ($method === 'GET') {
            $_GET = $data;
        } else {
            $_POST = $data;
            $_SERVER['CONTENT_TYPE'] = 'application/json';
        }
        
        // Create request and response objects
        $request = new \Api\Request();
        $response = new \Api\Response();
        
        return [
            'request' => $request,
            'response' => $response
        ];
    }
    
    /**
     * Assert API response
     */
    protected function assertApiResponse($response, $expectedStatus, $expectedData = null) {
        $this->assertEquals($expectedStatus, $response->getStatusCode());
        
        if ($expectedData !== null) {
            $actualData = json_decode($response->getBody(), true);
            
            if (is_array($expectedData)) {
                foreach ($expectedData as $key => $value) {
                    $this->assertArrayHasKey($key, $actualData);
                    $this->assertEquals($value, $actualData[$key]);
                }
            } else {
                $this->assertEquals($expectedData, $actualData);
            }
        }
    }
}

// Clean up any previous test data on bootstrap
TestDatabaseHelper::cleanUp();

echo "PHPUnit bootstrap loaded successfully.\n";
echo "SuiteCRM Path: {$suitecrmPath}\n";
echo "API Path: {$apiPath}\n";
echo "Test database ready.\n\n";