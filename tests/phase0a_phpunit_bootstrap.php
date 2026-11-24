<?php

$testCaseClass = 'PHPUnit\\Framework\\TestCase';
if (!class_exists($testCaseClass, false) && class_exists('PHPUnit_Framework_TestCase')) {
	class_alias('PHPUnit_Framework_TestCase', $testCaseClass);
}

require __DIR__ . DIRECTORY_SEPARATOR . 'bootstrap.php';
