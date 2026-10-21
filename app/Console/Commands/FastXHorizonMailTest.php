<?php

namespace App\Console\Commands;

use App\Mail\MailCenterMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class FastXHorizonMailTest extends Command
{
    protected $signature = 'fastxhorizon:mail-test
                            {recipient : Explicit recipient email address}
                            {--from=info : Approved FastXHorizon identity key}
                            {--render-only : Validate and render the message without sending}';

    protected $description = 'Send or render a FastXHorizon mail identity test without exposing SMTP credentials';

    public function handle(): int
    {
        $recipient = (string) $this->argument('recipient');
        $identity = (string) $this->option('from');
        $addresses = config('fastxhorizon.mail_addresses');

        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $this->error('The recipient must be a valid email address.');
            return self::FAILURE;
        }

        if (!is_array($addresses) || !array_key_exists($identity, $addresses)) {
            $this->error('Unknown mail identity. Use one of: ' . implode(', ', array_keys((array) $addresses)));
            return self::FAILURE;
        }

        $this->line('Identity: ' . $identity . ' (' . $addresses[$identity] . ')');
        $this->line('Recipient: ' . $recipient);

        $message = new MailCenterMessage(
            '<p>This is a FastXHorizon mail identity test.</p>',
            'FastXHorizon mail identity test',
            null,
            $identity,
        );

        if ($this->option('render-only')) {
            $message->build();
            $this->info('Message rendered successfully; nothing was sent.');
            return self::SUCCESS;
        }

        try {
            Mail::to($recipient)->send($message);
            $this->info('Test email sent through the configured Laravel mail transport.');
            return self::SUCCESS;
        } catch (\Throwable $exception) {
            report($exception);
            $this->error('Test email failed. Check the Laravel mail log or provider diagnostics; credentials are not displayed.');
            return self::FAILURE;
        }
    }
}
