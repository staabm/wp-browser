<?php

namespace lucatume\WPBrowser\Command;

use lucatume\WPBrowser\Lib\Generator\WPUnit;
use lucatume\WPBrowser\TestCase\WPXMLRPCTestCase;

class GenerateWPXMLRPC extends GenerateWPUnit
{
    /**
     * Returns the command name.
     *
     * @return string The command name.
     */
    public static function getCommandName(): string
    {
        return 'generate:wpxmlrpc';
    }

    /**
     * Returns the generator description.
     *
     * @return string The generator description.
     */
    public function getDescription(): string
    {
        return 'Generates a WPXMLRPCTestCase: a WP_XMLRPC_UnitTestCase with Codeception super-powers.';
    }

    /**
     * Returns the generator for the test case.
     *
     * @param array{namespace: string, actor: string} $config The generator configuration.
     * @param string $class The class to generate the test case for.
     *
     * @return WPUnit The generator.
     */
    protected function getGenerator(array $config, string $class): WPUnit
    {
        return new WPUnit($config, $class, WPXMLRPCTestCase::class);
    }
}
