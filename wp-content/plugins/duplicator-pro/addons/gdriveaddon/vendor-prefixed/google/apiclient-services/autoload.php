<?php

namespace VendorDuplicator;

// For older (pre-2.7.2) verions of google/apiclient
if (\file_exists(__DIR__ . '/../apiclient/src/Google/Client.php') && !\class_exists('VendorDuplicator\Google_Client', \false)) {
    require_once __DIR__ . '/../apiclient/src/Google/Client.php';
    if (\defined('VendorDuplicator\\Google_Client::LIBVER') && \version_compare(Google_Client::LIBVER, '2.7.2', '<=')) {
        $servicesClassMap = ['VendorDuplicator\Google\Client' => 'VendorDuplicator\\Google_Client', 'VendorDuplicator\Google\Service' => 'VendorDuplicator\\Google_Service', 'VendorDuplicator\Google\Service\Resource' => 'VendorDuplicator\\Google_Service_Resource', 'VendorDuplicator\Google\Model' => 'VendorDuplicator\\Google_Model', 'VendorDuplicator\Google\Collection' => 'VendorDuplicator\\Google_Collection'];
        foreach ($servicesClassMap as $alias => $class) {
            \class_alias($class, $alias);
        }
    }
}
\spl_autoload_register(function ($class) {
    if (0 === \strpos($class, 'VendorDuplicator\\Google_Service_')) {
        // Autoload the new class, which will also create an alias for the
        // old class by changing underscores to namespaces:
        //     Google_Service_Speech_Resource_Operations
        //      => Google\Service\Speech\Resource\Operations
        $classExists = \class_exists($newClass = \str_replace('_', '\\', $class));
        if ($classExists) {
            return \true;
        }
    }
}, \true, \true);
