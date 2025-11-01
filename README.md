<a href="https://github.com/TRC-Loop/CronDNS" align="center" style="text-decoration:none; color:inherit; display:block; outline:none;">
  <img src="https://github.com/TRC-Loop/CronDNS/blob/main/.github/assets/CronDNS.webp"/>
  <div>
    <img src="https://img.shields.io/github/stars/TRC-Loop/CronDNS?style=for-the-badge"/>
    <img src="https://img.shields.io/github/forks/TRC-Loop/CronDNS?style=for-the-badge"/>
    <img src="https://img.shields.io/github/license/TRC-Loop/CronDNS?style=for-the-badge"/>
    <img src="https://img.shields.io/github/commit-activity/m/TRC-Loop/CronDNS?style=for-the-badge">
    <img src="https://img.shields.io/github/check-runs/TRC-Loop/CronDNS/main?style=for-the-badge">
    <img src="https://img.shields.io/docker/v/trcloop/crondns?style=for-the-badge&logo=docker">
  </div>
</a>
<p align="center">
  <a href="">How it Works</a>
  ·
  <a href="https://github.com/TRC-Loop/CronDNS#installation">Installation</a>
  ·
  <a href="https://github.com/TRC-Loop/CronDNS/wiki">Documentation</a>
  ·
  <a href="https://hub.docker.com/r/trcloop/crondns">Docker</a>
  ·
  <a href="https://github.com/TRC-Loop/CronDNS/wiki/Supported-Registrars">Supported Registrars</a>
</p>
<br/><br/><br/>

<img src="https://github.com/TRC-Loop/CronDNS/blob/main/.github/assets/crondns_screenshot.webp"/>



# CronDNS

A Simple Webinterface to manage all your DynDNS Domains!

## DynDNS

**Dynamic DNS (DynDNS)** is a technique that lets a fixed hostname always resolve to the current public IP address of a device whose IP changes frequently.

*Example:*  
You own the domain `myhome.dyndns.org`.  
When your ISP assigns a new IP to your home network, the DNS record for `myhome.dyndns.org` is updated automatically, so anyone can reach your home services by simply typing that hostname, regardless of the actual IP at the moment.  

### DNS record types involved

| Record | What it stores | Typical use in DynDNS |
|--------|----------------|-----------------------|
| **A**  | IPv4 address   | The hostname’s IPv4 mapping. Updated whenever the IPv4 address changes. |
| **AAAA** | IPv6 address | The hostname’s IPv6 mapping. Updated when the IPv6 address changes. |
| **DynDNS record** | A special DNS update mechanism (often a TXT or SRV entry used by the provider) | Signals to the DNS provider that the hostname should be refreshed automatically; the provider’s API updates the A/AAAA records on your behalf. |


## Installation

https://hub.docker.com/r/trcloop/crondns

```
docker run -d \
  --name crondns \
  -p 8080:80 \
  -v crondns-data:/var/www/crondns/data \
  trcloop/crondns:latest
```
> [!IMPORTANT]  
> The default password is `cr0ndns!42+`. It should be changed immediatly in the settings upon logging in for the first time. Choose a secure password with atleast 8 Characters


## Features
- Only tries to change the IP Address when needed
- Short Delay: IP changes -> IP update
- Beatiful yet functional UI/UX
- Uptime Kuma and Webhook integration
- As simple as it gets
- Full dashboard









