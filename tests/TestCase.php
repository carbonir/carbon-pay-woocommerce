<?php

namespace Carbon\Pay\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use MockeryPHPUnitIntegration;

    /** @var array<string, mixed> In-memory wp_options. */
    protected $options = [];

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        $this->options = [];

        Functions\stubs([
            'absint' => static function ($value) {
                return abs((int) $value);
            },
            'sanitize_key' => static function ($value) {
                return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value));
            },
            'sanitize_text_field' => static function ($value) {
                return trim(strip_tags((string) $value));
            },
            'wp_unslash' => static function ($value) {
                return $value;
            },
            'add_query_arg' => static function (array $args, string $url) {
                return $url . (strpos($url, '?') === false ? '?' : '&') . http_build_query($args);
            },
            'wc_get_checkout_url' => 'https://shop.test/checkout/',
            'get_current_user_id' => 0,
            'get_option' => function ($name, $default = false) {
                return array_key_exists($name, $this->options) ? $this->options[$name] : $default;
            },
            'update_option' => function ($name, $value) {
                $this->options[$name] = $value;

                return true;
            },
        ]);
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }
}
