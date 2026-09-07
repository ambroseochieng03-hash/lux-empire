<?php
// config/RedisConnection.php
declare(strict_types=1);

final class RedisConnection
{
    private static ?Redis $instance = null;

    public static function get(): Redis
    {
        if (self::$instance === null) {
            $redis = new Redis();
            $redis->connect($_ENV['REDIS_HOST'] ?? '127.0.0.1', (int) ($_ENV['REDIS_PORT'] ?? 6379), 1.5);

            if (!empty($_ENV['REDIS_PASS'])) {
                $redis->auth($_ENV['REDIS_PASS']);
            }

            $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_IGBINARY);
            $redis->setOption(Redis::OPT_PREFIX, 'lux:');
            self::$instance = $redis;
        }

        return self::$instance;
    }
}
