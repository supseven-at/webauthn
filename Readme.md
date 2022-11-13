# Webauthn authentication for TYPO3

This is a TYPO3 CMS extension to provide Webauthn support for
multi-factor-authentication in the backend. It is compatible with every
browser and device supporting the Webauthn specification. When using a
Chromium based browser or Firefox, those include hardware dongles/keys,
Android smartphones as well as Webauthn compatible system authentications like
Windows Hello, using biometric data like fingerprints, and/or Active Directory.

## Installation

If the setup uses TYPO3 v11.5+, use composer to add the extension as a
dependency:

```shell
composer require supseven/webauthn
```

Older TYPO3 versions are not supported.

## Configuration

The following options are a quick overview. For detailed explanations see
the folder [Documention](./Documentation)

### Base setup

To (optionally) set webauthn as the default MFA method, add this line to the
TYPO3 setup, eg. in the AdditionalConfiguration.php file:

```php
$GLOBALS['TYPO3_CONF_VARS']['BE']['recommendedMfaProvider'] = 'webauthn';
```

### Extension configuration

The following configuration values in the `$GLOBALS['TYPO3_CONF_VARS']`
array are available (all optional!). If they are actually used or displayed
depends on the webauthn device being used, eg.: a simple security key cannot
show the name or icon.

`$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['webauthn']['name']`: String
with the name of the TYPO3 installation. Defaults to to the value of
`$GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename']` if not set explicitly.

`$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['webauthn']['id']`: String
with the ID of the app. Must be a domain name. If none given, the browser will
use the domain used during device registration. Should be set to the "main"
or "primary" domain if the TYPO3 backend is available under several domains.
Otherwise a registered device can only be used under the domain it was
registerd with.

`$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['webauthn']['icon']`: String
with a path to an icon. If none is set, webauth will try the value of the
setting `$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['backend']['loginLogo']`.
If the icon is actually displayed, depends on the device capabilties, eg.:
a dongle without a display will not show it, a smartphone might.

### User configuration

The actual setup is done in the "User Settings" module, for each backend
user individually.

If a user has a "real name" in the be_user record, this name will be
displayed, otherwise the "username" will be used.

## License

The software is licensed under the GPLv2 or, at your options, a later
version of this license. See [LICENSE](./LICENSE) or
<https://www.gnu.org/licenses/old-licenses/gpl-2.0.txt>.

The icon is the official webauthn icon, provided by the
[bootstrap icons](https://icons.getbootstrap.com/)
project which uses the MIT license. See
<https://github.com/twbs/icons/blob/main/LICENSE.md> for details.

Includes the [cbor-js](https://github.com/paroga/cbor-js) library, licensed
under the MIT license. See
<https://github.com/paroga/cbor-js/blob/master/LICENSE> for details.

Uses the [webauth-lib](https://webauthn-doc.spomky-labs.com/) library, and
adapted code from
[webauthn-helper](https://github.com/web-auth/webauthn-helper)
licensed under the MIT license, see
<https://github.com/web-auth/webauthn-lib> for details.
