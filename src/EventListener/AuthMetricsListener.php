<?php

namespace App\EventListener;

use App\Contract\MetricsRegistryInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;

final class AuthMetricsListener
{
    public function __construct(
        private readonly MetricsRegistryInterface $metricsRegistry,
    ) {}

    public function onKernelTerminate(TerminateEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();

        $route = $request->attributes->get('_route', 'unknown');
        $status = (string) $response->getStatusCode();

        try {
            $registry = $this->metricsRegistry->getRegistry();

            match ($route) {
                'api_auth_register' => $this->trackRegistration($registry, $status),
                'api_auth_login' => $this->trackLogin($registry, $status),
                'api_auth_logout' => $this->trackLogout($registry, $status),
                'api_auth_refresh' => $this->trackTokenRefresh($registry, $status),
                default => null,
            };
        } catch (\Throwable) {
        }
    }

    private function trackRegistration(\Prometheus\CollectorRegistry $registry, string $status): void
    {
        $registry->getOrRegisterCounter(
            '', 'auth_registration_attempts_total',
            'Total registration attempts', ['status']
        )->inc([$status]);

        if ($status === '201') {
            $registry->getOrRegisterCounter(
                '', 'auth_registrations_total', 'Total successful registrations', []
            )->inc();
        }

        if ($status === '400') {
            $registry->getOrRegisterCounter(
                '', 'auth_registration_duplicates_total', 'Duplicate email attempts', []
            )->inc();
        }
    }

    private function trackLogin(\Prometheus\CollectorRegistry $registry, string $status): void
    {
        $registry->getOrRegisterCounter(
            '', 'auth_login_attempts_total', 'Total login attempts', ['status']
        )->inc([$status]);

        if ($status === '200') {
            $registry->getOrRegisterCounter(
                '', 'auth_login_success_total', 'Total successful logins', []
            )->inc();
        }

        if ($status === '401') {
            $registry->getOrRegisterCounter(
                '', 'auth_login_failures_total', 'Total failed login attempts', []
            )->inc();
        }
    }

    private function trackLogout(\Prometheus\CollectorRegistry $registry, string $status): void
    {
        if ($status === '200') {
            $registry->getOrRegisterCounter(
                '', 'auth_logouts_total', 'Total successful logouts', []
            )->inc();
        }
    }

    private function trackTokenRefresh(\Prometheus\CollectorRegistry $registry, string $status): void
    {
        $registry->getOrRegisterCounter(
            '', 'auth_token_refreshes_total', 'Total token refresh attempts', ['status']
        )->inc([$status]);
    }
}
