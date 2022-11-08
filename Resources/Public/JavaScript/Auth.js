require(['TYPO3/CMS/Webauthn/Helper', 'TYPO3/CMS/Webauthn/Ceremony'], function (helper, ceremony) {
    async function auth() {
        const form = document.querySelector('#mfaController');

        try {
            const resp = await ceremony.login(
                TYPO3.settings.ajaxUrls.webauthn_auth_options,
                TYPO3.settings.ajaxUrls.webauthn_auth_verify
            );
            document.querySelector('#credentialId').value = resp.id;
            document.querySelector('#mfaController').submit();
        } catch (e) {
            console.error(e);
            form.querySelector('#webauthnFailure').classList.remove('hide');
        }
    }

    helper.init(async function () {
        document.querySelectorAll('#mfaController [type=submit]').forEach((el) => {
            el.name = 'btnSubmit';
            el.addEventListener('click', async (ev) => {
                ev.preventDefault();
                await auth();
            });
        });

        await auth();
    });
});
