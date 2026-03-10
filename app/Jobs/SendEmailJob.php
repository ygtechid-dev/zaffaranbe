<?php

namespace App\Jobs;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendEmailJob extends Job
{
    protected $to;
    protected $subject;
    protected $content;
    protected $view;
    protected $data;
    protected $isHtml;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($to, $subject, $content = null, $isHtml = false, $view = null, $data = [])
    {
        $this->to = $to;
        $this->subject = $subject;
        $this->content = $content;
        $this->isHtml = $isHtml;
        $this->view = $view;
        $this->data = $data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            if ($this->isHtml && $this->view) {
                Mail::send($this->view, $this->data, function ($message) {
                    $message->to($this->to)
                        ->subject($this->subject);
                });
            } else {
                Mail::raw($this->content, function ($message) {
                    $message->to($this->to)
                        ->subject($this->subject);
                });
            }

            Log::info("Background email sent successfully to: " . $this->to);
        } catch (\Exception $e) {
            // Log the error but don't re-throw, so the main process/worker stays alive
            Log::error("Background email process error (suppressed) for {$this->to}: " . $e->getMessage());
        }
    }
}
