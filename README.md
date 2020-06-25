# TYPO3 Site Language Redirection

PSR-15 middleware to redirect user to correct site language.

- Language detection is based on HTTP headers (browser language) or IP address.
- When the user switches the language, a cookie gets set to save the new language as preferred language.

## How it works

Example of how the extensions determines the site to redirect to via HTTP headers:

If Accept-Language is `en-US,de-AT` it looks for sites with an hreflang of `en-US`. If there is no match it then looks for `en`. If there is still no match it repeats the same logic with `de-AT` and so on.

## Installation

Install via Extension Manager or composer.  

`composer require urbantrout/site-language-redirection`

### Enable IP address based redirects

1. Update the GeoIP2 database file for IP address based redirects via CLI or Scheduler.
    * **CLI**  
    `./vendor/bin/typo3 sitelanguageredirection:updatedb`
    * **Scheduler**  
    Create new task of class **Excute console commands** and set **Schedulable Command** to **sitelanguageredirection:updatedb**  
    ![Settings of new scheduler task](Documentation/Images/scheduler.png)  
    Use this option to periodically update your database file.
    
    This step creates a file under `\TYPO3\CMS\Core\Core\Environment::getVarPath() . '/sitelanguageredirection/'` with all the geolocation information.  
    **Note:** This does not alter your SQL database.
2. Update the preferred method in your site configuration in the tab **Site Language Redirection**. Defaults to HTTP headers.  
![Screenshot of Site Language Redirection tab in site configuration](Documentation/Images/site-config.png)  
Changing this value to **IP address** updates `config/sites/<sitename>/config.yaml` and adds the following line of code:  
```yaml
SiteLanguageRedirectionMethod: 2
```

### Configure Language Fallbacks

This feature adds the possibility to define fallback languages. So for instance, if there's no italian version of the website, redirect to english version, and so on.

Site configuration would look like this with optional `SiteLanguageRedirectionFallbacks`:

```yaml
SiteLanguageRedirectionMethod: 1
SiteLanguageRedirectionFallbacks:
  fr: 'en'
  it: 'en'
```
