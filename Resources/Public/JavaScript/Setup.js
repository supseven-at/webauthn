require(['TYPO3/CMS/Webauthn/Helper', 'TYPO3/CMS/Webauthn/Ceremony', 'TYPO3/CMS/Backend/Notification'], function (
    helper,
    ceremony,
    notify
) {
    helper.init(() => {
        if (!helper.detectWebAuthnSupport()) {
            document.querySelector('#addWebauthnForm').style.display = 'none';
            return;
        }

        document.querySelectorAll('[data-toggle=addWebauthn]').forEach((el) => {
            el.addEventListener('click', async () => {
                try {
                    const form = el.closest('form');
                    await ceremony.register(
                        form.querySelector('#deviceName').value,
                        TYPO3.settings.ajaxUrls.webauthn_register_options,
                        TYPO3.settings.ajaxUrls.webauthn_register_save
                    );
                    form.submit();
                } catch (e) {
                    console.error(e);
                    notify.error(TYPO3.lang.registerError);
                }
            });
        });
    });
});
