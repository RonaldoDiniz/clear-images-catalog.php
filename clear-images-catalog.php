<?php
/**
 * Cleanup images from Magento
 */
require 'app/Mage.php';
if (!Mage::isInstalled()) {
    echo "Application is not installed yet, please complete install wizard first.";
    exit;
}
Mage::app('admin')->setUseSessionInUrl(false);
umask(0);
$connection = Mage::getSingleton('core/resource')
    ->getConnection('core_write');
$sql = "select distinct "
    . "cp.entity_id, "
    . "cpg.value_id, "
    . "cpv.value as default_value, "
    . "cpg.value "
    . "from catalog_product_entity as cp "
    . "join catalog_product_entity_varchar as cpv on cp.entity_id = cpv.entity_id "
    . "join catalog_product_entity_media_gallery as cpg on cp.entity_id = cpg.entity_id "
    . "where "
//            . "1 = 2 "
//        . "and "
    . "cpv.attribute_id in(85, 86, 87) "
    . "and "
    . "cpv.value != cpg.value;";
$results = $connection->fetchAll($sql);
$media = 'media/catalog/product';
$lastEntityId = null;
$origSums = array();
foreach ($results as $row) {
    if ($row['entity_id'] != $lastEntityId) {
        $lastEntityId = $row['entity_id'];
        $origSums = array();
    }
    $origFile = $media . $row['default_value'];
    if (!file_exists($origFile)) {
        continue;
    }
    $file = $media . $row['value'];
    if (file_exists($file)) {
        if (!isset($origSums[$origFile])) {
            $origSums[$origFile] = md5_file($origFile);
        }
        $sum = md5_file($file);
        if (!in_array($sum, $origSums)) {
            $origSums[$file] = $sum;
        } else {
            echo 'Delete image ' . $file . ' (#' . $row['entity_id'] . ')' . PHP_EOL;
            unlink($file);
        }
    }
    if (!file_exists($file)) {
        echo 'Delete record for ' . $file . ' (#' . $row['entity_id'] . ')' . PHP_EOL;
        $deleteSql = 'delete from catalog_product_entity_media_gallery where value_id = ' . $row['value_id'] . ';';
        $connection->query($deleteSql);
    }
}
// Find files on filesystem which aren't listed in the database
$files = glob($media . '/[A-z0-9]/*/*');
foreach ($files as $file) {
    $searchFile = str_replace($media, '', $file);
    // Lookup
    $mediaSql = "select count(*) as records from catalog_product_entity_media_gallery where value = '{$searchFile}'";
    $mediaCount = $connection->fetchOne($mediaSql);
    if ($mediaCount < 1) {
        echo 'Delete image ' . $file . PHP_EOL;
        unlink($file);
    }
}
