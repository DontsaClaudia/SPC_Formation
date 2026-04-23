<?php

namespace App\Controller;

use App\Entity\Chapitre;
use App\Entity\Formation;
use App\Form\ChapitreType;
use App\Repository\ChapitreRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/chapitre')]
final class AdminChapitreController extends AbstractController
{
    #[Route(name: 'app_admin_chapitre_index', methods: ['GET'])]
    public function index(#[MapEntity(id: 'formation')] Formation $formation): Response
    {
        return $this->render('admin_chapitre/index.html.twig', [
            'formation'=>$formation,
            'chapitres' => $formation->getChapitres(),
        ]);
    }

    #[Route('/new/{formation}', name: 'app_admin_chapitre_new', methods: ['GET', 'POST'])]
    public function new(#[MapEntity(id: 'formation')] Formation $formation, Request $request, ChapitreRepository $chapitreRepository): Response
    {
        $chapitre = new Chapitre($formation);
        $form = $this->createForm(ChapitreType::class, $chapitre);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $chapitreRepository->save($chapitre);

            return $this->redirectToRoute('app_admin_formation_edit', [
                'formation' => $formation->getId(),
            ], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin_chapitre/new.html.twig', [
            'formation' => $formation,
            'form' => $form,
            'chapitre' => $chapitre,
        ]);
    }

    #[Route('/{chapitre}', name: 'app_admin_chapitre_show', methods: ['GET'])]
    public function show(Chapitre $chapitre): Response
    {
        return $this->render('admin_chapitre/show.html.twig', [
            'chapitre' => $chapitre,
        ]);
    }

    #[Route('/{chapitre}/edit', name: 'app_admin_chapitre_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        Chapitre $chapitre,
        ChapitreRepository $chapitreRepository
    ): Response
    {
        $form = $this->createForm(ChapitreType::class, $chapitre);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // ✅ Récupère le nouveau fichier uploadé
            /** @var UploadedFile $mediaFile */
            $mediaFile = $form->get('media')->getData();

            if ($mediaFile) {
                // ✅ Supprime l'ancienne image si elle existe
                if ($chapitre->getMedia()) {
                    $oldFile = $this->getParameter('uploads_directory') . '/' . $chapitre->getMedia();
                    if (file_exists($oldFile)) {
                        unlink($oldFile);
                    }
                }

                // ✅ Upload la nouvelle image
                $newFilename = $chapitre->getId() . '.' . $mediaFile->guessExtension();

                try {
                    $mediaFile->move(
                        $this->getParameter('uploads_directory'),
                        $newFilename
                    );

                    $chapitre->setMedia($newFilename);

                } catch (FileException $e) {
                    $this->addFlash('error', 'Erreur lors de l\'upload de l\'image');
                }
            }

            $chapitreRepository->save($chapitre);

            return $this->redirectToRoute('app_admin_formation_edit', [
                'formation' => $chapitre->getFormation()->getId(),
            ], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin_chapitre/edit.html.twig', [
            'chapitre' => $chapitre,
            'form' => $form,
        ]);
    }

    #[Route('/{chapitre}', name: 'app_admin_chapitre_delete', methods: ['POST'])]
    public function delete(Request $request, Chapitre $chapitre, ChapitreRepository $chapitreRepository): Response
    {
        $formation = $chapitre->getFormation();
        if ($this->isCsrfTokenValid('delete'.$chapitre->getId(), $request->getPayload()->getString('_token'))) {
            $chapitreRepository->remove($chapitre);
        }

        return $this->redirectToRoute('app_admin_formation_edit', [
            'formation'=>$formation->getId(),
        ], Response::HTTP_SEE_OTHER);
    }
}
