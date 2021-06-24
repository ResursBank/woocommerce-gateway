# NETCURL 6.1

## Installation

Recommended installation method: composer install/require. See below.


### Install as standalone

    require tornevall/tornevall/tornelib-php-netcurl:^6.1


### Install with the entire networking suite

    require tornevall/tornevall/tornelib-php-network:^6.1


### Enabling curl and SOAP

Depending on your needs, you can start with nothing in your hands. Curl is the primary handle that has many more features than the other drivers. It is however actually not required. A proper installation in Ubuntu however may look like this:

    apt-get install php-curl php-xml php-json php-soap


* XML is not required unless you want SOAP and so on.
* SSL: OpenSSL or similar (if doing https requests).
* SOAP: SoapClient and XML-drivers (if doing https requests).
* CURL (if you prefer curl before streams).
* allow_url_fopen (if you have no access to curl, this has to be enabled).

## Packages

Source code can be found at [bitbucket repository](https://bitbucket.tornevall.net/projects/LIB/repos/tornelib-php-netcurl/browse), to make upgrading more stable. It is mirrored to [github](https://github.com/Tornevall/tornelib-php-netcurl) but new releases and tags are submitted (when ready) to [github](https://github.com/Tornevall/tornelib-php-netcurl) to maintain maximum stability (do we trust bitbucket? yes - our own server).


## Contact, information and documents

Documentation for v6.1 is located [here](https://docs.tornevall.net/display/TORNEVALL/NETCURLv6.1).
There's a [Mailinglist](https://lists.tornevall.net/pipermail/netcurl/) put up for everything concerning netcurl. That's also where you can find release information (for now). You can subscribe to the list [here](https://lists.tornevall.net/mailman/listinfo/netcurl).
Feel free to join the project from [JIRA](https://tracker.tornevall.net/projects/NETCURL). And don't be afraid of leaving feedback!


# Getting started

* [MODULE_CURL 6.1](https://docs.tornevall.net/display/TORNEVALL/NETCURLv6.1)
* [Getting started: Individual Modules](https://docs.tornevall.net/x/EAB4Aw)

Installing the network module instead will make features such as getGitTagsByUrl fully available.


## Compatibility

This library do support PHP 5.6 (not lower). However, you should [check here](https://www.php.net/supported-versions.php) to ensure your compatiblity yourself).


## Testing: Bamboo, github actions and bitbucket pipelines

NetCURL is tested within a few different suites. Due to the lack of "test time", tests are not entirely fulfilled in the Bitbucket cloud, which is why tests also are executed from other places on commits. Below is a list of those instances.

* [Atlassian Bamboo - 5.6 - 8.0](https://bamboo.tornevall.net/browse/TOR-NC60)
* [GitHub Actions - 5.6 + 7.3 - 8.0](https://github.com/Tornevall/tornelib-php-netcurl/actions)
* [Bitbucket Pipelines - 5.6 + 7.3 - 8.0](https://bitbucket.org/tornevallnetworks/tornelib-php-netcurl/addon/pipelines/home)

The [Bamboo-server](https://bamboo.tornevall.net) has a history which makes many older PHP versions available. But as of mid-summer 2020, all tests with old versions have been removed. This is also a work that continues. Github tests are only running with non-outdated versions (exception for 5.6) and so are bitbucked targeted. Since bamboo is the flagship of tests, old versions are currently not removed there.


### Other Requirements and dependencies
  
In its initial state, there are basically no requirements as this module tries to pick the best available driver in runtime.


## Using real RSS feeds

When using composer to install netcurl, also add the following to composer by for example this:

    composer require laminas/laminas-feed

If you prefer to use laminas http driver, you should also install it with laminas/laminas-http:

    composer require laminas/laminas-http

It is however not necessary with that driver, since netcurl will fall back to its own drivers if laminas is missing that driver. You should also know that if you use laminas-http it runs on pretty much defaults and therefore probably also uses **Laminas\\Http\\Client** as User-Agent.
You can request RSS feeds without laminas, however you're kind of on your own by doing this. In that case, you'll get the entries in SimpleXML formatting. 

## Library Support

### Current

* curl
* The simplest form of streamdriver and the binary safe file_get_contents (instead of the fopen-drivers that is based on the same system).
* SoapClient
* RSS feeds


#### Will you support sockets?

Not yet. This driver requires more, so this is put on hold.


## Will something break if I upgrade?

No. Version 6.1 is written to reach compatibility with v6.0, but with modernized code and PSR-4. However, do not use MODULE_CURL. Make sure you check out https://docs.tornevall.net/x/DoBPAw before deciding to run anything as older PHP-releases could be incompatible. However, if they are, so are probably you.
