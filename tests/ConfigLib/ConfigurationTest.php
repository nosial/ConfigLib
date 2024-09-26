<?php

namespace ConfigLib;

use PHPUnit\Framework\TestCase;

class ConfigurationTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        $config = new Configuration('test');
        unlink($config->getPath());
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
}
