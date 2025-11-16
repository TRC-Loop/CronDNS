<?php

require_once __DIR__ . '/../conf/config.php';
require_once __DIR__ . '/../lib/orm.php';
require_once __DIR__ . '/../lib/domain.php';
require_once __DIR__ . '/dyn_dns/BaseProvider.php';
require_once __DIR__ . '/dyn_dns/StratoProvider.php';
require_once __DIR__ . '/dyn_dns/NamecheapProvider.php';
require_once __DIR__ . '/dyn_dns/CloudflareProvider.php';
require_once __DIR__ . '/dyn_dns/MailinaboxProvider.php';
require_once __DIR__ . '/dyn_dns/HetznerProvider.php';

$domainManager = new PersistentEntityManager(Domain::class, $logger, DB, 'domains');
$domains = $domainManager->list([], ['domain' => 'ASC']);

// Fetch current public IPv4
$currentIp = trim(@file_get_contents('https://api.ipify.org'));
if (!$currentIp) {
    $logger->error('Could not determine current public IP.');
    exit(1);
}

$logger->info("Current external IP: $currentIp");

// Main update loop
foreach ($domains as $d) {
    $logger->info("Updating {$d->domain} ({$d->provider}) ...");

    if ($d->last_ip === $currentIp) {
        $logger->info("Domain {$d->domain} already has IP $currentIp → skipping");
        continue;
    }

    $providerClass = match (strtolower($d->provider)) {
        'strato'     => StratoProvider::class,
        'namecheap'  => NamecheapProvider::class,
        'cloudflare' => CloudflareProvider::class,
        'mailinabox' => MailinaboxProvider::class,
        'hetzner' => HetznerProvider::class,
        default      => null,
    };

    if (!$providerClass) {
        $logger->error("Unknown provider {$d->provider} for domain {$d->domain}");
        continue;
    }

    $provider = new $providerClass($d->domain, $d->credentials, $logger);

    if ($provider->updateIp($currentIp)) {
        $d->last_ip = $currentIp;
        $domainManager->save($d);
        $logger->info("Updated {$d->domain} → stored new IP $currentIp");
    } else {
        $logger->error("Failed to update {$d->domain}");
    }
}

$logger->info('DynDNS update completed.');

$lastRun = $settingsManager->find(["key" => "lastDynDnsRun"]);

if (!$lastRun) {
    $lastRun = new KeyValue();
    $lastRun->key = "lastDynDnsRun";
}

$lastRun->value = date('c'); // ISO8601 timestamp
$settingsManager->save($lastRun);

