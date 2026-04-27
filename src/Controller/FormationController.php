<?php

namespace App\Controller;

use App\Entity\Formation;
use App\Entity\FormationUser;
use App\Form\RechercheType;
use App\Repository\CategorieRepository;
use App\Repository\FormationRepository;
use App\Repository\FormationUserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class FormationController extends AbstractController
{
    /**
     * Page listant toutes les formations disponibles.
     * Si une catégorie est sélectionnée en session, on filtre par catégorie.
     */
    #[Route('/formation', name: 'app_formation')]
    public function app_formation(
        FormationRepository $formationRepository,
        SessionInterface $session,
        CategorieRepository $categorieRepository
    ): Response
    {
        // Récupère la catégorie stockée en session (si l'utilisateur a filtré)
        $categorieId = $session->get("categorie");
        $categorie = $categorieId ? $categorieRepository->find($categorieId) : null;

        // Création du formulaire de recherche
        $form = $this->createForm(RechercheType::class, null, [
            'action' => $this->generateUrl('app_recherche')
        ]);

        // Si une catégorie est active, on filtre les formations, sinon on affiche tout
        if ($categorie) {
            $form->get('categorie')->setData($categorie);
            $formations = $formationRepository->findBy(['categorie' => $categorie]);
        } else {
            $formations = $formationRepository->findAll();
        }

        return $this->render('formation/index.html.twig', [
            'controller_name' => 'FormationController',
            'formations' => $formations,
            'rechercheForm' => $form,
        ]);
    }

    /**
     * Page de détail d'une formation.
     * Accessible uniquement aux utilisateurs connectés (ROLE_USER).
     * Affiche aussi le chapitre en cours si l'utilisateur possède déjà la formation.
     */
    #[Route('/formation/{formation}/detail', name: 'app_formation_show')]
    #[IsGranted('ROLE_USER')]
    public function app_formation_show(
        Formation $formation,
        FormationUserRepository $formationUserRepository
    ): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $chapitreEnCours = null;

        // Si l'utilisateur a déjà acheté cette formation, on récupère sa progression
        if ($user->hasFormation($formation)) {
            $formationUser = $formationUserRepository->findOneBy([
                'user' => $user,
                'formation' => $formation
            ]);

            if ($formationUser) {
                $chapitreEnCours = $formationUser->getChapitreEncours();
            }
        }

        return $this->render('formation/show.html.twig', [
            'formation' => $formation,
            'chapitreEnCours' => $chapitreEnCours,
        ]);
    }

    /**
     * Création de la session Stripe Checkout et redirection vers la page de paiement Stripe.
     * 
     * Fonctionnement :
     * 1. On initialise Stripe avec notre clé secrète (stockée dans .env)
     * 2. On crée une session de paiement avec les infos de la formation
     * 3. Stripe génère une page de paiement hébergée et nous retourne son URL
     * 4. On redirige l'utilisateur vers cette page Stripe
     */
    #[Route('/formation/{formation}/acheter', name: 'app_formation_buy')]
    public function app_formation_buy(Formation $formation): Response
    {
        // Initialisation de Stripe avec la clé secrète depuis les paramètres Symfony
        \Stripe\Stripe::setApiKey($this->getParameter('stripe_secret_key'));

        // Création de la session Checkout Stripe
        $session = \Stripe\Checkout\Session::create([
            'payment_method_types' => ['card'], // On accepte uniquement les cartes bancaires
            'line_items' => [[
                'price_data' => [
                    'currency' => 'eur', // Devise en euros
                    'product_data' => [
                        'name' => $formation->getTitre(), // Nom de la formation affiché sur la page Stripe
                    ],
                    // Stripe travaille en centimes, donc on multiplie par 100
                    'unit_amount' => $formation->getPrix() * 100,
                ],
                'quantity' => 1, // On achète toujours 1 formation à la fois
            ]],
            'mode' => 'payment', // Paiement unique (pas un abonnement)

            // URL de redirection si le paiement est réussi
            'success_url' => $this->generateUrl(
                'app_formation_ipn',
                ['formation' => $formation->getId()],
                UrlGeneratorInterface::ABSOLUTE_URL // URL absolue obligatoire pour Stripe
            ),

            // URL de redirection si l'utilisateur annule le paiement
            'cancel_url' => $this->generateUrl(
                'app_formation_show',
                ['formation' => $formation->getId()],
                UrlGeneratorInterface::ABSOLUTE_URL
            ),
        ]);

        // Redirection vers la page de paiement hébergée par Stripe
        return $this->redirect($session->url);
    }

    /**
     * Page de confirmation affichée après un paiement réussi.
     */
    #[Route('/formation/successful_payment', name: 'app_formation_successful_payment')]
    public function app_formation_successful_payment(): Response
    {
        return $this->render('formation/success.html.twig', []);
    }

    /**
     * IPN (Instant Payment Notification) : traitement post-paiement.
     * 
     * Cette route est appelée par Stripe après un paiement réussi.
     * Elle enregistre la formation dans le compte de l'utilisateur
     * puis redirige vers la page de confirmation.
     * 
     * ⚠️ TODO : Ajouter une vérification de la signature Stripe (webhook)
     * pour s'assurer que la requête vient bien de Stripe et non d'un utilisateur malveillant.
     */
    #[Route('/formation/{formation}/ipn', name: 'app_formation_ipn')]
    public function app_formation_ipn(
        Formation $formation,
        FormationUserRepository $formationUserRepository
    ): Response
    {
        // Récupère l'utilisateur connecté
        $user = $this->getUser();

        // Crée la relation entre l'utilisateur et la formation (= achat enregistré)
        $formationUser = new FormationUser($user, $formation);
        $formationUserRepository->save($formationUser);

        // Redirige vers la page de succès
        return $this->redirectToRoute('app_formation_successful_payment', [
            'formation' => $formation,
        ]);
    }
}