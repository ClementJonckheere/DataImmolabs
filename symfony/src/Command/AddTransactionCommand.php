<?php

// src/Command/AddTransactionCommand.php

namespace App\Command;

use App\Document\Transaction;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:add-transaction',
    description: 'Ajoute une transaction en base MongoDB',
)]
class AddTransactionCommand extends Command
{
    public function __construct(private DocumentManager $dm)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $transaction = new Transaction(
            'Amiens',
            2495.5,
            new \DateTime('2023-09-15')
        );

        $this->dm->persist($transaction);
        $this->dm->flush();

        $output->writeln('Transaction insérée avec succès : ' . $transaction->getId());

        return Command::SUCCESS;
    }
}
