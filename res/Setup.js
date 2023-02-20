import { startRegistration, browserSupportsWebAuthn } from '@simplewebauthn/browser';

require(['TYPO3/CMS/Backend/Notification'], function (notify) {
    const wrapper = document.querySelector('#addWebauthnForm');

    if (!browserSupportsWebAuthn()) {
        wrapper.style.display = 'none';
        return;
    }

    wrapper.querySelectorAll('[data-toggle=addWebauthn]').forEach((el) => {
        el.addEventListener('click', async () => {
            try {
                const opts = JSON.parse(wrapper.dataset.creationOptions);
                const resp = await startRegistration(opts);
                const form = el.closest('form');
                form.querySelector('#credential').value = JSON.stringify(resp);
                form.submit();
            } catch (e) {
                console.error(e);
                notify.error(TYPO3.lang.registerError || 'Device not registered');
            }
        });
    });
});
