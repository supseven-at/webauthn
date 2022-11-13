require(['TYPO3/CMS/Webauthn/Helper', 'TYPO3/CMS/Webauthn/Ceremony', 'TYPO3/CMS/Backend/Notification'], function (
    helper,
    Ceremony,
    notify
) {
    helper.init(() => {
        document.querySelectorAll('[data-toggle=addWebauthn]').forEach((el) => {
            el.addEventListener('click', async () => {
                const form = el.closest('form');
                const nameField = form.querySelector('#newName');
                nameField.required = false;

                if (nameField.value.replace(/\s+/g, '') !== '') {
                    const opts = JSON.parse(el.dataset.creationOptions);
                    form.querySelector('#credential').value = await Ceremony.register(opts);
                    form.querySelector('#updateAction').value = 'add';
                    form.submit();
                } else {
                    nameField.value = '';
                    nameField.required = true;
                    form.reportValidity();
                }
            });
        });

        document.querySelectorAll('[data-toggle=deleteWebauthn]').forEach((el) => {
            el.addEventListener('click', () => {
                if (
                    !el.disabled &&
                    !el.classList.contains('disabled') &&
                    document.querySelectorAll('[data-toggle=deleteWebauthn]').length > 1
                ) {
                    const form = el.closest('form');

                    form.querySelector('#updateAction').value = 'remove';
                    form.querySelector('#credentialKey').value = el.dataset.webauthnId;

                    form.submit();
                }
            });
        });
    });
});
