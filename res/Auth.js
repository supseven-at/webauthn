import { startAuthentication } from '@simplewebauthn/browser';

(async function () {
    async function auth() {
        const form = document.querySelector('#mfaController');

        try {
            const options = JSON.parse(form.querySelector('[data-auth-options]').dataset.authOptions);
            const result = await startAuthentication(options);
            console.log(result);
            form.querySelector('#credential').value = JSON.stringify(result);
            form.submit();
        } catch (e) {
            console.error(e);
            form.querySelector('#webauthnFailure').classList.remove('hide');
        }
    }

    document.querySelectorAll('#mfaController [type=submit]').forEach((el) => {
        // Workaround for .submit() not working because the button has a name=submit attribute
        el.name = 'btnSubmit';
        el.addEventListener('click', async (ev) => {
            ev.preventDefault();
            await auth();
        });
    });

    await auth();
})();
