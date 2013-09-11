twitter-proxy
=============

Twitter API OAuth Proxy

The Twitter 1.1 API only supports authenticated requests, breaking code that relies
on anonymous access to the API.  In particular, this affects Javascript code, as you
would not want to put your access tokens and secrets in publicly visible JS.

This simple PHP script acts as a proxy between your client and the Twitter API, 
authenticating your requests.  

To prevent abuse, there is a whitelist feature, so you can list the specific URLs
for which the script will respond.

Also, the script includes a caching feature which will prevent your application from
exceeding the limits imposed by the Twitter API.
