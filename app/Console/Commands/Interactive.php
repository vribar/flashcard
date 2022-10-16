<?php

namespace App\Console\Commands;

use App\Http\Controllers\FlowController;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\SignalableCommandInterface;

class Interactive extends Command implements SignalableCommandInterface
{
    const SIGTERM = 15;
    const SIGQUIT = 3;
    const SIGABRT = 6;
    const SIGKILL = 9;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'flashcard:interactive';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Running the Flashcard application interactively';
    protected bool $exitFlashCards = false;


    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $flow = new FlowController($this);

        while(!$this->exitFlashCards) {
            $flow->mainMenu();
            $response = $this->ask(__('interaction.enter_option'));
            $this->exitFlashCards = $flow->handleMenuOption($response);
        }

        $this->line(__('interaction.thanks'));
        return Command::SUCCESS;
    }

    public function getSubscribedSignals(): array
    {
        return [self::SIGTERM, self::SIGQUIT, self::SIGABRT];
    }

    public function handleSignal(int $signal): void
    {
        $this->line('Ignoring ' . $signal);
    }
}
