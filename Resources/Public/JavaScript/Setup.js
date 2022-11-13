require(['TYPO3/CMS/Webauthn/Helper', 'TYPO3/CMS/Webauthn/Ceremony', 'TYPO3/CMS/Backend/Notification'], function (
    helper,
    ceremony,
    notify
) {
    helper.init(() => {
        const wrapper = document.querySelector('#addWebauthnForm');

        if (!helper.detectWebAuthnSupport()) {
            wrapper.style.display = 'none';
            return;
        }

        wrapper.querySelectorAll('[data-toggle=addWebauthn]').forEach((el) => {
            el.addEventListener('click', async () => {
                try {
                    const opts = JSON.parse(wrapper.dataset.creationOptions);
                    const form = el.closest('form');
                    form.querySelector('#credential').value = await ceremony.register(opts);
                    form.submit();
                } catch (e) {
                    console.error(e);
                    notify.error(TYPO3.lang.registerError);
                }
            });
        });
    });
});
