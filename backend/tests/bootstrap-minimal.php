<?php
/**
 * Minimal PHPUnit Bootstrap for SuiteCRM API Tests
 * Uses the same initialization as the API to avoid Administration bean issues
 */

// Define test environment
define('PHPUNIT_TEST', true);
define('ENTRY_POINT_TYPE', 'api');

// Set up paths
$suitecrmPath = getenv('SUITECRM_PATH') ?: '/var/www/html';
$apiPath = $suitecrmPath . '/custom/api';

// Check if SuiteCRM is installed
if (!file_exists($suitecrmPath . '/config.php')) {
    die("SuiteCRM is not installed. Please run the installation first.\n");
}

// Define sugarEntry
if (!defined('sugarEntry')) {
    define('sugarEntry', true);
}

// Change to SuiteCRM directory
chdir($suitecrmPath);

// Use the same initialization as the API
require_once $apiPath . '/init.php';

// Load API classes
require_once $apiPath . '/auth/JWT.php';
require_once $apiPath . '/middleware/AuthMiddleware.php';
require_once $apiPath . '/Request.php';
require_once $apiPath . '/Response.php';
require_once $apiPath . '/Router.php';

// Autoload test classes
spl_autoload_register(function ($class) use ($apiPath, $suitecrmPath) {
    // API classes
    $prefix = 'Api\\';
    $len = strlen($prefix);
    
    if (strncmp($prefix, $class, $len) === 0) {
        $relative_class = substr($class, $len);
        $file = $apiPath . '/' . str_replace('\\', '/', $relative_class) . '.php';
        
        if (file_exists($file)) {
            require_once $file;
        }
    }
    
    // Test classes
    $testPrefix = 'Tests\\';
    $testLen = strlen($testPrefix);
    
    if (strncmp($testPrefix, $class, $testLen) === 0) {
        $relative_class = substr($class, $testLen);
        $file = $suitecrmPath . '/tests/' . str_replace('\\', '/', $relative_class) . '.php';
        
        if (file_exists($file)) {
            require_once $file;
        }
    }
});

// Create test database helper
class TestDatabaseHelper {
    private static $createdRecords = [];
    
    /**
     * Create a test record
     */
    public static function createTestRecord($module, $data = []) {
        // For unit tests, just return a mock object
        $bean = new stdClass();
        $bean->id = uniqid();
        $bean->module_name = $module;
        
        foreach ($data as $field => $value) {
            $bean->$field = $value;
        }
        
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
        self::$createdRecords = [];
    }
}

// Base test case for unit tests
abstract class SuiteCRMUnitTest extends PHPUnit\Framework\TestCase {
    protected function setUp(): void {
        parent::setUp();
    }
    
    protected function tearDown(): void {
        TestDatabaseHelper::cleanUp();
        parent::tearDown();
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
    }
    
    protected function tearDown(): void {
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
}

echo "PHPUnit bootstrap loaded successfully.\n";
echo "SuiteCRM Path: {$suitecrmPath}\n";
echo "API Path: {$apiPath}\n";
echo "Ready for testing.\n\n";