<?php

namespace SplitTestForElementor\Classes\Http;

/**
 * Static accessor for the $_GET superglobal.
 *
 * Usage (no instantiation needed):
 *   $page = RSTGet::int('paged', 1);
 *   RSTGet::string('search')
 *   RSTGet::tryGetInt('id', $id)
 */
class RSTGet extends RSTInputBag {

	protected static function source(): array {
		return $_GET;
	}
}
