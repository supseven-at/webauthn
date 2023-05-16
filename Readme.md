# Webauthn authentication for TYPO3

This is a TYPO3 CMS extension to provide Webauthn support for
multi-factor-authentication in the backend. It is compatible with every
browser and device supporting the Webauthn specification. When using a
Chromium based browser, Firefox or Safari, those include hardware
dongles/keys, Android smartphones as well as Webauthn compatible system
authentications like Windows Hello, using biometric data like fingerprints,
and/or Active Directory.

## Installation

If the setup uses TYPO3 v11.5+, use composer to add the extension as a
dependency:

```shell
composer require supseven/webauthn
```

Older TYPO3 versions are not supported.

## Configuration

All the following configuration settings are optional. Available options as
well as their default values, if not explicitly set, are listed below.

### Base setup

To set webauthn as the default MFA method, add this line to the
TYPO3 setup, eg. in the AdditionalConfiguration.php file:

```php
$GLOBALS['TYPO3_CONF_VARS']['BE']['recommendedMfaProvider'] = 'webauthn';
```

Other providers still work, webauthn does not interfere with any of them.

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
registered with.

`$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['webauthn']['icon']`: String
with a path to an icon. If none is set, webauthn will try the value of the
setting `$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['backend']['loginLogo']`.
No icon will be used if neither settings are set.
If the icon is actually displayed, depends on the device capabilities, eg.:
a dongle without a display cannot show it, a smartphone might.

### User configuration

The actual setup is done in the "User Settings" module, for each backend
user individually.

If a user has a "real name" in the be_user record, this name will be
displayed, otherwise the "username" will be used.

## Alternatives

Extension [mfa_webauthn](https://github.com/bnf/mfa_webauthn) also provides
webauthn support for MFA. The main difference is that `EXT:mfa_webauthn`
offers a more *guided* UI at the expense of less supported devices. This
extensions offers more freedom in choice of devices, but may confuse less
experienced users with its options.

As always: in case of doubt, just try them both and choose the one that best
matches your requirements.

## Legal

### License

The software is licensed under the GPLv2 or, at your options, a later
version of this license. See [LICENSE](./LICENSE) or
<https://www.gnu.org/licenses/old-licenses/gpl-2.0.txt>.

### Mentions

The icon is the official webauthn icon, provided by the
[bootstrap icons](https://icons.getbootstrap.com/)
project which uses the MIT license. See
<https://github.com/twbs/icons/blob/main/LICENSE.md> for details.

Includes the [cbor-js](https://github.com/paroga/cbor-js) library, licensed
under the MIT license. See
<https://github.com/paroga/cbor-js/blob/master/LICENSE> for details.

Uses the [webauth-lib](https://webauthn-doc.spomky-labs.com/) library
licensed under the MIT license, see
<https://github.com/web-auth/webauthn-lib> for details.

The client JS uses the [simlewebauthn/browser](https://simplewebauthn.dev/docs/packages/browser)
library licensed under the MIT license, see
<https://github.com/web-auth/webauthn-lib> for details.
