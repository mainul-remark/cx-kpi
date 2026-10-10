<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AttendanceCheckOutReminder extends Notification
{
    public function __construct(private readonly string $checkedInAt)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Please check out')
            ->line("You checked in at {$this->checkedInAt} and have not checked out yet.")
            ->line('Check out when you finish for the day. A session left open past midnight is closed automatically and marked incomplete.');
    }

    public function toArray(object $notifiable): array
    {
        return ['message' => "You checked in at {$this->checkedInAt} and have not checked out yet."];
    }
}
