<?php

abstract class BaseProvider {
    protected array $credentials;
    protected string $domain;
    protected Logger $logger;

    public function __construct(string $domain, array $credentials, Logger $logger) {
        $this->domain = $domain;
        $this->credentials = $credentials;
        $this->logger = $logger;
    }

    abstract public function updateIp(string $ip): bool;
}
