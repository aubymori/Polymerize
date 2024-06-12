const dns = require("dns");

const INNERTUBE_API_HOST    = "www.youtube.com";

/* InnerTube API key. Mostly constant; this has not changed for at least 3 years. */
const INNERTUBE_API_KEY     = "AIzaSyAO_FJ2SlqU8Q4STEHLGCilw_Y9_11qcW8";

/* Don't change this, ever. */
const INNERTUBE_API_CLIENT  = "WEB";

/* InnerTube changes are mostly tied to major version, but sometimes updates are
   tied to the date. Change when needed. */
const INNERTUBE_API_VERSION = "2.20240606.06.00";

const innertube = {
    _ip: "",
    _cookies: "",
    _ua: "",
    _auth: "",

    async _init()
    {
        let dnsData = await dns.promises.lookup(
            INNERTUBE_API_HOST, 
            {
                family: 4,
                hints: dns.ADDRCONFIG | dns.V4MAPPED
            }
        );
        this._ip = dnsData.address;
    },

    setData(cookie, ua, auth)
    {
        this._cookies = cookie;
        this._ua = ua;
        this._auth = auth;
    },

    async request(
        action,
        body = {},
        clientName = INNERTUBE_API_CLIENT,
        clientVersion = INNERTUBE_API_VERSION,
    )
    {
        await this._init();
    },
};

module.exports = innertube;