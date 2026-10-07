<?php

declare(strict_types=1);

namespace Tests\Feature\Alerts;

use App\Notifications\CriticalLogAlertNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Ugc\Concerns\DispatchesFedapayWebhooks;
use Tests\TestCase;

class CriticalLogAlertTest extends TestCase
{
    use DispatchesFedapayWebhooks;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config([
            'alerts.critical.recipients' => ['admin@weact.test', 'ops@weact.test'],
            'alerts.critical.dedup_minutes' => 30,
            'alerts.critical.max_per_hour' => 20,
        ]);
        Notification::fake();
    }

    public function test_critical_log_notifies_each_recipient_with_subject_body_and_redaction(): void
    {
        Log::critical('Paiement bloqué', [
            'transaction_id' => 42,
            'api_token' => 'abc123',
            'nested' => ['Password' => 'hunter2', 'card_number' => '4111', 'ok' => 'visible'],
        ]);

        Notification::assertSentOnDemandTimes(CriticalLogAlertNotification::class, 2);
        Notification::assertSentOnDemand(
            CriticalLogAlertNotification::class,
            function (CriticalLogAlertNotification $n, array $channels, object $notifiable): bool {
                $mail = $n->toMail($notifiable);
                $body = implode("\n", array_map(fn ($l) => is_string($l) ? $l : '', $mail->introLines));

                return $notifiable->routes['mail'] === 'admin@weact.test'
                    && $mail->subject === '[WEACT][CRITIQUE] Paiement bloqué'
                    && str_contains($body, 'CRITICAL')
                    && str_contains($body, 'Paiement bloqué')
                    && str_contains($body, config('app.env'))
                    && str_contains($body, '"transaction_id": 42')
                    && str_contains($body, '"ok": "visible"')
                    && substr_count($body, '[masqué]') === 3
                    && ! str_contains($body, 'abc123')
                    && ! str_contains($body, 'hunter2')
                    && ! str_contains($body, '4111');
            }
        );
    }

    public function test_subject_is_truncated_to_about_80_characters(): void
    {
        Log::critical(str_repeat('x', 200));

        Notification::assertSentOnDemand(
            CriticalLogAlertNotification::class,
            fn (CriticalLogAlertNotification $n, array $c, object $notifiable): bool => mb_strlen($n->toMail($notifiable)->subject) <= 102
        );
    }

    public function test_context_json_is_truncated(): void
    {
        Log::critical('gros contexte', ['blob' => str_repeat('a', 10000)]);

        Notification::assertSentOnDemand(
            CriticalLogAlertNotification::class,
            function (CriticalLogAlertNotification $n, array $c, object $notifiable): bool {
                $body = implode("\n", array_map(fn ($l) => is_string($l) ? $l : '', $n->toMail($notifiable)->introLines));

                return str_contains($body, '[tronqué]') && mb_strlen($body) < 4600;
            }
        );
    }

    public function test_lower_levels_do_not_notify(): void
    {
        Log::info('i');
        Log::warning('w');
        Log::error('e');

        Notification::assertNothingSent();
    }

    public function test_alert_and_emergency_notify(): void
    {
        Log::alert('a');
        Log::emergency('e');

        Notification::assertSentOnDemandTimes(CriticalLogAlertNotification::class, 4);
    }

    public function test_same_message_and_context_within_window_alerts_once(): void
    {
        Log::critical('dup', ['id' => 1]);
        Log::critical('dup', ['id' => 1]);

        Notification::assertSentOnDemandTimes(CriticalLogAlertNotification::class, 2); // 1 événement x 2 destinataires
    }

    public function test_different_context_alerts_separately(): void
    {
        Log::critical('dup', ['id' => 1]);
        Log::critical('dup', ['id' => 2]);

        Notification::assertSentOnDemandTimes(CriticalLogAlertNotification::class, 4);
    }

    public function test_hourly_cap_drops_alerts_and_logs_one_throttle_warning(): void
    {
        config(['alerts.critical.max_per_hour' => 2, 'alerts.critical.recipients' => ['admin@weact.test']]);

        $warnings = 0;
        Event::listen(MessageLogged::class, function (MessageLogged $e) use (&$warnings): void {
            if ($e->level === 'warning' && str_contains($e->message, 'limitées')) {
                $warnings++;
            }
        });

        foreach ([1, 2, 3, 4, 5] as $i) {
            Log::critical('flood', ['i' => $i]);
        }

        Notification::assertSentOnDemandTimes(CriticalLogAlertNotification::class, 2);
        $this->assertSame(1, $warnings);
    }

    public function test_empty_or_invalid_recipients_disable_the_feature(): void
    {
        config(['alerts.critical.recipients' => []]);
        Log::critical('rien');

        Notification::assertNothingSent();
    }

    public function test_config_file_keeps_only_trimmed_valid_emails(): void
    {
        putenv('ADMIN_ALERT_EMAILS= a@x.test , nope,,b@x.test,a@x.test ');
        $_ENV['ADMIN_ALERT_EMAILS'] = $_SERVER['ADMIN_ALERT_EMAILS'] = ' a@x.test , nope,,b@x.test,a@x.test ';

        try {
            $config = require config_path('alerts.php');
        } finally {
            putenv('ADMIN_ALERT_EMAILS');
            unset($_ENV['ADMIN_ALERT_EMAILS'], $_SERVER['ADMIN_ALERT_EMAILS']);
        }

        $this->assertSame(['a@x.test', 'b@x.test'], $config['critical']['recipients']);
        $this->assertSame(30, $config['critical']['dedup_minutes']);
        $this->assertSame(20, $config['critical']['max_per_hour']);
    }

    public function test_listener_failure_never_propagates_and_only_warns(): void
    {
        Cache::shouldReceive('add')->andThrow(new \RuntimeException('cache down'));

        $levels = [];
        Event::listen(MessageLogged::class, function (MessageLogged $e) use (&$levels): void {
            $levels[] = $e->level;
        });

        Log::critical('boom');

        $this->assertEqualsCanonicalizing(['critical', 'warning'], $levels);
        Notification::assertNothingSent();
    }

    public function test_late_approved_fedapay_webhook_without_matching_row_alerts_admins(): void
    {
        $this->dispatchWebhook('transaction.approved', 9901, 'ref_late_alert');

        Notification::assertSentOnDemand(
            CriticalLogAlertNotification::class,
            fn (CriticalLogAlertNotification $n): bool => str_contains($n->logMessage, 'argent encaissé, rien de réglé')
                && $n->context['transaction_id'] === 9901
        );
        Notification::assertSentOnDemandTimes(CriticalLogAlertNotification::class, 2);
    }
}
