<?php

namespace App\Controller;

use App\Entity\RendezVous;
use App\Form\RendezVousType;
use App\Form\RendezVousStatusType;
use App\Repository\RendezVousRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Entity\Lieu;
use App\Repository\LieuRepository;

#[Route('/rdv')]
class RendezVousController extends AbstractController
{
    #[Route('/', name: 'app_rdv_index', methods: ['GET'])]
    public function index(RendezVousRepository $rendezVousRepository, EntityManagerInterface $entityManager): Response
    {
        // Logique intelligente : Affiche les RDV selon le rôle
        $user = $this->getUser();

        if ($this->isGranted('ROLE_MEDECIN')) {
            $rdvs = $rendezVousRepository->findBy(['medecin' => $user], ['dateHeure' => 'DESC']);
        } else {
            $rdvs = $rendezVousRepository->findBy(['patient' => $user], ['dateHeure' => 'ASC']);
        }

        // Mettre à jour automatiquement les statuts si la consultation est passée
        foreach ($rdvs as $rdv) {
            $rdv->updateStatusIfPassed();
        }
        $entityManager->flush();

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

            // --- VALIDATION SERVEUR ---
            // Vérifier que toutes les données obligatoires sont présentes
            if (empty($rdv->getMedecin())) {
                $this->addFlash('danger', 'Veuillez sélectionner un médecin.');
                return $this->render('rendez_vous/new.html.twig', [
                    'rendez_vous' => $rdv,
                    'form' => $form,
                ]);
            }

            if (empty($rdv->getDateHeure())) {
                $this->addFlash('danger', 'Veuillez sélectionner une date et une heure.');
                return $this->render('rendez_vous/new.html.twig', [
                    'rendez_vous' => $rdv,
                    'form' => $form,
                ]);
            }

            if (empty($rdv->getType())) {
                $this->addFlash('danger', 'Veuillez sélectionner un type de consultation.');
                return $this->render('rendez_vous/new.html.twig', [
                    'rendez_vous' => $rdv,
                    'form' => $form,
                ]);
            }

            // Vérifier que le médecin a le rôle ROLE_MEDECIN
            $medecin = $rdv->getMedecin();
            if (!in_array('ROLE_MEDECIN', $medecin->getRoles())) {
                $this->addFlash('danger', 'Le praticien sélectionné n\'est pas un médecin.');
                return $this->render('rendez_vous/new.html.twig', [
                    'rendez_vous' => $rdv,
                    'form' => $form,
                ]);
            }

            // Vérifier que la date est dans le futur
            $now = new \DateTime();
            if ($rdv->getDateHeure() <= $now) {
                $this->addFlash('danger', 'Veuillez sélectionner une date et une heure dans le futur.');
                return $this->render('rendez_vous/new.html.twig', [
                    'rendez_vous' => $rdv,
                    'form' => $form,
                ]);
            }

            // --- 2. PROTECTION ANTI-COLLISION ---
            // On vérifie si le médecin est libre à cette heure-là
            $conflits = $repo->countCrenau($rdv->getDateHeure(), $rdv->getMedecin());

            if ($conflits > 0) {
                $this->addFlash('danger', 'Le médecin n\'est pas disponible à ce créneau. Veuillez choisir une autre heure.');
                return $this->render('rendez_vous/new.html.twig', [
                    'rendez_vous' => $rdv,
                    'form' => $form,
                ]);
            }

            // --- 3. LOGIQUE TÉLÉMÉDECINE ---
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
                        $lieu->setAdresse($medecin->getAdresse());
                        $lieu->setVille('');
                        $lieu->setEstVirtuel(false);
                        $entityManager->persist($lieu);
                    }

                    $rdv->setLieu($lieu);
                }
                $uniqueId = uniqid(); 
                $rdv->setLienVisio("https://meet.jit.si/$uniqueId");
               
                // En visio, le lieu physique n'est pas important (on peut le mettre à null ou laisser tel quel)
                $rdv->setLieu(null);
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

    // Tu pourras ajouter ici la méthode pour supprimer/annuler un RDV
    #[Route('/{id}', name: 'app_rdv_delete', methods: ['POST'])]
    public function delete(Request $request, RendezVous $rendezVous, EntityManagerInterface $entityManager): Response
    {
        // Sécurité : On vérifie que l'utilisateur est bien le patient ou le médecin du RDV
        if ($rendezVous->getPatient() !== $this->getUser() && $rendezVous->getMedecin() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas supprimer ce rendez-vous.');
        }

        // Vérifier les permissions selon le rôle
        $isPatient = $rendezVous->getPatient() === $this->getUser();
        $isDoctor = $rendezVous->getMedecin() === $this->getUser();

        // Le patient ne peut pas supprimer si le RDV est finalisé ou passé
        if ($isPatient && !$rendezVous->canPatientDelete()) {
            $this->addFlash('danger', 'Vous ne pouvez pas supprimer ce rendez-vous. Il est finalisé ou passé.');
            return $this->redirectToRoute('app_rdv_index', [], Response::HTTP_SEE_OTHER);
        }

        // Le médecin ne peut pas supprimer si le RDV est passé
        if ($isDoctor && !$rendezVous->canDoctorDelete()) {
            $this->addFlash('danger', 'Vous ne pouvez pas supprimer ce rendez-vous. La consultation est passée.');
            return $this->redirectToRoute('app_rdv_index', [], Response::HTTP_SEE_OTHER);
        }

        // On vérifie le token de sécurité pour éviter les attaques CSRF
        if ($this->isCsrfTokenValid('delete'.$rendezVous->getId(), $request->request->get('_token'))) {
            $entityManager->remove($rendezVous);
            $entityManager->flush();
            $this->addFlash('success', 'Le rendez-vous a été supprimé avec succès.');
        }

        return $this->redirectToRoute('app_rdv_index', [], Response::HTTP_SEE_OTHER);
    }
    #[Route('/{id}/edit', name: 'app_rdv_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, RendezVous $rendezVous, EntityManagerInterface $entityManager, LieuRepository $lieuRepository): Response
    {
        // Sécurité : Seul le patient peut éditer un RDV
        if ($rendezVous->getPatient() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Seul le patient peut modifier ce rendez-vous.');
        }

        // Vérifier les permissions de modification
        if (!$rendezVous->canPatientEdit()) {
            $this->addFlash('danger', 'Vous ne pouvez pas modifier ce rendez-vous. Il est finalisé ou passé.');
            return $this->redirectToRoute('app_rdv_index', [], Response::HTTP_SEE_OTHER);
        }

        $isEditByDoctor = false;

        // $form = $this->createForm(RendezVousType::class, $rendezVous, [
        //     'edit_by_doctor' => false
        // ]);
        // $form->handleRequest($request);

        // if ($form->isSubmitted() && $form->isValid()) {

        //     // --- VALIDATION SERVEUR ---
        //     // Vérifier que toutes les données obligatoires sont présentes
        //     if (empty($rendezVous->getMedecin())) {
        //         $this->addFlash('danger', 'Veuillez sélectionner un médecin.');
        //         return $this->render('rendez_vous/edit.html.twig', [
        //             'rendez_vous' => $rendezVous,
        //             'form' => $form,
        //         ]);
        //     }

        //     if (empty($rendezVous->getDateHeure())) {
        //         $this->addFlash('danger', 'Veuillez sélectionner une date et une heure.');
        //         return $this->render('rendez_vous/edit.html.twig', [
        //             'rendez_vous' => $rendezVous,
        //             'form' => $form,
        //         ]);
        //     }

        //     if (empty($rendezVous->getType())) {
        //         $this->addFlash('danger', 'Veuillez sélectionner un type de consultation.');
        //         return $this->render('rendez_vous/edit.html.twig', [
        //             'rendez_vous' => $rendezVous,
        //             'form' => $form,
        //         ]);
        //     }
        
        //     // Vérifier que le médecin a le rôle ROLE_MEDECIN
        //     $medecin = $rendezVous->getMedecin();
        //     if (!in_array('ROLE_MEDECIN', $medecin->getRoles())) {
        //         $this->addFlash('danger', 'Le praticien sélectionné n\'est pas un médecin.');
        //         return $this->render('rendez_vous/edit.html.twig', [
        //             'rendez_vous' => $rendezVous,
        //             'form' => $form,
        //         ]);
        //     }

        //     // Vérifier que la date est dans le futur
        //     $now = new \DateTime();
        //     if ($rendezVous->getDateHeure() <= $now) {
        //         $this->addFlash('danger', 'Veuillez sélectionner une date et une heure dans le futur.');
        //         return $this->render('rendez_vous/edit.html.twig', [
        //             'rendez_vous' => $rendezVous,
        //             'form' => $form,
        //         ]);
        //     }

        // // Sécurité : On empêche de modifier un RDV qui n'est pas le sien
        // // (Sauf si on est ADMIN, à rajouter plus tard si besoin)
        // if ($rendezVous->getPatient() !== $this->getUser() && $rendezVous->getMedecin() !== $this->getUser()) {
        //     throw $this->createAccessDeniedException('Vous ne pouvez pas modifier ce rendez-vous.');
        // }

        $form = $this->createForm(RendezVousType::class, $rendezVous);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
           
            // LOGIQUE INTELLIGENTE :
            // Si on passe en Télémédecine et qu'il n'y a pas de lien, on en crée un.
            if ($rendezVous->getType() === 'Télémédecine' && empty($rendezVous->getLienVisio())) {
                $uniqueId = uniqid('cardiolink-');
                // Paramètres Jitsi pour permettre aux participants de rejoindre sans modérateur
                $jitsiUrl = "https://meet.jit.si/$uniqueId#config.disableModeratorIndicator=true&config.openBridgeChannel=true&userInfo.displayName=CardioLink";
                $rendezVous->setLienVisio($jitsiUrl);
                $rendezVous->setLieu(null); // On nettoie le lieu
            }

            // Si on repasse en Présentiel, on assigne le lieu selon le cabinet du médecin
            if ($rendezVous->getType() === 'Présentiel') {
                $rendezVous->setLienVisio(null);

                $medecin = $rendezVous->getMedecin();
                if ($medecin && $medecin->getCabinet()) {
                    // On cherche si un Lieu existe pour ce cabinet
                    $lieu = $lieuRepository->findOneBy(['nom' => $medecin->getCabinet()]);

                    if (!$lieu) {
                        // Si aucun Lieu n'existe, on en crée un
                        $lieu = new Lieu();
                        $lieu->setNom($medecin->getCabinet());
                        $lieu->setAdresse($medecin->getAdresse());
                        $lieu->setVille('');
                        $lieu->setEstVirtuel(false);
                        $entityManager->persist($lieu);
                    }

                    $rendezVous->setLieu($lieu);
                }
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

    #[Route('/{id}/status', name: 'app_rdv_update_status', methods: ['POST'])]
    #[IsGranted('ROLE_MEDECIN')]
    public function updateStatus(Request $request, RendezVous $rendezVous, EntityManagerInterface $entityManager): Response
    {
        // Sécurité : Vérifier que c'est le médecin du RDV
        if ($rendezVous->getMedecin() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas modifier ce rendez-vous.');
        }

        // Vérifier que la consultation n'est pas passée
        if ($rendezVous->isPassedConsultation()) {
            $this->addFlash('danger', 'Vous ne pouvez pas modifier le statut d\'une consultation passée.');
            return $this->redirectToRoute('app_rdv_index', [], Response::HTTP_SEE_OTHER);
        }

        // Get the new status from the request
        $newStatus = $request->request->get('status');

        // Validate the status
        $validStatuses = ['En attente', 'Accepté', 'Refusé', 'Complété'];
        if (!in_array($newStatus, $validStatuses)) {
            $this->addFlash('danger', 'Statut invalide.');
            return $this->redirectToRoute('app_rdv_index', [], Response::HTTP_SEE_OTHER);
        }

        // Verify CSRF token
        if (!$this->isCsrfTokenValid('status' . $rendezVous->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_rdv_index', [], Response::HTTP_SEE_OTHER);
        }

        // Update the status
        $rendezVous->setStatut($newStatus);
        $entityManager->flush();

        $this->addFlash('success', 'Le statut du rendez-vous a été mis à jour avec succès.');
        return $this->redirectToRoute('app_rdv_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/join-visio', name: 'app_rdv_join_visio', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function joinVisio(RendezVous $rendezVous,): Response
    {
        // Vérifier que c'est le patient ou le médecin du RDV
        $user = $this->getUser();
        if ($user instanceof \App\Entity\User) {
            $nom = $user->getNom();
            $prenom = $user->getPrenom();
        }
        if ($rendezVous->getPatient() !== $user && $rendezVous->getMedecin() !== $user) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à cette consultation.');
        }

        // Vérifier que c'est une télémédecine
        if ($rendezVous->getType() !== 'Télémédecine') {
            $this->addFlash('danger', 'Ce rendez-vous n\'est pas en télémédecine.');
            return $this->redirectToRoute('app_rdv_index', [], Response::HTTP_SEE_OTHER);
        }

        // Vérifier qu'il n'a pas passé longtemps après la consultation
        $now = new \DateTime();
        $consultationEndTime = (clone $rendezVous->getDateHeure())->add(new \DateInterval('PT2H'));
        if ($now > $consultationEndTime) {
            $this->addFlash('danger', 'Cette consultation est terminée.');
            return $this->redirectToRoute('app_rdv_index', [], Response::HTTP_SEE_OTHER);
        }

        // Get the Jitsi link
        $jitsiLink = $rendezVous->getLienVisio();
        if (!$jitsiLink) {
            $this->addFlash('danger', 'Le lien de la visioconférence n\'est pas disponible.');
            return $this->redirectToRoute('app_rdv_index', [], Response::HTTP_SEE_OTHER);
        }

        // Si c'est un médecin, ajouter le paramètre modérateur
        $isMedecin = $this->isGranted('ROLE_MEDECIN') && $rendezVous->getMedecin() === $user;
        if ($isMedecin) {
            // Ajouter des paramètres modérateur pour le médecin
            $separator = strpos($jitsiLink, '#') ? '&' : '#';
            $jitsiLink .= $separator . 'userInfo.displayName=' . urlencode($nom . ' ' . $prenom);
        } else {
            // Pour le patient, ajouter son nom aussi
            $separator = strpos($jitsiLink, '#') ? '&' : '#';
            $jitsiLink .= $separator . 'userInfo.displayName=' . urlencode($nom . ' ' . $prenom);
        }

        // Rediriger vers Jitsi
        return $this->redirect($jitsiLink);
    }
}
