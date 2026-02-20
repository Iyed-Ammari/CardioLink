<?php

namespace App\Controller;

use App\Entity\Lieu;
use App\Entity\RendezVous;
use App\Form\RendezVousType;
use App\Repository\LieuRepository;
use App\Repository\RendezVousRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/rdv')]
class RendezVousController extends AbstractController
{
    #[Route('/', name: 'app_rdv_index', methods: ['GET'])]
    public function index(RendezVousRepository $rendezVousRepository): Response
    {
        // Logique intelligente : Affiche les RDV selon le rôle
        $user = $this->getUser();
       
        if ($this->isGranted('ROLE_MEDECIN')) {
            $rdvs = $rendezVousRepository->findBy(['medecin' => $user], ['dateHeure' => 'DESC']);
        } else {
            $rdvs = $rendezVousRepository->findBy(['patient' => $user], ['dateHeure' => 'ASC']);
        }

        return $this->render('rendez_vous/index.html.twig', [
            'rendez_vous' => $rdvs,
        ]);
    }

    #[Route('/nouveau', name: 'app_rdv_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_PATIENT')] // Seul un patient peut demander un RDV
    public function new(Request $request, EntityManagerInterface $entityManager, RendezVousRepository $repo, LieuRepository $lieuRepository): Response
    {
        $rdv = new RendezVous();
       
        // 1. On lie automatiquement le RDV au patient connecté
        $rdv->setPatient($this->getUser());
        $rdv->setStatut('En attente'); // Statut par défaut

        $form = $this->createForm(RendezVousType::class, $rdv);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
           
            // --- VALIDATION 1: PROTECTION ANTI-COLLISION ---
            // On vérifie si le médecin est libre à cette heure-là
            $conflits = $repo->countCrenau($rdv->getDateHeure(), $rdv->getMedecin());
           
            if ($conflits > 0) {
                $this->addFlash('danger', 'Le médecin n\'est pas disponible à ce créneau. Veuillez choisir une autre heure.');
                return $this->render('rendez_vous/new.html.twig', [
                    'rendez_vous' => $rdv,
                    'form' => $form,
                ]);
            }

            // --- LOGIQUE TÉLÉMÉDECINE ---
            if ($rdv->getType() === 'Télémédecine') {
                // On génère un lien Jitsi unique
                $uniqueId = uniqid('cardiolink-');
                // Paramètres Jitsi pour permettre aux participants de rejoindre sans modérateur
                $jitsiUrl = "https://meet.jit.si/$uniqueId#config.disableModeratorIndicator=true&config.openBridgeChannel=true&userInfo.displayName=CardioLink";
                $rdv->setLienVisio($jitsiUrl);

                // En visio, le lieu physique n'est pas important (on peut le mettre à null ou laisser tel quel)
                $rdv->setLieu(null);
            } else {
                // Pour le présentiel, on assigne automatiquement le lieu selon le cabinet du médecin
                $medecin = $rdv->getMedecin();
                if ($medecin && $medecin->getCabinet()) {
                    // On cherche si un Lieu existe pour ce cabinet
                    $lieu = $lieuRepository->findOneBy(['nom' => $medecin->getCabinet()]);

                    if (!$lieu) {
                        // Si aucun Lieu n'existe, on en crée un
                        $lieu = new Lieu();
                        $lieu->setNom($medecin->getCabinet());
                        $lieu->setAdresse($medecin->getCabinet());
                        $lieu->setVille($medecin->getAdresse());
                        $lieu->setContact($medecin->getTel());
                        $lieu->setEstVirtuel(false);
                        $entityManager->persist($lieu);
                    }

                    $rdv->setLieu($lieu);
                }
            }

            $entityManager->persist($rdv);
            $entityManager->flush();

            $this->addFlash('success', 'Votre demande de rendez-vous a été envoyée avec succès !');

            return $this->redirectToRoute('app_rdv_index');
        }

        return $this->render('rendez_vous/new.html.twig', [
            'rendez_vous' => $rdv,
            'form' => $form,
        ]);
    }
   
    // ajouter la méthode pour supprimer/annuler un RDV
#[Route('/{id}', name: 'app_rdv_delete', methods: ['POST'])]
    public function delete(Request $request, RendezVous $rendezVous, EntityManagerInterface $entityManager): Response
    {
        // On vérifie le token de sécurité pour éviter les attaques CSRF
        if ($this->isCsrfTokenValid('delete'.$rendezVous->getId(), $request->request->get('_token'))) {
            $entityManager->remove($rendezVous);
            $entityManager->flush();
            $this->addFlash('success', 'Le rendez-vous a été supprimé avec succès.');
        }

        return $this->redirectToRoute('app_rdv_index', [], Response::HTTP_SEE_OTHER);
    }
    #[Route('/{id}/edit', name: 'app_rdv_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, RendezVous $rendezVous, EntityManagerInterface $entityManager, RendezVousRepository $repo): Response
    {
        // Sécurité : On empêche de modifier un RDV qui n'est pas le sien
        // (Sauf si on est ADMIN, à rajouter plus tard si besoin)
        if ($rendezVous->getPatient() !== $this->getUser() && $rendezVous->getMedecin() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas modifier ce rendez-vous.');
        }

        $form = $this->createForm(RendezVousType::class, $rendezVous);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
           
            // --- VALIDATION 1: PROTECTION ANTI-COLLISION (si la date ou le médecin ont changé) ---
            if ($rendezVous->getDateHeure() || $rendezVous->getMedecin()) {
                $conflits = $repo->countCrenau($rendezVous->getDateHeure(), $rendezVous->getMedecin(), $rendezVous->getId());
                if ($conflits > 0) {
                    $this->addFlash('danger', 'Le médecin n\'est pas disponible à ce créneau. Veuillez choisir une autre heure.');
                    return $this->render('rendez_vous/edit.html.twig', [
                        'rendez_vous' => $rendezVous,
                        'form' => $form,
                    ]);
                }
            }

            // --- VALIDATION 2: LOGIQUE INTELLIGENTE ---
            // Si on passe en Télémédecine et qu'il n'y a pas de lien, on en crée un.
            if ($rendezVous->getType() === 'Télémédecine' && empty($rendezVous->getLienVisio())) {
                $uniqueId = uniqid('cardiolink-');
                $rendezVous->setLienVisio("https://meet.jit.si/$uniqueId");
                $rendezVous->setLieu(null); // On nettoie le lieu
            }
           
            // Si on repasse en Présentiel, on peut nettoyer le lien (optionnel)
            if ($rendezVous->getType() === 'Présentiel') {
                $rendezVous->setLienVisio(null);
            }

            $entityManager->flush();

            $this->addFlash('success', 'Le rendez-vous a été modifié avec succès.');

            return $this->redirectToRoute('app_rdv_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('rendez_vous/edit.html.twig', [
            'rendez_vous' => $rendezVous,
            'form' => $form,
        ]);
    }
    #[Route('/{id}/update-status', name: 'app_rdv_update_status', methods: ['POST'])]
public function updateStatus(
    Request $request,
    RendezVous $rendezVous,
    EntityManagerInterface $entityManager
): Response {

    if (!$this->isGranted('ROLE_MEDECIN')) {
        throw $this->createAccessDeniedException();
    }

    if ($this->isCsrfTokenValid(
        'update_status'.$rendezVous->getId(),
        $request->request->get('_token')
    )) {
        $rendezVous->setStatut($request->request->get('statut'));
        $entityManager->flush();
        $this->addFlash('success', 'Statut mis à jour.');
    }

    return $this->redirectToRoute('app_rdv_index');
}
}
