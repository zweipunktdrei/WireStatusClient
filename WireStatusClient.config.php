<?php

namespace ProcessWire;

/**
 * WireStatusClient Configuration
 *
 * Config settings for the Client mode.
 *
 * @author Markus Thomas
 */
class WireStatusClientConfig extends ModuleConfig
{
    public function __construct()
    {
        // Fallback-friendly random token generation
        try {
            $defaultToken = bin2hex(random_bytes(16));
        } catch (\Exception $e) {
            $defaultToken = md5(uniqid(mt_rand(), true));
        }

        $this->add([
            'client_enabled' => false,
            'client_token' => $defaultToken,
        ]);
    }

    public function getInputfields()
    {
        $inputfields = $this->wire(new InputfieldWrapper());
        $modules = $this->wire('modules');

        $f = $modules->get('InputfieldCheckbox');
        $f->name = 'client_enabled';
        $f->label = __('Enable Client API');
        $f->description = __('If enabled, this site will expose status information via an API endpoint.');
        $f->autocheck = 1;
        $f->value = $this->client_enabled;
        $inputfields->add($f);

        // Ensure token is generated and persisted to database config if empty
        $dbData = $modules->getModuleConfigData('WireStatusClient');
        if (empty($dbData['client_token'])) {
            try {
                $token = bin2hex(random_bytes(16));
            } catch (\Exception $e) {
                $token = md5(uniqid(mt_rand(), true));
            }
            $this->client_token = $token;
            $dbData['client_token'] = $token;
            $modules->saveConfig('WireStatusClient', $dbData);
        }

        $f = $modules->get('InputfieldText');
        $f->name = 'client_token';
        $f->label = __('API Authorization Token');
        $f->description = __('This token is required by the Master site to query status from this Client. Keep it secure.');
        $f->notes = __('We generated a secure token for you. You can change this if needed.');
        $f->value = $this->client_token;
        $f->required = true;
        $f->showIf = 'client_enabled=1';
        $inputfields->add($f);

        // Copyable configuration line helper
        $fMarkup = $modules->get('InputfieldMarkup');
        $fMarkup->name = 'client_copy_line';
        $fMarkup->label = __('Master Configuration Line');
        $fMarkup->description = __('Copy this line and paste it into the "Monitored Clients" textarea on your Master installation.');

        $siteName = $this->wire('config')->siteName ?: ($_SERVER['HTTP_HOST'] ?? 'Client Site');
        $siteUrl = $this->wire('pages')->get(1)->httpUrl();
        $copyLine = htmlspecialchars("{$siteName} | {$siteUrl} | {$this->client_token}");

        $fMarkup->value = "
            <div style='display: flex; align-items: center;'>
                <input type='text' id='wirestatus-copy-line' class='uk-input' value='{$copyLine}' readonly style='font-family: monospace; max-width: 600px; margin-right: 8px;'>
                <button type='button' class='uk-button uk-button-default' onclick='copyWireStatusLine()'><i class='fa fa-copy'></i> " . __('Copy') . "</button>
            </div>
            <script>
            function copyWireStatusLine() {
                var copyText = document.getElementById(\"wirestatus-copy-line\");
                copyText.select();
                copyText.setSelectionRange(0, 99999);
                navigator.clipboard.writeText(copyText.value).then(function() {
                    alert(\"" . __('Copied to clipboard!') . "\");
                });
            }
            // Auto-populate input field if empty (ProcessWire form populator fallback)
            setTimeout(function() {
                var tokenInput = document.getElementById(\"Inputfield_client_token\");
                if (tokenInput && !tokenInput.value) {
                    tokenInput.value = \"{$this->client_token}\";
                }
            }, 100);
            </script>
        ";
        $fMarkup->showIf = 'client_enabled=1';
        $inputfields->add($fMarkup);

        return $inputfields;
    }
}
