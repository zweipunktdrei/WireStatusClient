# WireStatus Client

This is a lightweight ProcessWire module that securely exposes system diagnostics and pending module updates via a token-authorized JSON API endpoint. 

It is designed to work in conjunction with the **WireStatus** (Master/Dashboard) module.

## Features
- Secure API endpoint exposing ProcessWire version, PHP version, active database details, debug mode, HTTPS state, page and user counts.
- Exposes list of modules requiring upgrades (using the native `ProcessWireUpgrade` module, if installed).
- Generates a cryptographically secure token on install to authenticate requests.
- Renders a copy-paste master configuration helper line in the settings.

## Installation
1. Upload the `WireStatusClient` directory to your client site's `/site/modules/` directory.
2. In your ProcessWire admin panel, go to **Modules > Site > Install > WireStatus Client** and click **Install**.
3. Go to the module configuration screen.
4. Check **Enable Client API**.
5. Copy the generated **Master Configuration Line**.
6. Paste it into the **Monitored Clients** text area of your master installation's **WireStatus** settings.

## Security
- Requests are authorized using the custom HTTP header `X-WireStatus-Token` or the fallback query parameter `token`.
- Make sure to use **HTTPS** for production sites to secure transit token values.
