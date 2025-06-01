<?php
// src/Controller/TestController.php
namespace App\Controller;

use App\Document\Transaction;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TestController extends AbstractController
{
#[Route('/test', name: 'test_mongo')]
public function index(DocumentManager $dm): Response
{
$transaction = new Transaction();
$transaction->setName('Test Transaction');
$transaction->setAmount(42.50);
$transaction->setCreatedAt(new \DateTime());

$dm->persist($transaction);
$dm->flush();

return new Response('Transaction saved with ID: ' . $transaction->getId());
}
}
