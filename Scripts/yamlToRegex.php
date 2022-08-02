<?php


use Symfony\Component\Yaml\Yaml;

// try to detect composer autoload file for development.
// Assumes we either are inside some public/web-root
if(is_file('../../../vendor/autoload.php')) {
	require_once('../../../vendor/autoload.php');
}
// or we have a local plugin enviroment with installed composer and .Build folder
else if (is_file('.Build/vendor/autoload.php')){
	require_once('.Build/vendor/autoload.php');
}
else {
	// cant work without composer, since we wont find Yaml Component
	die('Could not find composer autoload.php');
}

/**
 * slightly opinionated YamlToRegex Parser to read Current Bots.yaml and print out a single regex
 * Will return regex including '#' as delimiter
 */
class YamlToRegex {

	/**
	 * Will read Bots.yaml file and wrap each regex in () to avoid
	 * compromising the given Regex's.
	 *
	 * @return string
	 */
	function parse(): string {
		$regex = '#';
		$botFile = Yaml::parseFile('Configuration/Bots.yml');
		foreach ($botFile as $singleBotConfig) {
			$regex .= '(' . $singleBotConfig['regex'] . ')|';
		}

		return trim($regex, '|').'#';
	}
}

$parser = new YamlToRegex();
echo $parser->parse();