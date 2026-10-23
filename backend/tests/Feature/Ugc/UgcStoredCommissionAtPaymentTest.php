<?php

declare(strict_types=1);

namespace Tests\Feature\Ugc;

use App\Enums\MissionStatus;
use App\Models\Producer;
use App\Models\User;
use App\Services\FedapayService;
use FedaPay\HttpClient\CurlClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Passage de la commission UGC « produit seul » de 10 % à 5 % : une mission créée
 * AVANT le changement garde sa `commission_ugc` stockée, et c'est cette valeur
 * (jamais un recalcul au taux courant) que le paiement débite.
 */
class UgcStoredCommissionAtPaymentTest extends TestCase
{
    use RefreshDatabase;

    private ?CurlClient $originalInstance = null;

    /** @var list<array<string, mixed>> */
    public array $createdTransactions = [];

    protected function setUp(): void
    {
        parent::setUp();

        $reflection = new \ReflectionProperty(CurlClient::class, 'instance');
        $this->originalInstance = $reflection->getValue();

        $test = $this;
        $fake = new class($test) extends CurlClient
        {
            public function __construct(private UgcStoredCommissionAtPaymentTest $test)
            {
                parent::__construct();
            }

            public function request($method, $absUrl, $params, $headers)
            {
                if (strtolower($method) === 'post' && str_ends_with($absUrl, '/transactions')) {
                    $this->test->createdTransactions[] = $params;
                }

                return [
                    json_encode([
                        'v1/transaction' => ['klass' => 'v1/transaction', 'id' => 4242, 'status' => 'pending'],
                        'token' => 'tok_test',
                        'url' => 'https://fedapay.test/checkout',
                    ]),
                    200,
                    [],
                ];
            }
        };

        $reflection->setValue(null, $fake);
        \FedaPay\Requestor::setHttpClient($fake);
    }

    protected function tearDown(): void
    {
        (new \ReflectionProperty(CurlClient::class, 'instance'))->setValue(null, $this->originalInstance);
        \FedaPay\Requestor::setHttpClient(null);

        parent::tearDown();
    }

    public function test_payment_charges_the_stored_commission_not_the_current_rate(): void
    {
        $producer = Producer::factory()->create();
        User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);

        // Mission créée à l'ancien taux : 10 % × 100 000 = 10 000 stockés.
        // Au taux courant (5 %), le calcul donnerait 5 000.
        $mission = $producer->missions()->create([
            'titre' => 'Appel UGC — ancien taux',
            'description' => 'desc',
            'date_tournage' => now()->addMonth(),
            'profil_recherche' => 'Créatrices',
            'budget' => 0,
            'date_limite_candidature' => now()->addWeeks(2),
            'nombre_faces_voulu' => 1,
            'type_mission' => 'ugc',
            'genre_voulu' => 'femme',
            'lieu' => 'Cotonou',
            'duree' => 'Livrables vidéo',
            'status' => MissionStatus::PendingPayment,
            'type_compensation' => 'product',
            'nom_produit' => 'Tenue Shade Fit',
            'valeur_produit' => 100000,
            'nombre_videos' => 2,
            'montant_remuneration' => null,
            'commission_ugc' => 10000,
        ]);

        (new FedapayService)->initiatePaymentForUgcMission($mission, 'idem-key-1');

        $this->assertCount(1, $this->createdTransactions);
        $this->assertSame(10000, (int) $this->createdTransactions[0]['amount']);
        $this->assertSame(10000, (int) $mission->fresh()->commission_ugc);
    }
}
