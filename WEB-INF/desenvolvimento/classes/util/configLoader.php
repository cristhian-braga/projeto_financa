<?php
class ConfigLoader {

    private static $config = null;

    public static function get() {
        if (self::$config !== null) {
            return self::$config; // já carregado nesta requisição
        }

        $cacheFile = __DIR__ . '/../../cache/phpmvc-config.cache';
        $xmlFile   = __DIR__ . '/../../config/phpmvc-config.xml';

        // cache válido se existir e for mais novo que o XML
        if (file_exists($cacheFile) && filemtime($cacheFile) >= filemtime($xmlFile)) {
            self::$config = unserialize(file_get_contents($cacheFile));
            return self::$config;
        }

        self::$config = self::parseXml($xmlFile);
        file_put_contents($cacheFile, serialize(self::$config));

        return self::$config;
    }

    private static function parseXml($xmlFile) {
        $xml = simplexml_load_file($xmlFile);
        $result = [];

        foreach ($xml->{'action-mappings'}->action as $action) {
            $path = (string) $action['path'];
            $result[$path] = [
                'type'     => (string) $action['type'],
                'form'     => (string) $action['name'],
                'forwards' => []
            ];
            foreach ($action->forward as $forward) {
                $result[$path]['forwards'][(string) $forward['name']] = [
                    'path'     => (string) $forward['path'],
                    'redirect' => filter_var((string) $forward['redirect'], FILTER_VALIDATE_BOOLEAN)
                ];
            }
        }

        return $result;
    }
}