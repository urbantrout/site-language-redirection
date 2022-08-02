<?php

call_user_func(
	static function () {
		$GLOBALS['TYPO3_CONF_VARS']['FE']['addRootLineFields'] .= ', tx_sitelanguageredirection_stop';
	}
);
