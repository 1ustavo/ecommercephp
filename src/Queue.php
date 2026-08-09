<?php

class Queue
{
    private static string $dir;
    private static string $processingDir;

    public static function init(string $dir = null): void
    {
        self::$dir = $dir ?: __DIR__ . "/../../storage/queue";
        self::$processingDir = self::$dir . "/processing";

        if (!is_dir(self::$dir)) {
            @mkdir(self::$dir, 0775, true);
        }
        if (!is_dir(self::$processingDir)) {
            @mkdir(self::$processingDir, 0775, true);
        }
    }

    /**
     * Adiciona um job na fila.
     *
     * @param string $class Classe responsável pelo job (ex: NotificacaoJob)
     * @param array $data Dados serializados do job
     */
    public static function push(string $class, array $data = []): void
    {
        $id = uniqid("job_", true);
        $payload = json_encode([
            "id" => $id,
            "class" => $class,
            "data" => $data,
            "created_at" => date("Y-m-d H:i:s"),
        ], JSON_UNESCAPED_UNICODE);

        $file = self::$dir . "/" . $id . ".json";
        @file_put_contents($file, $payload, LOCK_EX);
    }

    /**
     * Processa jobs pendentes (chamar via cron/worker).
     */
    public static function process(int $limit = 10): int
    {
        self::init();

        $files = glob(self::$dir . "/*.json");
        if (!$files) {
            return 0;
        }

        $processed = 0;
        foreach ($files as $file) {
            if ($processed >= $limit) {
                break;
            }

            $content = @file_get_contents($file);
            if ($content === false) {
                continue;
            }

            $job = json_decode($content, true);
            if (!$job || !isset($job["class"])) {
                @unlink($file);
                continue;
            }

            $processingFile = self::$processingDir . "/" . basename($file);
            if (!@rename($file, $processingFile)) {
                continue;
            }

            try {
                if (class_exists($job["class"])) {
                    $instance = new $job["class"]();
                    if (method_exists($instance, "handle")) {
                        $instance->handle($job["data"] ?? []);
                    }
                }
                @unlink($processingFile);
                $processed++;
            } catch (Throwable $e) {
                error_log("Erro no job {$job['id']}: " . $e->getMessage());
                @rename($processingFile, $file);
            }
        }

        return $processed;
    }

    /**
     * Retorna quantidade de jobs pendentes.
     */
    public static function count(): int
    {
        self::init();
        $files = glob(self::$dir . "/*.json");
        return $files ? count($files) : 0;
    }
}