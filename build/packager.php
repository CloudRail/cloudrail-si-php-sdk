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


# STEP 1 - Basic configurations
$packager = new \Burgomaster($stageDirectory, $projectRoot);

# STEP 2 - Copying basic files
// Copy basic files to the stage directory. Note that we have chdir'd onto
// the $projectRoot directory, so use relative paths.
// $basicFiles = ['lib/guzzle.phar'/*'README.md', 'LICENSE'*/];
// foreach ($basicFiles as $file) {
//     $packager->deepCopy($file, $file);
// }

# STEP 3 - Copying the source files (again relative to project root)
// Copy each dependency to the staging directory. Copy *.php files.
//$packager->recursiveCopy('src', '', ['php']);
$packager->recursiveCopy('src', 'CloudRail', ['php']);

# STEP 4 - Map all the classes that the Phar will let available
// Create the classmap autoloader, and instruct the autoloader to
// automatically require the 'GuzzleHttp/functions.php' script.
$explicitFiles = [];//['GuzzleHttp/functions.php'];
$packager->createAutoloader($explicitFiles);

//Writing extra line to PHAR autoloader
$h = fopen($stageDirectory . "/autoloader.php", 'a');

fwrite($h, <<<EOT
\$GLOBALS['cloudrail-php-version'] = '$libraryVersion';
EOT
);

# STEP 5 - Generate the Phar - WARNING BE CAREFUL WHEN CHANGING NAMES FROM HERE
$targetDirectory = $productDir;
$pharPath = $targetDirectory . '/CloudRail.phar';
// Create a phar file from the staging directory at a specific location
$packager->createPhar($pharPath);

//Generating DEV autoloader
$packager->createAutoloaderDev($explicitFiles,"autoloaderDev.php", __DIR__ . "/..");

// Create a zip file from the product directory
$unconpressedProduct = $productDir . "/CloudRail-php";
mkdir($unconpressedProduct);
$output = shell_exec("mv  $pharPath $unconpressedProduct");

$libPath = $unconpressedProduct . "/lib";
if (!file_exists($libPath)){
    mkdir($libPath);
}
$dependecies = __DIR__ . "/../lib";
$output = shell_exec("cp -r $dependecies $unconpressedProduct");
$output = shell_exec("cd $productDir && zip -r3 ./CloudRail-php.zip ./CloudRail-php");