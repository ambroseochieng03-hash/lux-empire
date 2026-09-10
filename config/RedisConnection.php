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

            /*
             * pconnect() (not connect()) — reuses one underlying TCP
             * socket to Redis across separate PHP worker processes,
             * instead of a fresh handshake on every single request.
             * With Apache's prefork MPM (many separate OS processes,
             * not threads), this is the difference between "thousands
             * of requests" meaning thousands of new connections vs. a
             * small, stable pool of long-lived ones. The persistent_id
             * argument keeps this pool separate from any other
             * persistent connection this same PHP process might open
             * elsewhere, avoiding cross-contamination.
             */
            $redis->pconnect(
                $_ENV['REDIS_HOST'] ?? '127.0.0.1',
                (int) ($_ENV['REDIS_PORT'] ?? 6379),
                1.5,
                'lux_empire'
            );

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
