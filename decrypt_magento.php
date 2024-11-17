#!/usr/bin/env php
<?php
/**
 * Magento 2.4.5 Decrypt core_config_data values using Magento's framework
 *
 * This script can be placed in /usr/sbin or any directory in your PATH.
 * When run, it will attempt to locate the Magento installation in the current
 * working directory and decrypt all encrypted values in the core_config_data table.
 *
 * Usage:
 * 1. Place this script in /usr/sbin or any directory in your PATH.
 * 2. Ensure it has execute permissions: chmod +x decrypt_core_config_data.php
 * 3. Run the script from any directory containing a Magento installation: decrypt_core_config_data.php
 *
 * @author: Sean Breeden
 * @website: www.seanbreeden.com
 */

use Magento\Framework\App\Bootstrap;
use Magento\Framework\Encryption\EncryptorInterface;
function getMagentoRootPath() {
    $currentDir = getcwd();
    $previousDir = '';
    while ($currentDir && $currentDir !== $previousDir) {
        if (file_exists($currentDir . '/app/bootstrap.php')) {
            return $currentDir;
        }
        $previousDir = $currentDir;
        $currentDir = dirname($currentDir);
    }
    return false;
}
$magentoRootPath = getMagentoRootPath();
if (!$magentoRootPath) {
    die("Magento installation not found in the current directory or any parent directories.\n");
}
chdir($magentoRootPath);
require $magentoRootPath . '/app/bootstrap.php';
$params = $_SERVER;
$bootstrap = Bootstrap::create(BP, $params);
$objectManager = $bootstrap->getObjectManager();
$appState = $objectManager->get(\Magento\Framework\App\State::class);
try {
    $appState->setAreaCode(\Magento\Framework\App\Area::AREA_GLOBAL);
} catch (\Magento\Framework\Exception\LocalizedException $e) {
    die('Error: ' . $e->getMessage)
}
$resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
$connection = $resource->getConnection();
$tableName = $resource->getTableName('core_config_data');
$encryptor = $objectManager->get(EncryptorInterface::class);
$sql = "SELECT config_id, path, value FROM $tableName";
$results = $connection->fetchAll($sql);
if (!$results) {
    die("No data found in core_config_data!" . PHP_EOL);
}
foreach ($results as $row) {
    $configId = $row['config_id'];
    $path = $row['path'];
    $value = $row['value'];
    $decryptedValue = $value;
    $isEncrypted = false;
    if (isEncrypted($value)) {
        $isEncrypted = true;
        try {
            $decryptedValue = $encryptor->decrypt($value);
        } catch (\Exception $e) {
            $decryptedValue = "Error decrypting: " . $e->getMessage();
        }
    }
    echo "Config ID: $configId" . PHP_EOL;
    echo "Path: $path" . PHP_EOL;
    echo "Value: " . ($isEncrypted ? "(Encrypted)" : "(Not Encrypted)") . PHP_EOL;
    echo "Decrypted Value: $decryptedValue" . PHP_EOL . PHP_EOL;
}
function isEncrypted($value) {
    // Check for Magento's encryption prefixes (e.g., '0:', '1:', '2:', etc.)
    return preg_match('/^\d+:.*$/', $value);
}
