<?php

namespace ProcessWire;

/**
 * WireStatusClient Module
 *
 * Exposes local system diagnostics and installed modules updates info via a secure API.
 *
 * @author Markus Thomas
 */
class WireStatusClient extends WireData implements Module
{
    /**
     * Initialize the module
     *
     * Hooks into the page rendering pipeline to capture API requests.
     */
    public function init()
    {
        if ($this->client_enabled) {
            $this->addHookBefore('ProcessPageView::ready', $this, 'hookCheckApiRequest');
        }
    }

    /**
     * Hook to intercept API requests
     *
     * Matches either the query param (?wirestatus_api=1) or path (/wirestatus-api/)
     *
     * @param HookEvent $event
     */
    public function hookCheckApiRequest(HookEvent $event)
    {
        if (!$this->client_enabled) {
            return;
        }

        $isApiRequest = false;

        // 1. Query parameter check (highly compatible fallback)
        if ($this->wire('input')->get('wirestatus_api') !== null) {
            $isApiRequest = true;
        } else {
            // 2. Path-based check (e.g. /wirestatus-api/)
            $requestUri = $_SERVER['REQUEST_URI'] ?? '';
            $apiPath = '/' . trim($this->wire('config')->urls->root, '/') . '/wirestatus-api';
            $apiPath = preg_replace('#/+#', '/', $apiPath); // Normalize slashes

            $cleanUri = parse_url($requestUri, PHP_URL_PATH);
            if (rtrim($cleanUri, '/') === rtrim($apiPath, '/')) {
                $isApiRequest = true;
            }
        }

        if ($isApiRequest) {
            $this->handleApiRequest();
        }
    }

    /**
     * Expose local diagnostics in JSON format
     *
     * Verifies API token before yielding data.
     */
    protected function handleApiRequest()
    {
        // Authenticate the token
        $token = '';
        if (isset($_SERVER['HTTP_X_WIRESTATUS_TOKEN'])) {
            $token = $_SERVER['HTTP_X_WIRESTATUS_TOKEN'];
        } elseif (function_exists('getallheaders')) {
            $headers = getallheaders();
            foreach ($headers as $key => $value) {
                if (strcasecmp($key, 'X-WireStatus-Token') === 0) {
                    $token = $value;
                    break;
                }
            }
        }

        if (empty($token)) {
            $token = $this->wire('input')->get('token');
        }

        if (empty($token) || $token !== $this->client_token) {
            header('HTTP/1.1 401 Unauthorized');
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'status' => 'error',
                'message' => 'Unauthorized: Invalid or missing token.'
            ]);
            exit();
        }

        // Get system specifications
        $system = [
            'pw_version' => $this->wire('config')->version,
            'php_version' => PHP_VERSION,
            'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'db_driver' => $this->wire('database')->getAttribute(\PDO::ATTR_DRIVER_NAME) ?? 'MySQL',
            'db_version' => $this->wire('database')->getAttribute(\PDO::ATTR_SERVER_VERSION) ?? 'Unknown',
            'debug_mode' => $this->wire('config')->debug,
            'https' => $this->wire('config')->https,
            'admin_theme' => $this->wire('config')->adminTheme,
            'pages_count' => $this->wire('pages')->count("id>0"),
            'users_count' => $this->wire('users')->count("id>0"),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
        ];

        // Gather module updates if ProcessWireUpgrade is installed
        $updates = [];
        $hasUpgradeModule = $this->wire('modules')->isInstalled('ProcessWireUpgrade');
        if ($hasUpgradeModule) {
            $checker = $this->wire('modules')->get('ProcessWireUpgradeCheck');
            if ($checker) {
                // Returns modules that have upgrades available
                $upgrades = $checker->getModuleVersions(true);
                if (is_array($upgrades)) {
                    foreach ($upgrades as $name => $info) {
                        $updates[] = [
                            'name' => $name,
                            'title' => $this->wire('modules')->getModuleInfo($name)['title'] ?? $name,
                            'local' => $info['local'] ?? '',
                            'remote' => $info['remote'] ?? '',
                        ];
                    }
                }
            }
        }

        // Gather all installed modules with versions
        $installedModules = [];
        $allModuleInfo = $this->wire('modules')->getModuleInfoAll();
        foreach ($allModuleInfo as $name => $info) {
            if ($this->wire('modules')->isInstalled($name)) {
                $installedModules[] = [
                    'name' => $name,
                    'title' => $info['title'] ?? $name,
                    'version' => $this->wire('modules')->formatVersion($info['version']),
                    'author' => $info['author'] ?? '',
                ];
            }
        }

        $thirtyDaysAgo = time() - (30 * 24 * 60 * 60); // 30 Tage in Sekunden
        $recentUpdates = [];
        // Konfiguration der auszulesenden Logdateien und deren Filter
        $logFiles = [
            'modules.txt' => ['module', 'install', 'upgrade', 'update', 'version changes', 'aktualisiert'],
            'system-updater.txt' => [] // Da diese Datei nur Core-Updates betrifft, lesen wir alle Zeilen aus
        ];
        foreach ($logFiles as $filename => $keywords) {
            $filePath = $this->wire('config')->paths->logs . $filename;

            if (file_exists($filePath) && is_readable($filePath)) {
                $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

                foreach ($lines as $line) {
                    // ProcessWire trennt Logs mit Tabulatoren: Datum | User | URL | Message
                    $parts = explode("\t", $line);

                    if (count($parts) >= 2) {
                        $dateStr = trim($parts[0]);
                        $timestamp = strtotime($dateStr);

                        // Ignorieren, wenn das Datum ungültig oder älter als 30 Tage ist
                        if (!$timestamp || $timestamp < $thirtyDaysAgo) {
                            continue;
                        }

                        $message = trim(end($parts)); // Die Log-Nachricht steht immer ganz hinten
                        $user = count($parts) > 2 ? trim($parts[1]) : '-';

                        // Falls Keywords definiert sind, Nachricht filtern
                        if (!empty($keywords)) {
                            $match = false;
                            foreach ($keywords as $kw) {
                                if (stripos($message, $kw) !== false) {
                                    $match = true;
                                    break;
                                }
                            }
                            if (!$match) {
                                continue;
                            }
                        }

                        // Eintrag hinzufügen
                        $recentUpdates[] = [
                            'timestamp' => $timestamp,
                            'date' => date('d.m.Y H:i', $timestamp),
                            'user' => $user,
                            'message' => $message
                        ];
                    }
                }
            }
        }
        // Alle Logs chronologisch (neueste zuerst) sortieren
        usort($recentUpdates, function ($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });
        // Nur die sortierten Daten ohne den Timestamp-Key weitergeben
        $payload['recent_updates'] = array_map(function ($item) {
            return [
                'date' => $item['date'],
                'user' => $item['user'],
                'message' => $item['message']
            ];
        }, $recentUpdates);

        $response = [
            'status' => 'success',
            'site_name' => $this->wire('config')->siteName ?: ($_SERVER['HTTP_HOST'] ?? 'ProcessWire Site'),
            'timestamp' => time(),
            'system' => $system,
            'has_upgrade_module' => $hasUpgradeModule,
            'updates' => $updates,
            'modules' => $installedModules,
            'recent_updates' => $recentUpdates
        ];

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($response, JSON_PRETTY_PRINT);
        exit();
    }

    /**
     * Install hook to generate a secure token on module install
     */
    public function ___install()
    {
        try {
            $token = bin2hex(random_bytes(16));
        } catch (\Exception $e) {
            $token = md5(uniqid(mt_rand(), true));
        }

        $modules = $this->wire('modules');
        $dbData = $modules->getModuleConfigData($this->className());
        if (empty($dbData['client_token'])) {
            $dbData['client_token'] = $token;
            $modules->saveConfig($this->className(), $dbData);
        }
    }
}
