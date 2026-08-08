class googleRecaptcha {
    constructor(sitekey) {
        this.client_id = sitekey;
    }
    init() {
        grecaptcha.ready(()=>{
            grecaptcha.execute(this.client_id, {action: 'user/register'}).then((token)=>{
                const expire_at = {
                    issued_at: new Date().getTime(),
                    expire_at: new Date(new Date().getTime()+(5*60*1000)).getTime(),
                    /* expire_at: 5min */
                }
                console.debug(expire_at);
                localStorage.setItem( (btoa(location.href)).slice(0, 16) + '.reCAPTCHA', JSON.stringify({
                    issued_at: expire_at.issued_at,
                    expire_at: expire_at.expire_at,
                    token: token,
                }) );
            });
        });
    }
    reset() {
        grecaptcha.ready(()=>{
            grecaptcha.reset();
        });
    }
    getToken() {
        let token = localStorage.getItem( (btoa(location.href)).slice(0, 16) + '.reCAPTCHA' );
        if( typeof token === 'undefined' ){
            return null;
        }
        if( typeof token === 'object' && token === null ){
            return null;
        }
        token = JSON.parse(token);
        token.token = (typeof token.token === 'undefined') ? null : token.token;
        token.issued_at = (typeof token.issued_at === 'undefined') ? null : token.issued_at;
        token.expire_at = (typeof token.expire_at === 'undefined') ? null : token.expire_at;
        return token;
    }
}
googleRecaptcha = new googleRecaptcha('6LfCHdcUAAAAAOwkHsW_7W7MfoOrvoIw9CXdLRBA');
googleRecaptcha.init();
