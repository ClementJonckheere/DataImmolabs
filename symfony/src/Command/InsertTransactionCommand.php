<?php

namespace App\Command;

use App\Document\Transaction;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:insert-transaction')]
class InsertTransactionCommand extends Command
{
    public function __construct(private DocumentManager $dm)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $transaction = new Transaction('Amiens', 2500.75, new \DateTime('2024-04-04'));
        $this->dm->persist($transaction);
        $this->dm->flush();

        $output->writeln('<info>Transaction insérée avec succès !</info>');

        return Command::SUCCESS;
    }
}
