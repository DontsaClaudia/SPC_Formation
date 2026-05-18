<?php

namespace App\Controller;

use App\Entity\Chapitre;
use App\Entity\Formation;
use App\Entity\User;
use App\Repository\FormationUserRepository;
use App\Services\NextChap;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class SuiviFormationController extends AbstractController
{
    #[Route('/suivi-formation/{chapitre}', name: 'app_suivi_formation')]
    #[IsGranted('ROLE_USER')]
    public function app_suivi_formation(Chapitre $chapitre, FormationUserRepository $formationUserRepository): Response
    {
        $user = $this->getUser();
        if (!$user->hasFormation($chapitre->getFormation())) {
            return $this->redirectToRoute('app_formation_show', ['formation' => $chapitre->getFormation()->getId()]);
        }
        return $this->render('suivi_formation/read.html.twig', [
            'chapitre' => $chapitre,
            'userFormation' => $formationUserRepository->findOneBy(['user' => $user, 'formation' => $chapitre->getFormation()]),
        ]);
    }

    #[Route('/suivi-formation/{chapitre}/suivant', name: 'app_formation_chap_suivant')]
    public function app_formation_chap_suivant(Chapitre $chapitre, NextChap $nextChap): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $formation = $chapitre->getFormation();

        if ($user == null || !$user->hasFormation($formation)) {
            return $this->redirectToRoute('app_formation_show', [
                'formation' => $formation->getId()
            ]);
        }

        $chapSuivant = $nextChap->chapitre_suivant($user, $formation);

        if ($chapSuivant === null) {
            $this->addFlash('success', 'Félicitations ! Vous avez terminé la formation.');
            return $this->redirectToRoute('app_formation_show', [
                'formation' => $formation->getId()
            ]);
        }

        return $this->redirectToRoute('app_suivi_formation', [
            'chapitre' => $chapSuivant->getId()
        ]);
    }
}
