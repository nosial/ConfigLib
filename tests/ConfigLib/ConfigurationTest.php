<?php

namespace ConfigLib;

use PHPUnit\Framework\TestCase;

class ConfigurationTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        $config = new Configuration('test');

        if(file_exists($config->getPath()))
        {
            unlink($config->getPath());
        }

        // Clean up test config files
        $testConfigs = ['test_env_override_true', 'test_env_override_false'];
        foreach ($testConfigs as $testConfig) {
            $config = new Configuration($testConfig);
            if(file_exists($config->getPath())) {
                unlink($config->getPath());
            }
        }
    }

    public function testConstruct(): void
    {
        $config = new Configuration();
        $this->assertInstanceOf(Configuration::class, $config);
    }

    public function testSetExists(): void
    {
        $config = new Configuration('test');

        $this->assertTrue($config->set('key1.key2', 'value', true));
        $this->assertEquals('value', $config->get('key1.key2'));

    }

    public function testSetNotExists(): void
    {
        $config = new Configuration('test');
        $this->assertFalse($config->set('key1.key3', 'value'));
    }

    public function testSetInvalidKey(): void
    {
        $config = new Configuration('test');
        $this->assertFalse($config->set('invalid\key', 'value'));
    }

    public function testGetExists(): void
    {
        $config = new Configuration('test');
        $config->set('key1.key2', 'value');
        $this->assertEquals('value', $config->get('key1.key2'));
    }

    /**
     * @test
     * Test get method when provided with existing key
     */
    public function testGetMethodWithValidKey(): void
    {
        $config = new Configuration('test');
        $this->assertTrue($config->set('key1.key2', 'value', true));
        $this->assertEquals('value', $config->get('key1.key2'));
        $this->assertTrue($config->set('foo.fizz_buzz', 'value', true));
        $this->assertEquals('value', $config->get('foo.fizz_buzz'));
    }

    /**
     * @test
     * Test get method when provided with non-existing key
     */
    public function testGetMethodWithInvalidKey(): void
    {
        $config = new Configuration('test');
        $this->assertNull($config->get('non.existing.key'));
    }

    /**
     * @test
     * Test get method when key format is not valid
     */
    public function testGetMethodWithIncorrectKeyFormat(): void
    {
        $config = new Configuration('test');
        $this->assertNull($config->get('incorrect\format'));
    }

    /**
     * @test
     * Test get method when provided with existing key and expecting the default value to be returned
     */
    public function testGetMethodWithValidKeyExpectingDefaultValue(): void
    {
        $config = new Configuration('test');
        $config->set('key1.key2', null);
        $this->assertEquals('default', $config->get('key1.key2', 'default'));
    }

    /**
     * @test
     * Test setDefault method when non-existing key is provided
     */
    public function testSetDefaultWithNonExistingKey(): void
    {
        $config = new Configuration('test');
        $this->assertTrue($config->setDefault('non.existing.key', 'default'));
        $this->assertEquals('default', $config->get('non.existing.key'));
    }

    /**
     * @test
     * Test setDefault uses environment variable when set and override is true
     */
    public function testSetDefaultWithEnvVarOverrideTrue(): void
    {
        $config = new Configuration('test_env_override_true');
        putenv('CONFIGLIB_TEST_ENV=env_value');
        $this->assertTrue($config->setDefault('env.key', 'default', 'CONFIGLIB_TEST_ENV'));
        $this->assertEquals('env_value', $config->get('env.key'));
        putenv('CONFIGLIB_TEST_ENV'); // cleanup
    }

    /**
     * @test
     * Test setDefault uses environment variable when set and override is false
     */
    public function testSetDefaultWithEnvVarOverrideFalse(): void
    {
        $config = new Configuration('test_env_override_false');
        putenv('CONFIGLIB_TEST_ENV2=env_value2');
        $this->assertTrue($config->setDefault('env.key2', 'default2', 'CONFIGLIB_TEST_ENV2', false));
        $this->assertEquals('env_value2', $config->get('env.key2'));
        putenv('CONFIGLIB_TEST_ENV2'); // cleanup
    }

    /**
     * @test
     * Test export and import functionality for all supported file formats
     */
    public function testExportImportAllFormats(): void
    {
        $formats = [FileFormat::YAML, FileFormat::JSON, FileFormat::JSON_PRETTY, FileFormat::SERIALIZED];
        $baseConfig = [
            'section' => [
                'foo' => 'bar',
                'baz' => 123,
                'arr' => [1, 2, 3]
            ]
        ];
        $tmpFiles = [];
        $config = new Configuration('test_export_import');
        $config->clear();
        foreach ($baseConfig as $k => $v) {
            $config->set($k, $v, true);
        }
        try {
            foreach ($formats as $format) {
                $tmpFile = sys_get_temp_dir() . '/configlib_test_' . uniqid() . $format->getExtension();
                $tmpFiles[] = $tmpFile;
                $config->export($tmpFile, $format, false);
                $this->assertFileExists($tmpFile);
                // Now import into a new config instance
                $importedConfig = new Configuration('imported_' . uniqid());
                $importedConfig->clear();
                $importedConfig->import($tmpFile);
                $this->assertSame($config->getConfiguration(), $importedConfig->getConfiguration(), 'Config mismatch for format: ' . $format->name);
            }
        } finally {
            foreach ($tmpFiles as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
        }
    }

    /**
     * @test
     * Test export with appendExtension true/false and cleanup
     */
    public function testExportAppendExtension(): void
    {
        $config = new Configuration('test_append_ext');
        $config->set('foo.bar', 'baz', true);
        $tmpFiles = [];
        try {
            // With appendExtension = true
            $fileBase = sys_get_temp_dir() . '/configlib_test_append';
            $config->export($fileBase, FileFormat::YAML, true);
            $fileWithExt = $fileBase . FileFormat::YAML->getExtension();
            $tmpFiles[] = $fileWithExt;
            $this->assertFileExists($fileWithExt);
            // With appendExtension = false
            $fileNoExt = $fileBase . '_noext';
            $config->export($fileNoExt, FileFormat::JSON, false);
            $tmpFiles[] = $fileNoExt;
            $this->assertFileExists($fileNoExt);
        } finally {
            foreach ($tmpFiles as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
        }
    }
}
