# Current Scripts

## yamlToRegex

Used to read Bots.yaml file and return a usable Regex String for `\UrbanTrout\SiteLanguageRedirection\Middleware\RedirectionMiddleware::$botPattern`.
Can be used with `composer regex:create`, for easier usage can be parsed into a file:
`composer regex:create > regex.txt`, regex.txt is added as gitignore.
