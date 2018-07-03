<?php
require __DIR__ . '/Burgomaster.php';

#STEP 0 - Cleaning product dir and setting initial variables
$productDir = __DIR__ . "/product";
$output = shell_exec("rm -r $productDir/*");

//Getting lib version
$jsonPath = __DIR__ . "/../composer.json";
$jsonString = file_get_contents($jsonPath);
$decodedComposer = json_decode($jsonString,true,50);
$libraryVersion = $decodedComposer["version"];
// Creating staging directory at library-php/build/staging.
$stageDirectory = __DIR__ . '/staging';
// The root of the project is up one directory from the current directory.
$projectRoot = __DIR__ . '/../';


#Basic configurations
$packager = new \Burgomaster($stageDirectory, $projectRoot);

#Copying the source files (again relative to project root)
$packager->recursiveCopy('src', 'CloudRail', ['php']);

#Map all the classes that the Phar will let available

$explicitFiles = [];//['GuzzleHttp/functions.php'];
$packager->createAutoloader($explicitFiles);

//Writing extra line to PHAR autoloader
$h = fopen($stageDirectory . "/autoloader.php", 'a');

fwrite($h, <<<EOT
\$GLOBALS['cloudrail-php-version'] = '$libraryVersion';
EOT
);

#Generate the Phar - WARNING BE CAREFUL WHEN CHANGING NAMES FROM HERE
$targetDirectory = $productDir;
$pharPath = $targetDirectory . '/CloudRail.phar';
// Create a phar file from the staging directory at a specific location
$packager->createPhar($pharPath);
