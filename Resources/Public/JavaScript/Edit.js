(()=>{var f=(e=>typeof require<"u"?require:typeof Proxy<"u"?new Proxy(e,{get:(t,n)=>(typeof require<"u"?require:t)[n]}):e)(function(e){if(typeof require<"u")return require.apply(this,arguments);throw new Error('Dynamic require of "'+e+'" is not supported')});function g(e){return new TextEncoder().encode(e)}function u(e){let t=new Uint8Array(e),n="";for(let r of t)n+=String.fromCharCode(r);return btoa(n).replace(/\+/g,"-").replace(/\//g,"_").replace(/=/g,"")}function w(e){let t=e.replace(/-/g,"+").replace(/_/g,"/"),n=(4-t.length%4)%4,i=t.padEnd(t.length+n,"="),r=atob(i),l=new ArrayBuffer(r.length),s=new Uint8Array(l);for(let a=0;a<r.length;a++)s[a]=r.charCodeAt(a);return l}function y(){return window?.PublicKeyCredential!==void 0&&typeof window.PublicKeyCredential=="function"}function m(e){let{id:t}=e;return{...e,id:w(t),transports:e.transports}}function E(e){return e==="localhost"||/^([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z]{2,}$/i.test(e)}var o=class extends Error{constructor(t,n="WebAuthnError"){super(t),this.name=n}};function v({error:e,options:t}){var n,i;let{publicKey:r}=t;if(!r)throw Error("options was missing required publicKey property");if(e.name==="AbortError"){if(t.signal===new AbortController().signal)return new o("Registration ceremony was sent an abort signal","AbortError")}else if(e.name==="ConstraintError"){if(((n=r.authenticatorSelection)===null||n===void 0?void 0:n.requireResidentKey)===!0)return new o("Discoverable credentials were required but no available authenticator supported it","ConstraintError");if(((i=r.authenticatorSelection)===null||i===void 0?void 0:i.userVerification)==="required")return new o("User verification was required but no available authenticator supported it","ConstraintError")}else{if(e.name==="InvalidStateError")return new o("The authenticator was previously registered","InvalidStateError");if(e.name!=="NotAllowedError"){if(e.name==="NotSupportedError")return r.pubKeyCredParams.filter(s=>s.type==="public-key").length===0?new o('No entry in pubKeyCredParams was of type "public-key"',"NotSupportedError"):new o("No available authenticator supported any of the specified pubKeyCredParams algorithms","NotSupportedError");if(e.name==="SecurityError"){let l=window.location.hostname;if(E(l)){if(r.rp.id!==l)return new o(`The RP ID "${r.rp.id}" is invalid for this domain`,"SecurityError")}else return new o(`${window.location.hostname} is an invalid domain`,"SecurityError")}else if(e.name==="TypeError"){if(r.user.id.byteLength<1||r.user.id.byteLength>64)return new o("User ID was not between 1 and 64 characters","TypeError")}else if(e.name==="UnknownError")return new o("The authenticator was unable to process the specified options, or could not create a new credential","UnknownError")}}return e}var c=class{createNewAbortSignal(){this.controller&&this.controller.abort("Cancelling existing WebAuthn API call for new one");let t=new AbortController;return this.controller=t,t.signal}},A=new c,C=["cross-platform","platform"];function S(e){if(e&&!(C.indexOf(e)<0))return e}async function p(e){var t;if(!y())throw new Error("WebAuthn is not supported in this browser");let i={publicKey:{...e,challenge:w(e.challenge),user:{...e.user,id:g(e.user.id)},excludeCredentials:(t=e.excludeCredentials)===null||t===void 0?void 0:t.map(m)}};i.signal=A.createNewAbortSignal();let r;try{r=await navigator.credentials.create(i)}catch(b){throw v({error:b,options:i})}if(!r)throw new Error("Registration was not completed");let{id:l,rawId:s,response:a,type:h}=r,d;return typeof a.getTransports=="function"&&(d=a.getTransports()),{id:l,rawId:u(s),response:{attestationObject:u(a.attestationObject),clientDataJSON:u(a.clientDataJSON),transports:d},type:h,clientExtensionResults:r.getClientExtensionResults(),authenticatorAttachment:S(r.authenticatorAttachment)}}f([],function(){document.querySelectorAll("[data-toggle=addWebauthn]").forEach(e=>{e.addEventListener("click",async()=>{let t=e.closest("form"),n=t.querySelector("#newName");if(n.required=!1,n.value.replace(/\s+/g,"")!==""){let i=JSON.parse(e.dataset.creationOptions);t.querySelector("#credential").value=await p(i),t.querySelector("#updateAction").value="add",t.submit()}else n.value="",n.required=!0,t.reportValidity()})}),document.querySelectorAll("[data-toggle=deleteWebauthn]").forEach(e=>{e.addEventListener("click",()=>{if(!e.disabled&&!e.classList.contains("disabled")&&document.querySelectorAll("[data-toggle=deleteWebauthn]").length>1){let t=e.closest("form");t.querySelector("#updateAction").value="remove",t.querySelector("#credential").value=e.dataset.webauthnId,t.submit()}})})});})();
