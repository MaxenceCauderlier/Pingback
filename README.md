# Pingback

[Lisez-moi en français](https://github.com/MaxenceCauderlier/Pingback/blob/main/README_FR.md)

Pingback is a PHP class that allows you to send and receive pingbacks.

## About

Pingbacks are a way to inform other blogs that you've written content and that a link to one of their articles exists within your content. This class enables you to interact with blogging platforms like WordPress and implements pingbacks as described in the [official specification](http://hixie.ch/specs/pingback/pingback-1.0). The only exception is that the [XML-RPC library](https://www.php.net/manual/en/book.xmlrpc.php) is not used because its installation has become too cumbersome since PHP 8.

## How It Works

The operation of pingbacks is as follows:
- Person A writes an article with a link to a blog article written by Person B.
- Blog A sends a pingback to Blog B to notify the existing link.
- Blog B receives this ping. If everything is correct, it processes it and typically adds a comment to Article B with a link to Article A.
- Blog B responds to Blog A: either with an error or success.

This class manages these submissions and responses almost automatically.

## Prerequisites

Pingback requires PHP >= 7.4 and the following libraries:
- [libxml](https://www.php.net/manual/en/book.libxml.php), included by default since PHP 7.4
- [cURL](https://www.php.net/manual/en/book.curl.php)

## Installation

Just include the file:

```php
require_once('pingback.php');
```

## Setting Up

To set up this pingback system, you need to follow these steps.

### Sending a Pingback

To notify other blogs that you have written an article and that links point to their articles, you can do the following:

```php
$ping = new Pingback();
$ping->inspect('http://mydomain.com/blog/my_article');
```

Replace `http://mydomain.com/blog/my_article` with the URL of your article. The Pingback class will then retrieve the content of the page, test all the links on it by checking for a pingback tag, and send pingbacks to all valid links.

### Adding a Pingback Page

To receive responses from other sites, you need to set up an address and a pingback page. For example, let's create a page called `ping.php` to receive pingbacks from other blogs as well as responses to our pingbacks. In this file, which should be placed at the root of your site, add the following content:

```php
$ping = new Pingback();
$res = $ping->listen('pingCallBack');
if ($res === null) {
  // Error during ping processing. Send the corresponding error and close the script.
  $ping->sendResponse();
}
if (!$res) {
  $ping->generateErrorResponse(Pingback::ERR_ALREADY_REGISTERRED);
}
// Send the response to the other blog
$ping->sendResponse();

function pingCallBack($sourceURL, $targetURL, $reqBody) {
  echo "A ping has arrived from $sourceURL to our article $targetURL";
  echo "The request sent is " . strlen($reqBody) . " characters long";
  // Ping processing
  if ('thePingIsAdded') {
    return true;
  } else {
    // Reject the ping if it's already registered
    return false;
  }
}
```

The `listen` method expects only one argument: a valid callback function. 

- `$sourceURL` is the address of the article mentioning your article
- `$targetURL` is your article's address
- `$reqBody` is the HTML content of `$sourceURL`, the page that linked to your article.

This method will "listen" to all requests sent to the address `http://mydomain.com/ping.php`. If a request is sent via POST with a pingback method and is valid, it will execute the provided callback function (in this case, `pingCallBack`). If the method returns `null`, an error was encountered during ping processing.

In the callback function, you handle the incoming ping using the three provided variables described earlier. You can either add the comment or reject it, perhaps because it's already registered.

### Declaring the Pingback Address

For other blogs to find your pingback page, you need to implement one of two methods on every article where you want to receive pingbacks.

#### Header

The first method is to send a header on the page of a blog article:

```php
header('X-Pingback: http://mydomain.com/ping.php');
```

#### Link Tag

Alternatively, you can add a link tag to the `<head>` of your HTML page to provide other blogs with your pingback address:

```html
<link rel="pingback" href="http://mydomain.com/ping.php">
```

And that's it!

## Exploits

All WordPress blogs use the following pingback address: `http://domain.com/xmlrpc.php`. Due to the bots attempting to spam this address, I recommend not using the same address and instead opting for something like `http://domain.com/ping.php`. Finally, only implement one of the two pingback methods on pages that can genuinely accept pingbacks; otherwise, you'll receive unnecessary pings on your contact page, blog post list, etc., where you can't add comments.
