# CloudRail Authentication Sample

This is a sample of a use case that authentication with Dropbox is needed and performed using cloudrail framework. To handle server and backend issues we use the [Slim Framework](https://www.slimframework.com/) as the application Framework.

## Requirements

* Run with PHP7
* A CloudRail `$AppKey`, you can get one at https://www.cloudrail.com (signup is needed)
* An app configured in [Dropbox Developer Dashboard](https://www.dropbox.com/developers/apps)
* A dropbox `$clientKey` and `$client`
* One of your acceptables redirectURLs must be `http://localhost:8080/auth`
* Add your own `$clientKey` and `$client` to the `index.php`.

## Up and runing

To install the dependencies, run this command from the directory of this sample.

    php composer.phar install

To run the project simple run the command.

    php -S localhost:8080

Now you can perform authentication through the endpoint `/auth/Dropbox`  and `/auth/GoogleDrive`

That's it! Now go build something cool.
