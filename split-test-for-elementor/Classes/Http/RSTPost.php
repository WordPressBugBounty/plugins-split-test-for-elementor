<?php

namespace SplitTestForElementor\Classes\Http;

/**
 * Static accessor for the $_POST superglobal.
 *
 * Usage (no instantiation needed):
 *   $name = RSTPost::string('name');
 *   RSTPost::int('testId')
 *   RSTPost::tryGetInt('age', $age)
 */
class RSTPost extends RSTInputBag {

	protected static function source(): array {
		return $_POST;
	}
}
