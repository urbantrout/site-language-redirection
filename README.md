# TYPO3 Site Language Redirection

PSR-15 middleware to redirect user to correct site language.

- Language detection is based on HTTP headers (browser language).
- When the user switches the language, a cookie gets set to save the new language as preferred language.

## Installation

`composer require urbantrout/site-language-redirection`
