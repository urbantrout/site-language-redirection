.. include:: Includes.txt

=============
Configuration
=============

You can change the way this extensions tries to redirect site visitors. The two options are

* Browser language (HTTP headers)
* IP address

IP address based redirects
==========================

First you need to fetch a database file and store it on your server. This file is mandatory as it is used to map IP addresses to countries.

.. rst-class:: bignums

1. Update the GeoIP2 database file via CLI or Scheduler.

   * **CLI**
      Run `./vendor/bin/typo3 sitelanguageredirection:updatedb`

   * **Scheduler**
      Create new task of class **Execute console commands** and set **Schedulable Command** to **sitelanguageredirection:updatedb**

      .. image:: Images/scheduler.png
         :alt: Settings of new scheduler task.

      .. tip::

         Use this option to periodically update your database file.

2. Update the preferred method in your site configuration in the tab **Site Language Redirection**. Defaults to HTTP headers.

   .. image:: Images/site-config.png
      :alt: Screenshot showing the Site Language Redirection tab in the site configuration.

   This updates :file:`config/sites/<sitename>/config.yaml` and adds the following line of code:

   .. code-block:: yaml

      SiteLanguageRedirectionMethod: 2
