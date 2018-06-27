<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

require 'vendor/autoload.php';

use CloudRail\Settings;
use CloudRail\Service\Dropbox;

//Setting up CloudRail key
Settings::$licenseKey = "[CLOUDRAIL_KEY]";


$app = new \Slim\App;

$app->get('/hello/{name}', function (Request $request, Response $response, array $args) {
    $name = $args['name'];
    $response->getBody()->write("Hello, $name");

    return $response;
});

$app->get('/', function (Request $request, Response $response, array $args) {
    $name = $args['name'];
    $response->getBody()->write("Please use the endpoint /auth/Dropbox to test the authentication");
    return $response;
});

$app->get('/auth/Dropbox', function (Request $request, Response $response, array $args) {

  $GLOBALS['lastServiceName'] = 'Dropbox';

  // First redirect receiver will pass the auth URL as a parameter,
  // so you should retrieve it and use when needed
  $extractedURL = "";
  $firstRedirectReceiver = function($startUrl) use (&$extractedURL){
    $extractedURL = $startUrl;
  };

  //Credentials for Dropbox
  $clientKey = "[CLIENT_KEY]";
  $clientSecret = "[CLIENT_SECRET]";
  $redirectUrl = "http://localhost:8080/auth";
  $state = "Dropbox"; // This should be used to differ the requesteeers

  //Instantiate the service object
  $dropbox = new Dropbox($firstRedirectReceiver, $clientKey,$clientSecret,$redirectUrl,$state);

  try {
    $dropbox->login();
  } catch (Exception $e) {
    echo 'Exception Captured: ',  $e->getMessage(), "\n";
  }
  return $response->withRedirect($extractedURL, 301);
});

//URL that the service will return
$app->get('/auth', function (Request $request, Response $response, array $args) {

  if (array_key_exists("state",$request->getQueryParams())) {
    $serviceName = $request->getQueryParams()['state'];
  }

  //This redirect SHOULD RETURN the Array with the key value that matches
  $secondRedirectReceiver = function($startUrl) use (&$request) {
    return ["url"=>strval($request->getUri())];
  };

  //Credentials for Dropbox
  $clientKey = "[CLIENT_KEY]";
  $clientSecret = "[CLIENT_SECRET]";
  $redirectUrl = "http://localhost:8080/auth";
  $state = "Dropbox"; // This should be used to differ the requesteeers

  $dropbox = new Dropbox($secondRedirectReceiver, $clientKey,$clientSecret,$redirectUrl,$state);

  try {
    $dropbox->login();
    $savedState = $dropbox->saveAsString();
  } catch (Exception $e) {
    echo 'Exception: ',  $e->getMessage(), "\n";
  }
    $response->getBody()->write("SavedState:, $savedState");
    return $response;
});

$app->run();
