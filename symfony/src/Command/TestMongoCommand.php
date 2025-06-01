<?php

namespace App\Command;

use App\Document\Transaction;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:test-mongo', description: 'Teste la connexion à MongoDB et compte les transactions.')]
class TestMongoCommand extends Command
{
    public function __construct(private DocumentManager $dm)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $collection = $this->dm->getDocumentCollection(Transaction::class);
            $output->writeln("DB: " . $collection->getNamespace());
            $sample = $collection->findOne();
            $output->writeln("Exemple brut : " . json_encode($sample));
        } catch (\Exception $e) {
            $output->writeln("Erreur de connexion Mongo : " . $e->getMessage());
        }

        return Command::SUCCESS;
    }
}
