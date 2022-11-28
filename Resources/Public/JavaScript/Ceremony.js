define('TYPO3/CMS/Webauthn/Ceremony', function () {
    const base64UrlDecode = (input) => {
        input = input.replace(/-/g, '+').replace(/_/g, '/');

        const pad = input.length % 4;

        if (pad) {
            if (pad === 1) {
                throw new Error('Input base64 string is the wrong length to determine padding');
            }

            input += new Array(5 - pad).join('=');
        }

        return atob(input);
    };

    const arrayToBase64String = (a) => btoa(String.fromCharCode(...a));

    const preparePublicKeyOptions = (publicKey) => {
        publicKey.challenge = Uint8Array.from(base64UrlDecode(publicKey.challenge), (c) => c.charCodeAt(0));

        if (publicKey.user) {
            publicKey.user = {
                ...publicKey.user,
                id: Uint8Array.from(window.atob(publicKey.user.id), (c) => c.charCodeAt(0)),
            };
        }

        if (publicKey.excludeCredentials) {
            publicKey.excludeCredentials = publicKey.excludeCredentials.map((data) => {
                return {
                    ...data,
                    id: Uint8Array.from(base64UrlDecode(data.id), (c) => c.charCodeAt(0)),
                };
            });
        }

        if (publicKey.allowCredentials) {
            publicKey.allowCredentials = publicKey.allowCredentials.map((data) => {
                return {
                    ...data,
                    id: Uint8Array.from(base64UrlDecode(data.id), (c) => c.charCodeAt(0)),
                };
            });
        }

        return publicKey;
    };

    const preparePublicKeyCredentials = (data) => {
        const publicKeyCredential = {
            id: data.id,
            type: data.type,
            rawId: arrayToBase64String(new Uint8Array(data.rawId)),
            response: {
                clientDataJSON: arrayToBase64String(new Uint8Array(data.response.clientDataJSON)),
            },
        };

        if (data.response.attestationObject) {
            publicKeyCredential.response.attestationObject = arrayToBase64String(
                new Uint8Array(data.response.attestationObject)
            );
        }

        if (data.response.authenticatorData) {
            publicKeyCredential.response.authenticatorData = arrayToBase64String(
                new Uint8Array(data.response.authenticatorData)
            );
        }

        if (data.response.signature) {
            publicKeyCredential.response.signature = arrayToBase64String(new Uint8Array(data.response.signature));
        }

        if (data.response.userHandle) {
            publicKeyCredential.response.userHandle = arrayToBase64String(new Uint8Array(data.response.userHandle));
        }

        return publicKeyCredential;
    };

    return {
        register: async function (options) {
            const publicKey = preparePublicKeyOptions(options);
            const credentials = await navigator.credentials.create({ publicKey });
            const publicKeyCredential = preparePublicKeyCredentials(credentials);

            return JSON.stringify(publicKeyCredential);
        },
        login: async function (options) {
            const publicKey = preparePublicKeyOptions(options);
            const credentials = await navigator.credentials.get({ publicKey });
            return JSON.stringify(preparePublicKeyCredentials(credentials));
        },
    };
});
