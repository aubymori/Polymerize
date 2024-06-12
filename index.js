const https = require("https");
const express = require("express");
const app = express();
const fs = require("fs");
const innertube = require("./modules/innertube");
const simplefunnel = require("./modules/simplefunnel");

app.all(/.*/, (req, res) =>
{
    innertube.setData(
        req.headers.cookie,
        req.headers["user-agent"],
        req.headers.authorization
    );

    simplefunnel.funnel(req).then((res) =>
    {
        console.log(res);
    });

    res.setHeader("Content-Type", "text/plain");
    res.send("hi");
});

const opts = {
    key: fs.readFileSync("./certs/server.key"),
    cert: fs.readFileSync("./certs/server.cert")
}

https.createServer(opts, app).listen(443, (req, res) =>
{
    
});