const dns = require("dns");
const https = require("https");

const SIMPLEFUNNEL_HOST = "www.youtube.com";

const SIMPLEFUNNEL_ILLEGAL_HEADERS = [
    "accept",
    "accept-encoding",
    "host"
];

const simplefunnel = {
    _ip: "",

    async _init()
    {
        if (this._ip == "")
        {
            let dnsData = await dns.promises.lookup(
                SIMPLEFUNNEL_HOST, 
                {
                    family: 6,
                    hints: dns.ADDRCONFIG | dns.V4MAPPED
                }
            );
            this._ip = dnsData.address;
        }
    },

    async funnel(req)
    {
        await this._init();
        console.log(`Requesting https://[${this._ip}]${req.url}`);
        let headers = req.headers;
        for (const header of SIMPLEFUNNEL_ILLEGAL_HEADERS)
        {
            if (headers[header])
            {
                delete headers[header];
            }
        }
        return await fetch(
            `https://[${this._ip}]${req.url}`,
            {
                method: req.method,
                headers: req.headers,
                body: req?.body,
                agent: new https.Agent({
                    rejectUnauthorized: false
                })
            }
        );
    },
};

module.exports = simplefunnel;