# Pingback
Pingback est une classe PHP qui vous permet d'envoyer et de recevoir des pingback.

## A propos

Les pingback sont un moyen d'informer d'autres blogs que vous avez rédigé du contenu et qu'un lien pointant vers un de leur article existe à l'intérieur.
Cette classe vous permet d'intéragir avec des plateformes de blog, comme WordPress, et implémente les pingback comme décrits par la [spécification officielle](http://hixie.ch/specs/pingback/pingback-1.0).
Seule exception : la librairie [XML-RPC](https://www.php.net/manual/en/book.xmlrpc.php) n'est pas utilisée, car son installation est devenue trop contraignante depuis PHP 8.

## Comment ça marche

Le fonctionnement des pingback est celui-ci :
- Une personne A écrit un article, avec un lien vers un article de blog, écrit par une personne B
- Le blog A va envoyer un pingback au blog B pour signaler le lien existant
- Le blog B va recevoir ce ping. Si tout est correct, il va le traiter et généralement ajouter un commentaire à l'article B, avec le lien vers l'article A
- Le blog B répond au blog A : Soit une erreur, soit succès

Cette classe va gérer ces envois et réponses presque automatiquement.

## Pré-requis

Pingback nécessite les librairies suivantes :
- [libxml](https://www.php.net/manual/en/book.libxml.php), installée par défaut depuis PHP 7.4
- [cURL](https://www.php.net/manual/en/book.curl.php)

## Installation

Il suffit d'inclure le fichier :

```php
require_once('pingback.php');
```

## Mise en place

Pour mettre en place ce système de pingback, vous devez suivre ces quelques étapes.

### Envoyer un pingback

Pour notifier les autres blogs que vous avez écrit un article et que des liens pointent vers leurs articles, il suffit de faire cela :

```php
$ping = new Pingback();
$ping->inspect('http://mydomain.com/blog/my_article');
```

En remplaçant `http://mydomain.com/blog/my_article` par l'URL de votre article que vous avez rédigé.
La classe Pingback va alors récupérer le contenu de la page, tester tous les liens présents sur celle-ci en checkant la présence d'une balise pingback, et envoyer des pingback à tous les liens valides.

### Ajouter une page de pingback

Pour que les autres sites vous répondent, nous allons devoir mettre en place une adresse et une page de pingback.
Pour l'exemple, nous créerons ici une page `ping.php`, destinée à recevoir les pingback que les autres blogs peuvent nous envoyer, mais aussi les réponses à nos pingback.
Dans ce fichier, que nous mettrons à la racine du site, mettons ce contenu :

```php
$ping = new Pingback();
$res = $ping->listen('pingCallBack');
if (!$res) {
  $ping->generateErrorResponse(Pingback::ERR_ALREADY_REGISTERRED);
}
// On envoi la réponse à l'autre blog
$ping->sendResponse();

function pingCallBack($sourceURL, $targetURL, $reqBody) {
  echo "Un ping est arrivé de $sourceURL vers notre article $target";
  echo "La requête envoyée est de " . strlen($reqBody) . " caractères";
  // Traitement du ping
  if ('lePingEstAjouté') {
    return true;
  } else {
    // On refuse le ping s'il est déjà enregistré
    return false;
  }
}
```

La méthode `listen` n'attend qu'un argument : une fonction de [callback valide](https://www.php.net/manual/en/language.types.callable.php).
- `$sourceURL` est l'adresse de l'article qui parle de notre article
- `$targetURL` est l'adresse de notre article, celui vers qui le pingback est envoyé
- `$reqBody` est le contenu HTML de `$sourceURL`, page qui a fait un lien vers notre article

Cette méthode va "écouter" toutes les requêtes envoyées à l'adresse `http://mydomain.com/ping.php`. Si une requête est envoyée en POST, avec une méthode de pingback, et que le ping est valide, alors elle exécutera la fonction de callback fournie en argument (ici la fonction `pingCallBack`).

Dans cette fonction de callback, vous avez le soin de traiter le ping entrant, grâce aux 3 variables fournies et décrites plus haut :
- Soit vous ajoutez le commentaire
- Ou alors vous le refusez, peut-être parce qu'il existe déjà par exemple.
  

Par exemple, pour un blog sous WordPress, le lien utilisé pour les pingback est toujours celui-ci (http://domain.com/xmlrpc.php).

Sur les pages de votre blog qui sont destinés à recevoir des pingback, vous devez mettre en place une balise ou un header indiquant votre lien de pingback.

## Exploit

Dont use domain.com/xmlrpc.php as your pingback URL, cause robots will try to spam it
