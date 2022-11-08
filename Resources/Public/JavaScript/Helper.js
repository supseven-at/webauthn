define('TYPO3/CMS/Webauthn/Helper', ['TYPO3/CMS/Backend/Notification'], function (notify) {
    return {
        init: function (callback) {
            if (document.readyState === 'complete' || document.readyState === 'interactive') {
                setTimeout(() => callback(), 1);
            } else {
                document.addEventListener('DOMContentLoaded', () => callback());
            }
        },
        detectWebAuthnSupport: function () {
            if (window.PublicKeyCredential === undefined || typeof window.PublicKeyCredential !== 'function') {
                let errorMessage = "This browser doesn't support WebAuthn.";

                if (
                    window.location.protocol === 'http:' &&
                    window.location.hostname !== 'localhost' &&
                    window.location.hostname !== '127.0.0.1'
                ) {
                    errorMessage =
                        'WebAuthn only supports secure connections. ' +
                        'For testing over HTTP, the origin "localhost" is also allowed.';
                }

                notify.error('Webauthn not supported', errorMessage);
                return false;
            }

            return true;
        },
    };
});
