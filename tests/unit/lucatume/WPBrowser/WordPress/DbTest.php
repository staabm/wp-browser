<?php


namespace lucatume\WPBrowser\WordPress;

use Codeception\Test\Unit;
use lucatume\WPBrowser\Tests\Traits\TmpFilesCleanup;
use lucatume\WPBrowser\Tests\Traits\UopzFunctions;
use lucatume\WPBrowser\Utils\Env;
use lucatume\WPBrowser\Utils\Filesystem as FS;
use lucatume\WPBrowser\Utils\Random;
use lucatume\WPBrowser\WordPress\InstallationState\InstallationStateInterface;
use lucatume\WPBrowser\WordPress\InstallationState\Single;
use PDO;

class DbTest extends Unit
{
    use UopzFunctions;
    use TmpFilesCleanup;

    /**
     * It should throw when building with invalid db name
     *
     * @test
     */
    public function should_throw_when_building_with_invalid_db_name(): void
    {
        $this->expectException(DbException::class);
        $this->expectExceptionCode(DbException::INVALID_DB_NAME);

        new Db('!invalid~db-name', 'root', 'root', 'localhost');
    }

    /**
     * It should allow getting the db credentials and DSN
     *
     * @test
     */
    public function should_allow_getting_the_db_credentials_and_dsn(): void
    {
        $db = new Db('test', 'bob', 'secret', '192.1.2.3:4415', 'test_');

        $this->assertEquals('test', $db->getDbName());
        $this->assertEquals('bob', $db->getDbUser());
        $this->assertEquals('secret', $db->getDbPassword());
        $this->assertEquals('192.1.2.3:4415', $db->getDbHost());
        $this->assertEquals('test_', $db->getTablePrefix());
        $this->assertEquals('mysql:host=192.1.2.3;port=4415', $db->getDsn());
        $this->assertEquals('mysql://bob:secret@192.1.2.3:4415/test',
            $db->getDbUrl());
    }

    /**
     * It should build correctly from wp-config file
     *
     * @test
     */
    public function should_build_correctly_from_wp_config_file(): void
    {
        $wpRootDir = FS::tmpDir('db_', [
            'wp-settings.php' => '<?php ',
            'wp-config.php' => file_get_contents(codecept_data_dir('files/test-wp-config_001.php'))
        ]);
        $wpConfigFile = new WPConfigFile($wpRootDir, $wpRootDir . '/wp-config.php');

        $db = Db::fromWpConfigFile($wpConfigFile);

        $this->assertEquals('test', $db->getDbName());
        $this->assertEquals('bob', $db->getDbUser());
        $this->assertEquals('secret', $db->getDbPassword());
        $this->assertEquals('192.1.2.3:4415', $db->getDbHost());
        $this->assertEquals('test_', $db->getTablePrefix());
        $this->assertEquals('mysql:host=192.1.2.3;port=4415', $db->getDsn());
        $this->assertEquals('mysql://bob:secret@192.1.2.3:4415/test',
            $db->getDbUrl());
    }

    /**
     * It should allow db operations
     *
     * @test
     */
    public function should_allow_db_operations(): void
    {
        $dbName = Random::dbName();
        $dbHost = Env::get('WORDPRESS_DB_HOST');
        $dbUser = Env::get('WORDPRESS_DB_USER');
        $dbPassword = Env::get('WORDPRESS_DB_PASSWORD');
        $db = new Db($dbName, $dbUser, $dbPassword, $dbHost, 'test_');

        $this->assertInstanceOf(PDO::class, $db->create()->getPDO());
        $this->assertTrue($db->exists());
        $this->assertFalse($db->drop()->exists());
        $this->assertEquals(1, $db->query('CREATE DATABASE ' . $dbName));
        $this->assertEquals(0, $db->useDb($dbName)->query('CREATE TABLE table_1 (id INT)'));
        $this->assertEquals(1, $db->query('INSERT INTO table_1 (id) VALUES (:id)', ['id' => 1]));
        $this->assertEquals(1, $db->query('INSERT INTO table_1 (id) VALUES (:id)', ['id' => 2]));
        $this->assertEquals(1, $db->query('DROP DATABASE ' . $dbName));
    }

    /**
     * It should allow options operations
     *
     * @test
     */
    public function should_allow_options_operations(): void
    {
        $wpRootDir = FS::tmpDir('single_');
        $dbName = Random::dbName();
        $dbHost = Env::get('WORDPRESS_DB_HOST');
        $dbUser = Env::get('WORDPRESS_DB_USER');
        $dbPassword = Env::get('WORDPRESS_DB_PASSWORD');
        $db = new Db($dbName, $dbUser, $dbPassword, $dbHost, 'test_');
        $configurationData = new ConfigurationData();
        $configurationData->setConst('WP_PLUGIN_DIR', $wpRootDir . '/site-plugins');
        Installation::scaffold($wpRootDir, '6.1.1')
            ->configure($db, InstallationStateInterface::SINGLE_SITE, $configurationData)
            ->install(
                'https://wp.local',
                'admin',
                'password',
                'admin@wp.local',
                'Test');

        new Single($wpRootDir, $wpRootDir . '/wp-config.php');

        $this->assertEquals('lorem', $db->getOption('non-existent-option', 'lorem'));
        foreach ([
                     'foo' => 'bar',
                     'bar' => 2389,
                     'object' => (object)['foo' => 'bar'],
                     'array' => ['foo' => 'bar'],
                     'associative array' => ['foo' => 'bar', 'bar' => 'foo'],
                     'null' => null,
                     'true' => true,
                     'false' => false,
                 ] as $name => $value) {
            $this->assertEquals(1, $db->updateOption($name, $value));
            $this->assertEquals($value, $db->getOption($name));
        }
    }

    /**
     * It should throw if dump file does not exist
     *
     * @test
     */
    public function should_throw_if_dump_file_does_not_exist(): void
    {
        $dbName = Random::dbName();
        $dbHost = Env::get('WORDPRESS_DB_HOST');
        $dbUser = Env::get('WORDPRESS_DB_USER');
        $dbPassword = Env::get('WORDPRESS_DB_PASSWORD');
        $db = new Db($dbName, $dbUser, $dbPassword, $dbHost, 'wptests_');

        $this->expectException(DbException::class);
        $this->expectExceptionCode(DbException::DUMP_FILE_NOT_EXIST);

        $db->import('non-existent-file.sql');
    }

    /**
     * It should throw if dump cannot be opened to import
     *
     * @test
     */
    public function should_throw_if_dump_cannot_be_opened_to_import(): void
    {
        $dbName = Random::dbName();
        $dbHost = Env::get('WORDPRESS_DB_HOST');
        $dbUser = Env::get('WORDPRESS_DB_USER');
        $dbPassword = Env::get('WORDPRESS_DB_PASSWORD');
        $db = new Db($dbName, $dbUser, $dbPassword, $dbHost, 'wptests_');

        $this->expectException(DbException::class);
        $this->expectExceptionCode(DbException::DUMP_FILE_NOT_READABLE);

        $this->uopzSetFunctionReturn('fopen', false);

        $db->import(codecept_data_dir('files/test-dump-001.sql'));
    }

    /**
     * It should import database dumps correctly
     *
     * @test
     */
    public function should_import_database_dumps_correctly(): void
    {
        $dbName = Random::dbName();
        $dbHost = Env::get('WORDPRESS_DB_HOST');
        $dbUser = Env::get('WORDPRESS_DB_USER');
        $dbPassword = Env::get('WORDPRESS_DB_PASSWORD');
        $db = new Db($dbName, $dbUser, $dbPassword, $dbHost, 'wptests_');

        $db->import(codecept_data_dir('files/test-dump-001.sql'));

        $this->assertEquals('value_1', $db->getOption('option_1'));
    }

    /**
     * It should throw if dump line execution fails
     *
     * @test
     */
    public function should_throw_if_dump_line_execution_fails(): void
    {
        $dbName = Random::dbName();
        $dbHost = Env::get('WORDPRESS_DB_HOST');
        $dbUser = Env::get('WORDPRESS_DB_USER');
        $dbPassword = Env::get('WORDPRESS_DB_PASSWORD');
        $db = new Db($dbName, $dbUser, $dbPassword, $dbHost, 'wptests_');

        $this->expectException(DbException::class);
        $this->expectExceptionCode(DbException::FAILED_QUERY);

        $db->import(codecept_data_dir('files/bad-dump.sql'));
    }

    /**
     * It should correctly handle import files using transactions
     *
     * @test
     */
    public function should_correctly_handle_import_files_using_transactions(): void
    {
        $dbName = Random::dbName();
        $dbHost = Env::get('WORDPRESS_DB_HOST');
        $dbUser = Env::get('WORDPRESS_DB_USER');
        $dbPassword = Env::get('WORDPRESS_DB_PASSWORD');
        $db = new Db($dbName, $dbUser, $dbPassword, $dbHost, 'wptests_');

        $db->import(codecept_data_dir('files/test-dump-w-transaction.sql'));

        $this->assertEquals('test_value_1', $db->getOption('test_option_1'));
    }
}
