![Packagist](https://img.shields.io/packagist/v/CloudRail/library-php.svg)
<p align="center">
  <img width="200px" src="http://cloudrail.github.io/img/cloudrail_logo_github.png"/>
</p>

# CloudRail SI for php7

Integrate Multiple Services With Just One API

<p align="center">
  <img width="300px" src="http://cloudrail.github.io/img/cloudrail_si_github.png"/>
</p>

CloudRail is an API integration solution which abstracts multiple APIs from different providers into a single and universal interface.
<p align="center">
  <img width="800px" src="http://cloudrail.github.io/img/available_interfaces_v3.png"/>
</p>
---
---

Learn more about CloudRail at https://cloudrail.com

Full documentation can be found [here](https://cloudrail.com/integrations)

---
---

With CloudRail, you can easily integrate external APIs into your application. 
CloudRail provides abstracted interfaces that take several services and then exposes a developer-friendly API that uses common functions between all providers. 
This means that, for example, upload() works in exactly the same way for Dropbox as it does for Google Drive, OneDrive, and other Cloud Storage Services, and getEmail() works similarly the same way across all social networks.

## Composer

```
php7 composer.phar require cloudrail/library-php
```

After installing, you need to require Composer's autoloader:

```php
require 'vendor/autoload.php';
```

## Current Interfaces
| Interface              | Included Services                        |
| ---------------------- | ---------------------------------------- |
| Cloud Storage          | Dropbox, Google Drive, OneDrive, OneDrive Business, Box, Egnyte |
| Business Cloud Storage | Amazon S3, Microsoft Azure, Google Cloud Services, Rackspace, Backblaze |
| Social Profile         | Facebook, GitHub, Google+, LinkedIn, Slack, Twitter, Windows Live, Yahoo, Instagram, Heroku |
| Social Interaction     | Facebook, Facebook Page, Twitter         |
| Payment                | PayPal, Stripe                           |
| Email                  | Maljet, Sendgrid                         |
| SMS                    | Twilio, Nexmo                            |
| Point of Interest      | Google Places, Foursquare, Yelp          |
| Video                  | YouTube, Twitch, Vimeo                   |
| Messaging              | Facebook Messenger, Telegram, Line, Viber |
---
### Cloud Storage Interface:

* Dropbox
* Box
* Google Drive
* Microsoft OneDrive
* Microsoft OneDrive for Business
* Egnyte

#### Features:

* Download files from Cloud Storage.
* Upload files to Cloud Storage.
* Get Meta Data of files, folders and perform all standard operations (copy, move, etc) with them.
* Retrieve user and quota information
* Generate share links for files and folders
* Get thumbnails for images

[Full Documentation](https://cloudrail.com/integrations/interfaces/CloudStorage;platformId=php)
#### Code Example:

```php
require_once __DIR__ . '/vendor/autoload.php';
Settings::$licenseKey = "[CloudRail License Key]";

//$service = new Dropbox($redirectReceiver,"CLIENTID","[CLIENT_SECRET]", "[REDIRECT_URI]","[STATE]");
//$service = new Box($redirectReceiver,"CLIENTID","[CLIENT_SECRET]", "[REDIRECT_URI]","[STATE]");
//$service = new PCloud($redirectReceiver,"CLIENTID","[CLIENT_SECRET]", "[REDIRECT_URI]","[STATE]");
//$service = new Egnyte($redirectReceiver,"[DOMAIN]","CLIENTID","[CLIENT_SECRET]", "[REDIRECT_URI]","[STATE]");
//$service = new GoogleDrive($redirectReceiver,"CLIENTID","[CLIENT_SECRET]", "[REDIRECT_URI]","[STATE]");
$service = new OneDrive($redirectReceiver,"CLIENTID","[CLIENT_SECRET]", "[REDIRECT_URI]","[STATE]");

$stream = $service->download("/firstFolder/image.jpg");
```
---
### Business/Bucket Cloud Storage Interface:

* Amazon Web Services S3
* Microsoft Azure
* Google Cloud Services
* Rackspace
* Backblaze

#### Features

* Create, delete and list buckets
* Upload files
* Download files
* List files in a bucket and delete files
* Get file metadata (last modified, size, etc.)

[Full Documentation](https://cloudrail.com/integrations/interfaces/BusinessCloudStorage;platformId=php)
#### Code Example

```php
require_once __DIR__ . '/vendor/autoload.php';
Settings::$licenseKey = "[CloudRail License Key]";

//$service = new MicrosoftAzure("[ACCOUNT_NAME]","[ACCESS_KEY]");
//$service = new Backblaze("[ACCOUNT_ID]","[APP_KEY]");
//$service = new Rackspace("[USERNAME]","[API_KEY]","[REGION]");
//$service = new AmazonS3("[ACCOUNT_KEY]","[SECRET_KEY]","[REGION]");
$service = new GoogleCloudPlatform("[CLIENT_EMAIL]","[PRIVATE_KEY]","[PROJECT_ID]");

$bucket = $service->createBucket("[BUCKET_NAME]");
```
---
### Social Profile Interface:

* Facebook
* Github
* Google Plus
* LinkedIn
* Slack
* Twitter
* Windows Live
* Yahoo
* Instagram
* Heroku

#### Features

* Get profile information, including full names, emails, genders, date of birth, and locales.
* Retrieve profile pictures.
* Login using the Social Network.

[Full Documentation](https://cloudrail.com/integrations/interfaces/Profile;platformId=php)
#### Code Example:

```php
require_once __DIR__ . '/vendor/autoload.php';
Settings::$licenseKey = "[CloudRail License Key]";

//$service = new GooglePlus($redirectReceiver,"[ACCOUNT_NAME]","[ACCESS_KEY]", "[REDIRECT_URL]","[STATE]");
//$service = new Slack($redirectReceiver,"[ACCOUNT_NAME]","[ACCESS_KEY]", "[REDIRECT_URL]","[STATE]");
//$service = new GitHub($redirectReceiver,"[ACCOUNT_NAME]","[ACCESS_KEY]", "[REDIRECT_URL]","[STATE]");
//$service = new Twitter($redirectReceiver,"[ACCOUNT_NAME]","[ACCESS_KEY]", "[REDIRECT_URL]");
//$service = new Instagram($redirectReceiver,"[ACCOUNT_NAME]","[ACCESS_KEY]", "[REDIRECT_URL]","[STATE]");
$service = new Facebook($redirectReceiver,"[ACCOUNT_NAME]","[ACCESS_KEY]", "[REDIRECT_URL]","[STATE]");

$email = $service->getEmail(]);
$fullName = $service->getFullName();
$pictureURL = $service->getPictureURL();
```
---
### Social Interaction Interface:

* Facebook (interacts with a user)
* Facebook Pages (interacts with a page)
* Twitter

#### Features

* Get the IDs of a user's friends/followers. Works well with the Profile interface's "login with" to check if two of your users are friends on a platform
* Post messages to a wall/stream
* Post pictures and videos to a wall/stream

[Full Documentation](https://cloudrail.com/integrations/interfaces/Social;platformId=php)
#### Code Example:

```php
require_once __DIR__ . '/vendor/autoload.php';
Settings::$licenseKey = "[CloudRail License Key]";

$redirectReceiver = function(){};
//$service = new Twitter($redirectReceiver,"[ACCOUNT_NAME]","[ACCESS_KEY]", "[REDIRECT_URL]");
//$service = new FacebookPage($redirectReceiver,"[ACCOUNT_NAME]","[ACCESS_KEY]", "[REDIRECT_URL]","[REDIRECT_URL]","[STATE]");
$service = new Facebook($redirectReceiver,"[ACCOUNT_NAME]","[ACCESS_KEY]", "[REDIRECT_URL]","[STATE]");

$email = $service->postUpdate("Hey there, im using CloudRail SDK");
```
---
### Payment Interface:

* PayPal
* Stripe

#### Features

* Perform charges
* Refund previously made charges
* Manage subscriptions

[Full Documentation](https://cloudrail.com/integrations/interfaces/Payment;platformId=php)
#### Code Example

```php
require_once __DIR__ . '/vendor/autoload.php';
Settings::$licenseKey = "[CloudRail License Key]";

$redirectReceiver = function(){};
$service = new Stripe($redirectReceiver,"[SECRET_KEY]");
$service = new PayPal($redirectReceiver,"[SANDBOX?]","[ACCESS_KEY]", "[CLIENT_SECRET]");

$creditCard = new CreditCard(null, 6, 2021, "xxxxxxxxxxxxxxxx", "visa", "<FirstName>", "<LastName>", null);
$payment = $service->createCharge(500,"USD",$creditCard);
```
---
### Email Interface:

* Mailjet
* Sendgrid

#### Features

* Send Email

[Full Documentation](https://cloudrail.com/integrations/interfaces/Email;platformId=php)

#### Code Example

```php
require_once __DIR__ . '/vendor/autoload.php';
Settings::$licenseKey = "[CloudRail License Key]";

//$service = new SendGrid("[API_KEY]");
//$service = new MailJet("[CLIENT_ID]","[CLIENT_SECRET");
$service = new GMail("[CLIENT_ID]","[CLIENT_SECRET]","[REDIRECT_URL]", "[STATE]");

$service->sendEmail("info@cloudrail.com", "CloudRail", ["foo@bar.com", "bar@foo.com"], "Welcome", "Hello from CloudRail", null, null, null,[]);
```
---
### SMS Interface:

* Twilio
* Nexmo

#### Features

* Send SMS

[Full Documentation](https://cloudrail.com/integrations/interfaces/SMS;platformId=php)

#### Code Example

```php
require_once __DIR__ . '/vendor/autoload.php';
Settings::$licenseKey = "[CloudRail License Key]";

$service = new Twilio("[ACCOUNT_ID]","[AUTH_TOKEN]");
$service = new Twizo("[ACCOUNT_KEY]");
$service = new Nexmo("[CLIENT_ID]","[CLIENT_SECRET]");

$service->sendSMS("CloudRail", "+4912345678", "Hello from CloudRail");
```
---
### Points of Interest Interface:

* Google Places
* Foursquare
* Yelp

#### Features

* Get a list of POIs nearby
* Filter by categories or search term

[Full Documentation](https://cloudrail.com/integrations/interfaces/PointsOfInterests;platformId=php)
#### Code Example

``` php
require_once __DIR__ . '/vendor/autoload.php';
Settings::$licenseKey = "[CloudRail License Key]";

//$service = new Yelp( "[API_KEY]");
//$service = new GooglePlaces( "[API_KEY]");
$service = new Foursquare( "[CLIENT_ID]","[CLIENT_SECRET]");
$retrievedPOIs = $service->getNearbyPOIs( -15.7662,-47.8829,3000,"cafe",[]);

var_dump($retrievedPOIs);
```
---
### Video Interface:

* YouTube
* Twitch
* Vimeo

#### Features

* Get channel metadata
* List videos for a channel
* Get video metadata
* Search for videos
* Upload a video

[Full Documentation](https://cloudrail.com/integrations/interfaces/Video;platformId=php)
#### Code Example

``` php
require_once __DIR__ . '/vendor/autoload.php';
Settings::$licenseKey = "[CloudRail License Key]";

//$service = new Twitch($redirectReceiver,"[CLIENT_ID]", "[CLIENT_SECRET]", "[REDIRECT_URI]", "[STATE]");
//$service = new Vimeo($redirectReceiver, "[CLIENT_ID]","[CLIENT_SECRET]", "[REDIRECT_URI]","[STATE]");
$service = new YouTube($redirectReceiver,"[CLIENT_ID]", "[CLIENT_SECRET]", "[REDIRECT_URI]", "[STATE]");

$service->searchVideos("CloudRail Tutorial", 0, 50);
```
---
### Messaging Interface:

* Facebook Messenger
* Telegram
* Line
* Viber

#### Features

* Send text messages
* Send files, images, videos and audios
* Parse a message received on your webhook
* Download the content of an attachment sent to your webhook

[Full Documentation](https://cloudrail.com/integrations/interfaces/Messaging;platformId=php)
#### Code Example

``` php
 require_once __DIR__ . '/vendor/autoload.php';
Settings::$licenseKey = "[CloudRail License Key]";

$service = new Telegram($redirectReceiver,"[BOT_TOKEN]", "[WEBHOOK_URL]");
$service = new Line($redirectReceiver,"[BOT_TOKEN]");
$service = new Viber($redirectReceiver,"[BOT_TOKEN]", "[WEBHOOK_URL]", "[BOT_NAME]");
$service = new FacebookMessenger($redirectReceiver,"[BOT_TOKEN]");

$service->sendMessage("92hf2f83f9", "Greetings from CloudRail!");
```
---

More interfaces are coming soon.

## Advantages of Using CloudRail

* Consistent Interfaces: As functions work the same across all services, you can perform tasks between services simply.

* Easy Authentication: CloudRail includes easy ways to authenticate, to remove one of the biggest hassles of coding for external APIs.

* Switch services instantly: One line of code is needed to set up the service you are using. Changing which service is as simple as changing the name to the one you wish to use.

* Simple Documentation: There is no searching around Stack Overflow for the answer. The [CloudRail Wiki](https://documentation.cloudrail.com/php/php/Home) is regularly updated, clean, and simple to use.

* No Maintenance Times: The CloudRail Libraries are updated when a provider changes their API.

* Direct Data: Everything happens directly in the Library. No data ever passes a CloudRail server.

## Composer

```
php7 composer.phar require cloudrail/library-php
```

After installing, you need to require Composer's autoloader:

```php
require 'vendor/autoload.php';
```

## Examples

Check out https://github.com/CloudRail/cloudrail-si-php-sdk/tree/master/examples for examples of how to e.g. implement a redirectReceiver and more complex use cases.

## License Key

CloudRail provides a developer portal which offers usage insights for the SDKs and allows you to generate license keys.

It's free to sign up and generate a key.

Head over to https://developers.cloudrail.com

## Pricing

Learn more about our pricing on https://cloudrail.com/cloudrail-pricing/ 

## Other Platforms

CloudRail is also available for other platforms like Android, iOS and Java. You can find all libraries on https://cloudrail.com

## Questions?

Get in touch at any time by emailing us: support@cloudrail.com

or

Tag a question with cloudrail on [StackOverflow](http://stackoverflow.com/questions/tagged/cloudrail)