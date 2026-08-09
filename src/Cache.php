<?php

class Cache
{
    private static string $dir;
    private static int $ttl;

    public static function init(string $dir = null, int $ttl = 300): void
    {
        self::$dir = $dir ?: __DIR__ . "/../../storage/cache";
        self::$ttl = $ttl;

        if (!is_dir(self::$dir)) {
            @mkdir(self::$dir, 0775, true);
        }
    }

    public static function get(string $key): ?string
    {
        $file = self::$dir . "/" . md5($key) . ".cache";

        if (!file_exists($file)) {
            return null;
        }

        $data = file_get_contents($file);
        if ($data === false) {
            return null;
        }

        $parts = explode("|", $data, 2);
        if (count($parts) !== 2) {
            return null;
        }

        [$expire, $value] = $parts;
        if ((int) $expire < time()) {
            @unlink($file);
            return null;
        }

        return $value;
    }

    public static function set(string $key, string $value, int $ttl = null): void
    {
        $ttl = $ttl ?? self::$ttl;
        $file = self::$dir . "/" . md5($key) . ".cache";
        $expire = time() + $ttl;
        @file_put_contents($file, $expire . "|" . $value, LOCK_EX);
    }

    public static function delete(string $key): void
    {
        $file = self::$dir . "/" . md5($key) . ".cache";
        @unlink($file);
    }

    public static function clear(): void
    {
        if (!is_dir(self::$dir)) {
            return;
        }

        foreach (glob(self::$dir . "/*.cache") as $file) {
            @unlink($file);
        }
    }
}