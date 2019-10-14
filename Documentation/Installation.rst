.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. ==================================================
.. DEFINE SOME TEXTROLES
.. --------------------------------------------------
.. role::   underline
.. role::   typoscript(code)
.. role::   ts(typoscript)
   :class:  typoscript
.. role::   php(code)
.. rst-class:: bignums


Installation
============

Install the extension either via the Extension Manager or composer :bash:`composer require urbantrout/site-language-redirection`.

IP address based redirects


#. Update the GeoIP2 database file for IP address based redirects via CLI or Scheduler.

   * **CLI**
      :bash:`./vendor/bin/typo3 sitelanguageredirection:updatedb`
   * **Scheduler**
      Create new task of class **Excute console commands** and set **Schedulable Command** to **sitelanguageredirection:updatedb**

      .. image:: Images/scheduler.png
         :alt: Settings of new scheduler task.

      Use this option to periodically update your database file.

#. Update the preferred method in your site configuration in the tab **Site Language Redirection**. Defaults to HTTP headers.

   .. image:: Images/site-config.png
      :alt: Screenshot showing the Site Language Redirection tab in the site configuration.

   This updates :file:`config/sites/<sitename>/config.yaml` and adds the following line of code:

   .. code-block:: yaml

      SiteLanguageRedirectionMethod: 1
