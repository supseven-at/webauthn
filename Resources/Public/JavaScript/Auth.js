require(['TYPO3/CMS/Webauthn/Helper', 'TYPO3/CMS/Webauthn/Ceremony'], function (helper, ceremony) {
    async function auth() {
        const form = document.querySelector('#mfaController');

        try {
            const options = JSON.parse(form.querySelector('[data-auth-options]').dataset.authOptions);
            form.querySelector('#credential').value = await ceremony.login(options);
            form.submit();
        } catch (e) {
            console.error(e);
            form.querySelector('#webauthnFailure').classList.remove('hide');
        }
    }

    helper.init(async function () {
        document.querySelectorAll('#mfaController [type=submit]').forEach((el) => {
            // Workaround for .submit() not working because the button has a name=submit attribute
            el.name = 'btnSubmit';
            el.addEventListener('click', async (ev) => {
                ev.preventDefault();
                await auth();
            });
        });

        await auth();
    });
});
