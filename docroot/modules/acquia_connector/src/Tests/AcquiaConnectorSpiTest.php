<?php

/**
 * @file
 * Definition of Drupal\acquia_connector\Tests\AcquiaConnectorSpiTest.
 */

namespace Drupal\acquia_connector\Tests;

use Drupal\simpletest\WebTestBase;
use Drupal\acquia_connector\Controller\SpiController;
use Drupal\acquia_connector\Controller\VariablesController;
use Drupal\Component\Serialization\Json;

/**
 * Tests the functionality of the Acquia SPI module.
 *
 * @group Acquia connector
 */
class AcquiaConnectorSpiTest extends WebTestBase {
  protected $strictConfigSchema = FALSE;
  protected $privileged_user;
  protected $setup_path;
  protected $credentials_path;
  protected $settings_path;
  protected $status_report_url;
  protected $acqtest_email        = 'TEST_networkuser@example.com';
  protected $acqtest_pass         = 'TEST_password';
  protected $acqtest_id           = 'TEST_AcquiaConnectorTestID';
  protected $acqtest_key          = 'TEST_AcquiaConnectorTestKey';
  protected $acqtest_expired_id   = 'TEST_AcquiaConnectorTestIDExp';
  protected $acqtest_expired_key  = 'TEST_AcquiaConnectorTestKeyExp';
  protected $acqtest_503_id       = 'TEST_AcquiaConnectorTestID503';
  protected $acqtest_503_key      = 'TEST_AcquiaConnectorTestKey503';
  protected $acqtest_error_id     = 'TEST_AcquiaConnectorTestIDErr';
  protected $acqtest_error_key    = 'TEST_AcquiaConnectorTestKeyErr';
  protected $acqtest_name         = 'test name';
  protected $acqtest_machine_name = 'test_name';

  protected $platformKeys = array('php', 'webserver_type', 'webserver_version', 'apache_modules', 'php_extensions', 'php_quantum', 'database_type', 'database_version', 'system_type', 'system_version', 'mysql');
  protected $spiDataKeys = array(
    'spi_data_version',
    'site_key',
    'modules',
    'platform',
    'quantum',
    'system_status',
    'failed_logins',
    '404s',
    'watchdog_size',
    'watchdog_data',
    'last_nodes',
    'last_users',
    'extra_files',
    'ssl_login',
    'file_hashes',
    'hashes_md5',
    'hashes_sha1',
    'fileinfo',
    'distribution',
    'base_version',
    'build_data',
    'roles',
    'uid_0_present',
  );

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('acquia_connector', 'toolbar', 'acquia_connector_test', 'node');

  /**
   *{@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    global $base_url;
    // Enable any modules required for the test
    // Create and log in our privileged user.
    $this->privileged_user = $this->drupalCreateUser(array(
      'administer site configuration',
      'access administration pages',
    ));
    $this->drupalLogin($this->privileged_user);

    // Setup variables.
    $this->environment_change_path = '/admin/config/system/acquia-connector/environment-change';
    $this->credentials_path = 'admin/config/system/acquia-connector/credentials';
    $this->settings_path = 'admin/config/system/acquia-connector';
    $this->status_report_url = 'admin/reports/status';
    $this->base_url = $base_url;

    //local env
    \Drupal::configFactory()->getEditable('acquia_connector.settings')->set('spi.server', $this->base_url)->save();
    \Drupal::configFactory()->getEditable('acquia_connector.settings')->set('spi.ssl_verify', FALSE)->save();
    \Drupal::configFactory()->getEditable('acquia_connector.settings')->set('spi.ssl_override', TRUE)->save();
  }


  /**
   * Helper function for storing UI strings.
   */
  private function acquiaSPIStrings($id) {
    switch ($id) {
      case 'spi-status-text':
        return 'SPI data will be sent once every 30 minutes once cron is called';
      case 'spi-not-sent';
        return 'SPI data has not been sent';
      case 'spi-send-text';
        return 'manually send SPI data';
      case 'spi-data-sent':
        return 'SPI data sent';
      case 'spi-data-sent-error':
        return 'Error sending SPI data. Consult the logs for more information.';
      case 'spi-new-def':
        return 'There are new checks that will be performed on your site by the Acquia Connector';
      case 'provide-site-name':
        return 'provide a site name';
      case 'change-env-detected':
        return 'A change in your site\'s environment has been detected. SPI data cannot be submitted until this is resolved.';
      case 'confirm-action':
        return 'confirm the action you wish to take';
      case 'block-site-message':
        return 'This site has been blocked from sending profile data to Acquia Cloud.';
      case 'unblock-site':
        return 'Unblock this site';
      case 'acquia-hosted':
        return 'Your site is now Acquia hosted.';
      case 'no-acquia-hosted':
        return 'Your site is no longer Acquia hosted.';
    }
  }

  /**
   * Test Acquia SPI UI.
   */
  public function testAcquiaSPIUI() {
    $this->drupalGet($this->status_report_url);
    $this->assertNoText($this->acquiaSPIStrings('spi-status-text'), 'SPI send option does not exist when site is not connected');
    // Connect site on key and id that will error.
    $edit_fields = array(
      'acquia_identifier' => $this->acqtest_error_id,
      'acquia_key' => $this->acqtest_error_key,
    );
    $submit_button = 'Connect';
    $this->drupalPostForm($this->credentials_path, $edit_fields, $submit_button);

    // If name and machine name are empty.
    $this->drupalGet($this->status_report_url);
    $this->assertText($this->acquiaSPIStrings('spi-not-sent'), 'SPI data was not sent');
    $this->assertText($this->acquiaSPIStrings('provide-site-name'), 'Provide a site name');

    $edit_fields = array(
      'name' => $this->acqtest_name,
      'machine_name' => $this->acqtest_machine_name,
    );
    $submit_button = 'Save configuration';
    $this->drupalPostForm($this->settings_path, $edit_fields, $submit_button);

    // Send SPI data.
    $this->drupalGet($this->status_report_url);
    $this->assertText($this->acquiaSPIStrings('spi-status-text'), 'SPI explanation text exists');
    $this->clickLink($this->acquiaSPIStrings('spi-send-text'));
    $this->assertNoText($this->acquiaSPIStrings('spi-data-sent'), 'SPI data was not sent');
    $this->assertText($this->acquiaSPIStrings('spi-data-sent-error'), 'Page says there was an error sending data');

    // Connect site on non-error key and id.
    $this->connectSite();
    $this->drupalGet($this->status_report_url);
    $this->clickLink($this->acquiaSPIStrings('spi-send-text'));
    $this->assertText($this->acquiaSPIStrings('spi-data-sent'), 'SPI data was sent');
    $this->assertNoText($this->acquiaSPIStrings('spi-not-sent'), 'SPI does not say "data has not been sent"');
    $this->assertText('This is the first connection from this site, it may take awhile for it to appear on the Acquia Network', 'First connection');

    // Machine name change.
    $edit_fields = array(
      'name' => $this->acqtest_name,
      'machine_name' => $this->acqtest_machine_name . '_change',
    );
    $submit_button = 'Save configuration';
    $this->drupalPostForm($this->settings_path, $edit_fields, $submit_button);
    $this->assertText('A change has been detected in your site environment. Please check the Acquia SPI status on your Status Report page for more information.', 'Change environment detected');
    $this->drupalGet($this->status_report_url);
    $this->clickLink($this->acquiaSPIStrings('confirm-action'));
    $this->assertText('Your site machine name changed from ' . $this->acqtest_machine_name . ' to ' . $this->acqtest_machine_name . '_change' . '.');

    // Block site.
    $edit_fields = array(
      'env_change_action' => 'block',
    );

    $submit_button = 'Save configuration';
    $this->drupalPostForm($this->environment_change_path, $edit_fields, $submit_button);
    $this->assertText($this->acquiaSPIStrings('block-site-message'), 'Block site');
    $this->clickLink($this->acquiaSPIStrings('unblock-site'));

    // Unblock site.
    $edit_fields = array(
      'env_change_action[unblock]' => TRUE,
    );

    $submit_button = 'Save configuration';
    $this->drupalPostForm($this->environment_change_path, $edit_fields, $submit_button);
    $this->assertText('Your site has been unblocked and is sending data to Acquia Cloud', 'Unblock site');
    $this->assertText($this->acquiaSPIStrings('spi-data-sent'), 'SPI data was sent');
    $this->assertNoText($this->acquiaSPIStrings('spi-not-sent'), 'SPI does not say "data has not been sent"');

    // Update machine name on existing site.
    $this->clickLink($this->acquiaSPIStrings('spi-send-text'));
    $this->assertText($this->acquiaSPIStrings('change-env-detected'), 'Change environment detected');
    $this->clickLink($this->acquiaSPIStrings('confirm-action'));

    $edit_fields = array(
      'env_change_action' => 'update',
    );

    $submit_button = 'Save configuration';
    $this->drupalPostForm($this->environment_change_path, $edit_fields, $submit_button);

    // Name change.
    $edit_fields = array(
      'name' => $this->acqtest_name . ' change',
      'machine_name' => $this->acqtest_machine_name . '_change',
    );
    $submit_button = 'Save configuration';
    $this->drupalPostForm($this->settings_path, $edit_fields, $submit_button);
    $this->drupalGet($this->status_report_url);
    $this->assertNoText($this->acquiaSPIStrings('spi-not-sent'), 'SPI does not say "data has not been sent"');
    $this->clickLink($this->acquiaSPIStrings('spi-send-text'));
    $this->assertText('Site name updated (from ' . $this->acqtest_name . ' to ' . $this->acqtest_name . ' change).', 'Change name');

    // Test acquia hosted site.
    $settings['_SERVER']['AH_SITE_NAME'] = (object) [
      'value' => 'acqtest_drupal',
      'required' => TRUE,
    ];
    $settings['_SERVER']['AH_SITE_ENVIRONMENT'] = (object) [
      'value' => 'dev',
      'required' => TRUE,
    ];

    $this->writeSettings($settings);
    sleep(10);

    $this->drupalGet($this->settings_path);
    $submit_button = 'Save configuration';
    $this->drupalPostForm($this->settings_path, array(), $submit_button);

    $spi = new spiControllerTest();
    $host_name = $spi->getAcquiaHostedName();
    $host_machine_name = $spi->getAcquiaHostedMachineName();

    $this->assertText('Site name updated (from ' . $this->acqtest_name . ' change to ' . $host_name . ').', 'Change name');
    $this->assertText('A change has been detected in your site environment. Please check the Acquia SPI status on your Status Report page for more information.', 'Change environment detected on settings page');

    $this->drupalGet($this->status_report_url);
    $this->assertText($this->acquiaSPIStrings('change-env-detected'), 'Change environment detected');
    $this->clickLink($this->acquiaSPIStrings('confirm-action'));
    $this->assertText($this->acquiaSPIStrings('acquia-hosted'), 'Site is now Acquia hosted');
    $this->assertText('Your site machine name changed from ' . $this->acqtest_machine_name . '_change to ' . $host_machine_name . '.', 'Change machine name');

    $edit_fields = array(
      'env_change_action' => 'update',
    );

    $submit_button = 'Save configuration';
    $this->drupalPostForm($this->environment_change_path, $edit_fields, $submit_button);

    // Test no acquia hosted site.
    $settings['_SERVER']['AH_SITE_NAME'] = (object) [
      'value' => NULL,
      'required' => FALSE,
    ];
    $settings['_SERVER']['AH_SITE_ENVIRONMENT'] = (object) [
      'value' => NULL,
      'required' => FALSE,
    ];

    $this->writeSettings($settings);
    sleep(10);

    $this->drupalGet($this->settings_path);

    $edit_fields = array(
      'name' => $this->acqtest_name,
      'machine_name' => $this->acqtest_machine_name,
    );

    $submit_button = 'Save configuration';
    $this->drupalPostForm($this->settings_path,  $edit_fields, $submit_button);
    $this->assertText('A change has been detected in your site environment. Please check the Acquia SPI status on your Status Report page for more information.', 'Change environment detected on settings page');
    $this->assertText('Site name updated (from ' . $host_name . ' to ' . $this->acqtest_name . ').', 'Change name');

    $this->drupalGet($this->status_report_url);
    $this->assertText($this->acquiaSPIStrings('change-env-detected'), 'Change environment detected');
    $this->clickLink($this->acquiaSPIStrings('confirm-action'));
    $this->assertText($this->acquiaSPIStrings('no-acquia-hosted'), 'Site is no longer Acquia hosted');
    $this->assertText('Your site machine name changed from ' . $host_machine_name . ' to ' . $this->acqtest_machine_name . '.', 'Change machine name');
  }

  /**
   * Test Acquia SPI data store.
   */
  public function testAcquiaSPIDataStore() {
    $data = array(
      'foo' => 'bar',
    );
    $spi = new spiControllerTest();
    $spi->dataStoreSet(array('testdata' => $data));
    $stored_data = $spi->dataStoreGet(array('testdata'));
    $diff = array_diff($stored_data['testdata'], $data);
    $this->assertTrue(empty($diff), 'Storage can store simple array');

    $this->drupalGet($this->base_url);
     //Platform data should have been written.
    $stored = $spi->dataStoreGet(array('platform'));
    $diff = array_diff(array_keys($stored['platform']), $this->platformKeys);
    $this->assertTrue(empty($diff), 'Platform element contains expected keys');
  }

  /**
   * Test Acquia SPI get.
   */
  public function testAcquiaSPIGet() {
    // Connect site on non-error key and id.
    $this->connectSite();

    $edit_fields = array(
      'name' => $this->acqtest_name,
      'machine_name' => $this->acqtest_machine_name,
    );
    $submit_button = 'Save configuration';
    $this->drupalPostForm($this->settings_path, $edit_fields, $submit_button);

    // Test spiControllerTest::get.
    $spi = new spiControllerTest();
    $spi_data = $spi->get();
    $valid = is_array($spi_data);
    $this->assertTrue($valid, 'spiController::get returns an array');
    if ($valid) {
      foreach ($this->spiDataKeys as $key) {
        if (!array_key_exists($key, $spi_data)) {
          $valid = FALSE;
          break;
        }
      }
      $this->assertTrue($valid, 'Array has expected keys');
      $private_key = \Drupal::service('private_key')->get();
      $this->assertEqual(sha1($private_key), $spi_data['site_key'], 'Site key is sha1 of Drupal private key');
      $this->assertTrue(!empty($spi_data['spi_data_version']), 'SPI data version is set');
      $vars = Json::decode($spi_data['system_vars']);
      $this->assertTrue(is_array($vars), 'SPI data system_vars is a JSON-encoded array');
      $this->assertTrue(isset($vars['user_admin_role']), 'user_admin_role included in SPI data');
      $this->assertTrue(!empty($spi_data['modules']), 'Modules is not empty');
      $modules = array('status', 'name', 'version', 'package', 'core', 'project', 'filename', 'module_data');
      $diff = array_diff(array_keys($spi_data['modules'][0]), $modules);
      $this->assertTrue(empty($diff), 'Module elements have expected keys');
      $diff = array_diff(array_keys($spi_data['platform']), $this->platformKeys);
      $this->assertTrue(empty($diff), 'Platform contains expected keys');
      $this->assertTrue(isset($spi_data['platform']['php_quantum']['SERVER']), 'Global server data included in SPI data');
      $this->assertTrue(isset($spi_data['platform']['php_quantum']['SERVER']['SERVER_SOFTWARE']), 'Server software data set within global server info');
      $this->assertTrue(isset($spi_data['platform']['mysql']['Select_scan']), 'Mysql info in platform contains an expected key');
      $this->assertTrue(isset($spi_data['file_hashes']['core/includes/database.inc']), 'File hashes array contains an expected key');
      $roles = Json::decode($spi_data['roles']);
      $this->assertTrue(is_array($roles), 'Roles is an array');
      $this->assertTrue(isset($roles) && array_key_exists('anonymous', $roles), 'Roles array contains anonymous user');
      $this->assertTrue(isset($spi_data['fileinfo']['core/scripts/drupal.sh']), 'Fileinfo contains an expected key');
      $this->assertTrue(strpos($spi_data['fileinfo']['core/scripts/drupal.sh'], 'mt') === 0, 'Fileinfo element begins with expected value');
    }
  }

  /**
   * Validate Acquia SPI data.
   */
  public function testNoObjectInSpiData() {
    // Connect site on non-error key and id.
    $this->connectSite();

    $edit_fields = array(
      'name' => $this->acqtest_name,
      'machine_name' => $this->acqtest_machine_name,
    );
    $submit_button = 'Save configuration';
    $this->drupalPostForm($this->settings_path, $edit_fields, $submit_button);

    $spi = new spiControllerTest();
    $spi_data = $spi->get();

    $this->assertFalse($this->is_contain_objects($spi_data), 'SPI data does not contain PHP objects.');
  }

  /**
   * Helper function determines whether given array contains PHP object.
   */
  public function is_contain_objects($arr) {
    foreach ($arr as $key => $item) {
      if (is_object($item)) {
        return TRUE;
      }
      if (is_array($item) && $this->is_contain_objects($item)) {
        return TRUE;
      }
    }
  }

  /**
   * Test Acquia SPI send.
   */
  public function testAcquiaSPISend() {
    // Connect site on invalid credentials.
    $edit_fields = array(
      'acquia_identifier' => $this->acqtest_error_id,
      'acquia_key' => $this->acqtest_error_key,
    );
    $submit_button = 'Connect';
    $this->drupalPostForm($this->credentials_path, $edit_fields, $submit_button);

    // Attempt to send something.
    $client = \Drupal::service('acquia_connector.client');
    // Connect site on valid credentials.
    $this->connectSite();

    // Check that result is an array.
    $spi = new spiControllerTest();
    $spi_data = $spi->get();
    unset($spi_data['spi_def_update']);
    $result = $client->sendNspi($this->acqtest_id, $this->acqtest_key, $spi_data);
    $this->assertTrue(is_array($result), 'SPI update result is an array');

    // Trigger a validation error on response.
    $spi_data['test_validation_error'] = TRUE;
    unset($spi_data['spi_def_update']);
    $result = $client->sendNspi($this->acqtest_id, $this->acqtest_key, $spi_data);
    $this->assertFalse($result, 'SPI result is false if validation error.');
    unset($spi_data['test_validation_error']);

    // Trigger a SPI definition update response.
    $spi_data['spi_def_update'] = TRUE;
    $result = $client->sendNspi($this->acqtest_id, $this->acqtest_key, $spi_data);
    $this->assertTrue(!empty($result['body']['update_spi_definition']), 'SPI result array has expected "update_spi_definition" key.');
  }

  /**
   * Test Acquia SPI update response.
   */
  public function testAcquiaSPIUpdateResponse() {
    $def_timestamp = \Drupal::config('acquia_connector.settings')->get('spi.def_timestamp');
    $this->assertEqual($def_timestamp, 0, 'SPI definition has not been called before');
    $def_vars = \Drupal::config('acquia_connector.settings')->get('spi.def_vars');
    $this->assertTrue(empty($def_vars), 'SPI definition variables is empty');
    $waived_vars = \Drupal::config('acquia_connector.settings')->get('spi.def_waived_vars');
    $this->assertTrue(empty($waived_vars), 'SPI definition waived variables is empty');
    // Connect site on non-error key and id.
    $this->connectSite();

    $edit_fields = array(
      'name' => $this->acqtest_name,
      'machine_name' => $this->acqtest_machine_name,
    );
    $submit_button = 'Save configuration';
    $this->drupalPostForm($this->settings_path, $edit_fields, $submit_button);

    // Send SPI data.
    $this->drupalGet($this->status_report_url);
    $this->clickLink($this->acquiaSPIStrings('spi-send-text'));
    $this->assertText($this->acquiaSPIStrings('spi-data-sent'), 'SPI data was sent');
    $this->assertNoText($this->acquiaSPIStrings('spi-not-sent'), 'SPI does not say "data has not been sent"');

    $def_timestamp = \Drupal::config('acquia_connector.settings')->get('spi.def_timestamp');
    $this->assertNotEqual($def_timestamp, 0, 'SPI definition timestamp set');
    $def_vars = \Drupal::config('acquia_connector.settings')->get('spi.def_vars');
    $this->assertTrue(!empty($def_vars), 'SPI definition variable set');
    \Drupal::configFactory()->getEditable('acquia_connector.settings')->set('spi.def_waived_vars', array('user_admin_role'))->save();
    // Test that new variables are in SPI data.
    $spi = new spiControllerTest();
    $spi_data = $spi->get();
    $vars = Json::decode($spi_data['system_vars']);
    $this->assertTrue(!empty($vars['file_temporary_path']), 'New variables included in SPI data');
    $this->assertTrue(!isset($vars['user_admin_role']), 'user_admin_role not included in SPI data');
  }

  /**
   * Test Acquia SPI messages.
   */
  public function testAcquiaSPIMessages() {
    $this->connectSite();

    $edit_fields = array(
      'name' => $this->acqtest_name,
      'machine_name' => $this->acqtest_machine_name,
    );
    $submit_button = 'Save configuration';
    $this->drupalPostForm($this->settings_path, $edit_fields, $submit_button);
    // First connection.
    $this->assertText('This is the first connection from this site, it may take awhile for it to appear on the Acquia Network.', 'First connection');

    $spi = new spiControllerTest();
    $response = $spi->sendFullSpi();

    $this->assertTrue(!isset($response['body']['nspi_messages']), 'No NSPI messages when send_method not set');
    $method = $this->randomString();
    $response = $spi->sendFullSpi($method);
    $this->assertIdentical($response['body']['nspi_messages'][0], $method, 'NSPI messages when send_method is set');

    $this->drupalGet($this->status_report_url);
    $this->clickLink($this->acquiaSPIStrings('spi-send-text'));
    $this->assertText(ACQUIA_SPI_METHOD_CALLBACK, 'NSPI messages printed on status page');
  }

  /**
   * Test Acquia SPI set variables.
   */
  public function testAcquiaSPISetVariables() {
    // Connect site on non-error key and id.
    $this->connectSite();

    $edit_fields = array(
      'name' => $this->acqtest_name,
      'machine_name' => $this->acqtest_machine_name,
    );
    $submit_button = 'Save configuration';
    $this->drupalPostForm($this->settings_path, $edit_fields, $submit_button);

    $spi = new spiControllerTest();
    $spi_data = $spi->get();
    $vars = Json::decode($spi_data['system_vars']);
    $this->assertTrue(empty($vars['acquia_spi_saved_variables']['variables']), 'Have not saved any variables');
    // Set error reporting so variable is saved.
    $edit = array(
      'error_level' => 'verbose',
    );
    $this->drupalPostForm('admin/config/development/logging', $edit, 'Save configuration');

    // Turn off error reporting.
    $set_variables = array('error_level' => 'hide');
    $variables = new VariablesControllerTest();
    $variables->setVariables($set_variables);

    $new = \Drupal::config('system.logging')->get('error_level');
    $this->assertTrue($new === 'hide', 'Set error reporting to log only');
    $vars = Json::decode($variables->getVariablesData());
    $this->assertTrue(in_array('error_level', $vars['acquia_spi_saved_variables']['variables']), 'SPI data reports error level was saved');
    $this->assertTrue(isset($vars['acquia_spi_saved_variables']['time']), 'Set time for saved variables');

    // Attemp to set variable that is not whitelisted.
    $current = \Drupal::config('system.site')->get('name');
    $set_variables = array('site_name' => 0);
    $variables->setVariables($set_variables);
    $after = \Drupal::config('system.site')->get('name');
    $this->assertIdentical($current, $after, 'Non-whitelisted variable cannot be automatically set');
    $vars = Json::decode($variables->getVariablesData());
    $this->assertFalse(in_array('site_name', $vars['acquia_spi_saved_variables']['variables']), 'SPI data does not include anything about trying to save clean url');

    // Test override of approved variable list.
    \Drupal::configFactory()->getEditable('acquia_connector.settings')->set('spi.set_variables_override', FALSE)->save();
    $set_variables = array('acquia_spi_set_variables_automatic' => 'test_variable');
    $variables->setVariables($set_variables);
    $vars = Json::decode($variables->getVariablesData());
    $this->assertFalse(isset($vars['test_variable']), 'Using default list of approved list of variables');
    \Drupal::configFactory()->getEditable('acquia_connector.settings')->set('spi.set_variables_override', TRUE)->save();
    $set_variables = array('acquia_spi_set_variables_automatic' => 'test_variable');
    $variables->setVariables($set_variables);
    $vars = Json::decode($variables->getVariablesData());
    $this->assertIdentical($vars['acquia_spi_set_variables_automatic'], 'test_variable', 'Altered approved list of variables that can be set');
  }

  /**
   * Helper function connects to valid subscription.
   */
  protected function connectSite() {
    $edit_fields = array(
      'acquia_identifier' => $this->acqtest_id,
      'acquia_key' => $this->acqtest_key,
    );
    $submit_button = 'Connect';
    $this->drupalPostForm($this->credentials_path, $edit_fields, $submit_button);
  }
}

/**
 * Class spiControllerTest
 * @package Drupal\acquia_connector\Tests
 */
class spiControllerTest extends SpiController {
  protected $client;

  public function __construct() {
    $client = \Drupal::service('acquia_connector.client');
    $this->client = $client;
  }

  /**
   * Gather site profile information about this site.
   *
   * @param string $method
   *   Optional identifier for the method initiating request.
   *   Values could be 'cron' or 'menu callback' or 'drush'.
   *
   * @return array
   *   An associative array keyed by types of information.
   */
  public function get($method = '') {
    return parent::get($method);
  }

  /**
   * Put SPI data in local storage.
   *
   * @param array $data Keyed array of data to store.
   * @param int $expire Expire time or null to use default of 1 day.
   */
  public function dataStoreSet($data, $expire = NULL) {
    parent::dataStoreSet($data, $expire);
  }

  /**
   * Get SPI data out of local storage.
   *
   * @param array Array of keys to extract data for.
   *
   * @return array Stored data or false if no data is retrievable from storage.
   */
  public function dataStoreGet($keys) {
    return parent::dataStoreGet($keys);
  }

  /**
   * Gather full SPI data and send to Acquia Network.
   *
   * @param string $method Optional identifier for the method initiating request.
   *   Values could be 'cron' or 'menu callback' or 'drush'.
   * @return mixed FALSE if data not sent else NSPI result array
   */
  public function sendFullSpi($method = '') {
    return parent::sendFullSpi($method);
  }

  /**
   * Generate the machine name for acquia hosted sites.
   *
   * @return string The suggested Acquia Hosted machine name.
   */
  public function getAcquiaHostedMachineName() {
    return parent::getAcquiaHostedMachineName();
  }

  /**
   * Generate the name for acquia hosted sites.
   *
   * @return string The suggested Acquia Hosted name.
   */
  public function getAcquiaHostedName() {
    return parent::getAcquiaHostedName();
  }
}

/**
 * Class VariablesControllerTest
 * @package Drupal\acquia_connector\Tests
 */
class VariablesControllerTest extends VariablesController {
  /**
   * @param array $set_variables
   * @return NULL|void
   */
  public function setVariables($set_variables) {
    parent::setVariables($set_variables);
  }

  /**
   * @return array
   */
  public function getVariablesData() {
    return parent::getVariablesData();
  }
}
